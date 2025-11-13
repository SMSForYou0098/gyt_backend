<?php

namespace App\Http\Controllers;

use App\Exports\ComplimentaryBookingsExport;
use App\Jobs\ProcessComplimentaryBookings;
use App\Models\ComplimentaryBookings;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Queue;

class ComplimentaryBookingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = ComplimentaryBookings::query();
            $user = Auth::user();

            $dates = null;
            if ($request->has('date')) {
                $dates = $request->date ? explode(',', $request->date) : null;
            }

            if ($user->hasRole('Admin')) {
                $query->select('batch_id', 'reporting_user')
                    ->distinct()
                    ->with(['user', 'ticket.event']);
            } else {
                $query->select('batch_id', 'reporting_user')
                    ->distinct()
                    ->with(['user', 'ticket.event'])
                    ->where('reporting_user', $user->id)
                    ->orWhere('reporting_user', $user->reporting_user);
            }

            if ($dates) {
                if (count($dates) === 1) {
                    $singleDate = Carbon::parse($dates[0])->toDateString();
                    $query->whereDate('created_at', $singleDate);
                } elseif (count($dates) === 2) {
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[1])->endOfDay();
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }
            } else {
                $query->whereDate('created_at', Carbon::today());
            }

            $batchBookings = $query->get();

            $result = $batchBookings->map(function ($batchBooking) {
                $bookings = ComplimentaryBookings::where('batch_id', $batchBooking->batch_id)->get();
                $bookingCount = $bookings->count();
                $ticketName = $bookings->first()->ticket->name ?? null;
                $eventName = $bookings->first()->ticket->event->name ?? null;
                $bookingDate = $bookings->first()->created_at;
                $bookingType = $bookings->first()->type;
                $data = $bookingType == 'imported' ? 1 : 0;
                $user = $batchBooking->reportingUser;

                return [
                    'name' => $user?->name,
                    'number' => $user?->number,
                    'booking_count' => $bookingCount,
                    'ticket_name' => $ticketName,
                    'event_name' => $eventName,
                    'booking_date' => $bookingDate,
                    'batch_id' => $batchBooking->batch_id,
                    'type' => $data,
                ];
            });

            return response()->json([
                'status' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve bookings',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function getTokensByBatchId(Request $request)
    {
        $batchId = $request->batch_id;

        $tokens = ComplimentaryBookings::where('batch_id', $batchId)
        ->with('ticket.event.user')
        ->get();

        return response()->json([
            'status' => true,
            'tokens' => $tokens,
        ]);
    }

    //number booking
    public function storeData(Request $request)
    {
        // Get the quantity
        $quantity = $request->input('quantity');

        // Create an array to hold the new records
        $bookings = [];
        $batchId = uniqid();
        // Loop through the quantity and create records
        for ($i = 0; $i < $quantity; $i++) {
            $bookings[] = [
                'reporting_user' => $request->input('user_id'),
                'batch_id' => $batchId,
                'ticket_id' => $request->input('ticket_id'),
                'token' => $this->generateRandomCode(),
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'number' => $request->input('number'),
                'type' => 'generated',
                'status' => 0,
                'created_at' => now(),
            ];
        }

        // Insert all records into the database
        ComplimentaryBookings::insert($bookings);

        $insertedUserIds = array_column($bookings, 'reporting_user');
        $bookingData = ComplimentaryBookings::where('batch_id', $batchId)
            ->with('ticket.event.user')
            ->get();
        // Return a response
        return response()->json([
            'status' => true,
            'message' => 'Complimentary bookings created successfully.',
            'bookings' => $bookings,
        ], 201);

    }

    //exel booking

    // 500 record impoart use queue
    // public function store(Request $request)
    // {
    //     try {

    //         $user = $request->user;
    //         $userId = $request->user_id;
    //         $ticketId = $request->ticket_id;
    //         if (is_array($user) && !empty($user)) {
    //             dispatch(new ProcessComplimentaryBookings(serialize($user), $userId, $ticketId));
    //         } else {
    //             Log::error('User data is not valid: ', [$user]);
    //         }


    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Booking process has been queued successfully.',
    //             'batch_tracking_id' => uniqid(),
    //         ], 202);

    //     } catch (\Exception $e) {
    //         Log::error('Job failed with error: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to queue booking process',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    public function store(Request $request)
    {
        $user = $request->user;
        $userId = $request->user_id;
        $ticketId = $request->ticket_id;
        $token = $request->token;

        $bookings = [];
        $batchId = uniqid();

        foreach ($user as $userData) {
            $existingUserByNumber = User::where('number', $userData['number'])->first();

            if ($existingUserByNumber) {
                $existingUser = $existingUserByNumber;
            } else {
                $existingUserByEmail = User::where('email', $userData['email'])->first();

                if ($existingUserByEmail) {
                    $existingUser = $existingUserByEmail;
                } else {
                    $existingUser = new User();
                    $existingUser->name = $userData['name'];
                    $existingUser->email = $userData['email'];
                    $existingUser->number = $userData['number'];
                    $existingUser->password = Hash::make($userData['number']);
                    $existingUser->status = true;
                    $existingUser->reporting_user = $userId;

                    $existingUser->save();
                    $this->updateUserRole($request, $existingUser);
                }
            }


            $bookings[] = [
                'user_id' => $existingUser->id,
                'batch_id' => $batchId,
                'ticket_id' => $ticketId,
                'token' => $userData['token'] ?? $this->generateRandomCode(),
                // 'token' => $this->generateRandomCode(),
                'name' => $existingUser->name,
                'email' => $existingUser->email,
                'number' => $existingUser->number,
                'reporting_user' => $userId,
                'status' => 0,
                'created_at' => now(),
                'type' => 'imported',
            ];
        }

        // Insert multiple bookings
        ComplimentaryBookings::insert($bookings);

        // Retrieve inserted bookings with relationships
        $insertedUserIds = array_column($bookings, 'user_id');
        $bookingData = ComplimentaryBookings::where('batch_id', $batchId)
            ->with('ticket.event.user') // Load related data
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Complimentary bookings created successfully.',
            'users' => $insertedUserIds,
            'bookings' => $bookingData,
        ], 201);
    }

    // public function store(Request $request)
    // {
    //     $user = $request->user;
    //     $userId = $request->user_id;
    //     $ticketId = $request->ticket_id;

    //     $bookings = [];
    //     $batchId = uniqid();

    //     foreach ($user as $userData) {
    //         $existingUserByNumber = User::where('number', $userData['number'])->first();

    //         if ($existingUserByNumber) {
    //             $existingUser = $existingUserByNumber;
    //         } else {
    //             $existingUserByEmail = User::where('email', $userData['email'])->first();

    //             if ($existingUserByEmail) {
    //                 $existingUser = $existingUserByEmail;
    //             } else {
    //                 $existingUser = new User();
    //                 $existingUser->name = $userData['name'];
    //                 $existingUser->email = $userData['email'];
    //                 $existingUser->number = $userData['number'];
    //                 $existingUser->password = Hash::make($userData['number']);
    //                 $existingUser->status = true;
    //                 $existingUser->reporting_user = $userId;

    //                 $existingUser->save();
    //                 $this->updateUserRole($request, $existingUser);
    //             }
    //         }

    //         $bookings[] = [
    //             'user_id' => $existingUser->id,
    //             'batch_id' => $batchId,
    //             'ticket_id' => $ticketId,
    //             'token' => $this->generateRandomCode(),
    //             'name' => $existingUser->name,
    //             'email' => $existingUser->email,
    //             'number' => $existingUser->number,
    //             'reporting_user' => $userId,
    //             'status' => 0,
    //             'created_at' => now(),
    //             'type' => 'imported',
    //         ];
    //     }

    //     // Insert multiple bookings
    //     ComplimentaryBookings::insert($bookings);

    //     // Retrieve inserted bookings with relationships
    //     $insertedUserIds = array_column($bookings, 'user_id');
    //     $bookingData = ComplimentaryBookings::where('batch_id', $batchId)
    //         ->with('ticket.event.user') // Load related data
    //         ->get();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Complimentary bookings created successfully.',
    //         'users' => $insertedUserIds,
    //         'bookings' => $bookingData,
    //     ], 201);
    // }

    public function checkUsers(Request $request)
    {

        $users = $request->input('users');

        if (!is_array($users)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid input. Expected an array of users.',
            ], 422);
        }

        $results = [];

        foreach ($users as $user) {
            $email = $user['email'] ?? null;
            $number = $user['number'] ?? null;

            if (!$email || !$number) {
                $results[] = [
                    'email' => $email,
                    'number' => $number,
                    'exists' => false,
                    'error' => 'Both email and number are required.',
                ];
                continue;
            }

            $existingUser = User::where('email', $email)
                ->orWhere('number', $number)
                ->first();

            if ($existingUser) {
                $results[] = [
                    'email' => $email,
                    'number' => $number,
                    'exists' => true,
                    'email_exists' => $existingUser->email == $email,
                    'number_exists' => $existingUser->number == $number,

                ];
            }
        }

        return response()->json([
            'status' => true,
            'results' => $results,
        ]);
    }

    private function generateRandomCode($length = 8)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@$*';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    private function updateUserRole($request, $user)
    {
        $defaultRoleName = 'User';
        if ($request->has('role_id') && $request->role_id) {
            $role = Role::find($request->role_id);
            if ($role) {
                $user->syncRoles([]);
                $user->assignRole($role);
            }
        } else {
            $defaultRole = Role::where('name', $defaultRoleName)->first();
            if ($defaultRole) {
                $user->syncRoles([]);
                $user->assignRole($defaultRole);
            }
        }
    }

    public function export(Request $request)
    {

        $Name = $request->input('name');
        $batchId = $request->input('batch_id');
        $Number = $request->input('number');
        $eventName = $request->input('ticket_id');
        $status = $request->input('status');
        $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

        $query = ComplimentaryBookings::query();

        if ($request->has('ticket_id')) {
            $query->where('ticket_id', $eventName);
        }

        if ($request->has('name')) {
            $query->where('name', $Name);
        }

        if ($request->has('number')) {
            $query->where('number', $Number);
        }

        if ($request->has('status')) {
            $query->where('status', $status);
        }

        if ($dates) {
            if (count($dates) === 1) {
                $singleDate = Carbon::parse($dates[0])->toDateString();
                $query->whereDate('created_at', $singleDate);
            } elseif (count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }
        }

        $ComplimentaryBookings = $query->with([
            'ticket.event',
            'ticket'
        ])->get();
        return response()->json(['ComplimentaryBookings' => $ComplimentaryBookings]);
        return Excel::download(new ComplimentaryBookingsExport($ComplimentaryBookings), 'ComplimentaryBookings_export.xlsx');
    }

}
