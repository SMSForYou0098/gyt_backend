<?php

namespace App\Http\Controllers;

use App\Exports\AccreditationBookingExport;
use App\Models\AccessArea;
use App\Models\AccreditationBooking;
use App\Models\AccreditationMasterBooking;
use App\Models\Balance;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsappApi;
use App\Services\SmsService;
use App\Services\WhatsappService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;

class AccreditationBookingController extends Controller
{
    // List All Bookings
    public function list(Request $request, $id)
    {
        try {
            $loggedInUser = Auth::user();
            $isAdmin = $loggedInUser->hasRole('Admin') || $loggedInUser->hasRole('Organizer');

            if ($request->has('date')) {
                $dates = explode(',', $request->date);
                if (count($dates) === 1 || ($dates[0] === $dates[1])) {
                    // Single date
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[0])->endOfDay();
                } elseif (count($dates) === 2) {
                    // Date range
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[1])->endOfDay();
                } else {
                    return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
                }
            } else {
                // Default: Today's bookings
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
            }

            if ($isAdmin) {
                $Masterbookings = AccreditationMasterBooking::withTrashed()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get();
                $allBookingIds = [];
                $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $startDate, $endDate) {
                    $bookingIds = $masterBooking->booking_id;

                    if (is_array($bookingIds)) {
                        $allBookingIds = array_merge($allBookingIds, $bookingIds);
                        $masterBooking->bookings = AccreditationBooking::withTrashed()
                            ->whereIn('id', $bookingIds)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->with(['ticket.event.user', 'user:id,name,number,email,photo,doc,reporting_user,company_name,designation'])
                            ->latest()
                            ->get()
                            ->map(function ($booking) {
                                $booking->agent_name = $booking->user->name ?? '';
                                $booking->event_name = $booking->ticket->event->name ?? '';
                                $booking->organizer = $booking->ticket->event->user->name ?? '';
                                $booking->access_area_names = AccessArea::whereIn('id', $booking->ticket->access_area ?? [])->pluck('title');
                                return $booking;
                            })->sortBy('id')->values();
                    } else {
                        $masterBooking->bookings = collect();
                    }
                })->map(function ($masterBooking) {
                    $masterBooking->is_deleted = $masterBooking->trashed();
                    $masterBooking->quantity = count($masterBooking->bookings);
                    return $masterBooking;
                });

                $normalBookings = AccreditationBooking::withTrashed()
                    ->with(['ticket.event.user', 'user:id,name,number,email,photo,doc,reporting_user,company_name,designation'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get()
                    ->map(function ($booking) {
                        $booking->agent_name = $booking->user->name ?? '';
                        $booking->event_name = $booking->ticket->event->name ?? '';
                        $booking->organizer = $booking->ticket->event->user->name ?? '';
                        $booking->access_area_names = AccessArea::whereIn('id', $booking->ticket->access_area ?? [])->pluck('title');
                        $booking->is_deleted = $booking->trashed();
                        $booking->quantity = 1;

                        return $booking;
                    });

                $filteredNormalBookings = $normalBookings->filter(function ($booking) use ($allBookingIds) {
                    return !in_array($booking->id, $allBookingIds);
                })->values();

                $combinedBookings = $Masterbookings->concat($filteredNormalBookings);
                $sortedCombinedBookings = $combinedBookings->sortByDesc('created_at')->values();

                return response()->json([
                    'status' => true,
                    'bookings' => $sortedCombinedBookings,
                ], 200);
            } else {
                $eventIds = Event::where('user_id', $id)->pluck('id');
                $tickets = Ticket::whereIn('event_id', $eventIds)->pluck('id');

                $Masterbookings = AccreditationMasterBooking::withTrashed()
                    ->where('accreditation_id', $id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get();

                $allBookingIds = [];
                $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $tickets, $startDate, $endDate) {
                    $bookingIds = $masterBooking->booking_id;

                    if (is_array($bookingIds)) {
                        $allBookingIds = array_merge($allBookingIds, $bookingIds);
                        $masterBooking->bookings = AccreditationBooking::whereIn('id', $bookingIds)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->with(['ticket.event.user', 'user:id,name,number,email,photo,doc,reporting_user,company_name,designation'])
                            ->latest()
                            ->get()
                            ->map(function ($booking) {
                                $booking->event_name = $booking->ticket->event->name;
                                $booking->organizer = $booking->ticket->event->user->name;

                                return $booking;
                            })->sortBy('id')->values();
                    } else {
                        $masterBooking->bookings = collect();
                    }
                })->map(function ($masterBooking) {
                    $masterBooking->is_deleted = $masterBooking->trashed();
                    $masterBooking->quantity = count($masterBooking->bookings);
                    return $masterBooking;
                });

                $normalBookings = AccreditationBooking::withTrashed()
                    ->with(['ticket.event.user:id,name,number,email,photo,reporting_user,doc,company_name,designation', 'user:id,name,number,email,photo,doc,reporting_user,company_name,designation'])
                    ->where('accreditation_id', $id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get()
                    ->map(function ($booking) {
                        $booking->event_name = $booking->ticket->event->name;
                        $booking->organizer = $booking->ticket->event->user->name;
                        $booking->access_area_names = AccessArea::whereIn('id', $booking->ticket->access_area ?? [])->pluck('title');
                        $booking->is_deleted = $booking->trashed();
                        $booking->quantity = 1;
                        return $booking;
                    });

                $filteredNormalBookings = $normalBookings->filter(function ($booking) use ($allBookingIds) {
                    return !in_array($booking->id, $allBookingIds);
                })->values();

                $combinedBookings = $Masterbookings->concat($filteredNormalBookings);
                $sortedCombinedBookings = $combinedBookings->sortByDesc('created_at')->values();

                return response()->json([
                    'status' => true,
                    'bookings' => $sortedCombinedBookings,
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage() . "on line" . $e->getLine(),
            ], 500);
        }
    }

    //store booking
    public function store(Request $request, $id, SmsService $smsService, WhatsappService $whatsappService)
    {
        try {
            $user = auth()->user();

            $bookings = [];
            $firstIteration = true;
            $attendees = $request->attendees ?? [];
            if (!is_array($attendees)) {
                $attendees = [];
            }
            if ($request->tickets['quantity'] > 0) {

                $bookingIds = $request->input('access_area');


                if (is_null($bookingIds)) {
                    $bookingIds = [];
                } elseif (is_string($bookingIds)) {
                    // Convert string like "6,8" to array
                    $bookingIds = explode(',', $bookingIds);
                }

                // Clean and convert all values to integers
                $bookingIds = array_map('intval', array_filter($bookingIds, fn($id) => trim($id) !== ''));

                for ($i = 0; $i < $request->tickets['quantity']; $i++) {
                    $booking = new AccreditationBooking();
                    $booking->ticket_id = $request->tickets['id'];
                    $booking->accreditation_id = $request->agent_id;
                    $booking->user_id = $request->user_id;
                    $booking->access_area = $bookingIds;

                    $ticket = Ticket::findOrFail($request->tickets['id']);
                    $event = $ticket->event;

                    $booking->token = $this->generateHexadecimalCode();
                    $booking->email = $request->email;
                    $booking->name = $request->name;
                    $booking->number = $request->number;
                    $booking->type = $request->type;
                    $booking->payment_method = $request->payment_method;
                    // $booking->attendee_id = $request->attendees[$i]['id'] ?? null;
                    $booking->status = 0;
                    $booking->attendee_id = isset($attendees[$i]) && is_array($attendees[$i])
                        ? ($attendees[$i]['id'] ?? null)
                        : null;

                    // Set price only on the first iteration
                    if ($firstIteration) {
                        $booking->amount = $request->amount;
                        $booking->discount = $request->discount;
                        $booking->base_amount = $request->base_amount;
                        $booking->convenience_fee = $request->convenience_fee;
                        $firstIteration = false;
                    }

                    $booking->save();
                    $booking->load(['user:id,name,number,email,photo,reporting_user,company_name,designation', 'ticket.event.user.smsConfig']);
                    $bookings[] = $booking;

                    $orderId = $booking->token ?? '';
                    // $shortLink = 'getyourticket.in/t/' . $orderId;
                    $shortLink =  $orderId;
                    $whatsappTemplate = WhatsappApi::where('title', 'garbabookingv2')->first();
                    $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

                    // $eventDateTime = $booking->ticket->event->date_range;
                    $eventDateTime = str_replace(',', ' |', $event->date_range) . ' | ' . $event->start_time . ' - ' . $event->end_time;
                    $mediaurl =  $event->thumbnail;
                    $data = (object) [
                        'name' => $booking->name,
                        'number' => $booking->number,
                        'templateName' => 'Booking Template online',
                        'whatsappTemplateData' => $whatsappTemplateName,
                        'mediaurl' => $mediaurl,
                        'shortLink' => $shortLink,
                        'values' => [
                            $booking->name,
                            $booking->number,
                            $event->name,
                            $request->tickets['quantity'],
                            $ticket->name,
                            $event->address,
                            $eventDateTime,
                        ],
                        'replacements' => [
                            ':C_Name' => $booking->name,
                            ':T_QTY' => $request->tickets['quantity'],
                            ':Ticket_Name' => $ticket->name,
                            ':Event_Name' => $event->name,
                            ':C_number' => $booking->number,
                        ]
                    ];


                    if ($i === 0) {
                        $smsService->send($data);
                        $whatsappService->send($data);
                    }
                }
            }

            return response()->json(['status' => true, 'message' => 'Tickets Booked Successfully', 'bookings' => $bookings], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to book tickets', 'error' => $e->getMessage()], 500);
        }
    }

    //store master booking
    public function AccreditationMaster(Request $request, $id)
    {
        try {
            $user = auth()->user();
            // if ($user->hasRole('Accreditation')) {
            //     $latestBalance = Balance::where('user_id', $user->id)->latest()->first();

            //     if (!$latestBalance) {
            //         return response()->json([
            //             'status' => false,
            //             'message' => 'Balance not found for the Accreditation.'
            //         ], 400);
            //     }

            //     $totalAmount = $request->amount;

            //     if ($latestBalance->total_credits < $totalAmount) {
            //         return response()->json([
            //             'status' => false,
            //             'message' => 'Not sufficient balance.'
            //         ], 400);
            //     }
            // }
            $agentMasterBooking = new AccreditationMasterBooking();
            $bookingIds = $request->input('bookingIds');

            if (is_string($bookingIds)) {
                $bookingIds = json_decode($bookingIds, true);

                if (is_null($bookingIds)) {
                    $bookingIds = explode(',', trim($bookingIds, '[]'));
                }
            }

            // Save the master booking details
            $agentMasterBooking->booking_id = $bookingIds;
            $agentMasterBooking->user_id = $request->user_id;
            $agentMasterBooking->accreditation_id = $request->agent_id;

            // $agentMasterBooking->order_id = $this->generateRandomCode(); // Generate an order ID
            $agentMasterBooking->order_id = $this->generateHexadecimalCode(); // Generate an order ID
            $agentMasterBooking->amount = $request->amount;
            $agentMasterBooking->discount = $request->discount ?? 0;
            $agentMasterBooking->payment_method = $request->payment_method;
            $agentMasterBooking->save();

            if ($user->hasRole('Accreditation')) {
                $newTotalCredits = $latestBalance->total_credits - $request->amount;

                $newBalance = new Balance();
                $newBalance->user_id = $user->id;
                $newBalance->total_credits = $newTotalCredits;
                $newBalance->new_credit = $request->amount;
                $newBalance->booking_id = $agentMasterBooking->id;  // correctly link to the AgentMaster record
                $newBalance->payment_method = $request->payment_method ?? 'cash';
                $newBalance->payment_type = 'debit';
                $newBalance->transaction_id = $this->generateTransactionId();
                $newBalance->description = 'agentMasterBooking';

                $newBalance->save();
            }
            // Retrieve the created agent master booking
            $agentMasterBookingDetails = AccreditationMasterBooking::where('order_id', $agentMasterBooking->order_id)->with('user:id,name,number,email,photo,reporting_user,company_name,designation')->first();

            if ($agentMasterBookingDetails) {
                $bookingIds = $agentMasterBookingDetails->booking_id;
                if (is_array($bookingIds)) {
                    $agentMasterBookingDetails->bookings = AccreditationBooking::whereIn('id', $bookingIds)->with('ticket.event.user:id,name,number,email,photo,reporting_user,company_name,designation.smsConfig')->get();
                } else {
                    $agentMasterBookingDetails->bookings = collect();
                }
            }


            return response()->json([
                'status' => true,
                'message' => 'Sponsor Master Ticket Created Successfully',
                'booking' => $agentMasterBookingDetails
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create Sponsor master booking', 'error' => $e->getMessage()], 500);
        }
    }

    public function userFormNumber(Request $request, $id)
    {
        try {
            // Find user by number
            $user = User::where('number', $id)->first();

            if ($user) {
                return response()->json([
                    'status' => true,
                    'message' => 'User fetched successfully',
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'photo' => $user->photo
                    ],
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($token)
    {
        // Check if it's a master booking
        $Masterbookings = AccreditationMasterBooking::where('order_id', $token)->first();

        if ($Masterbookings) {
            $bookingIds = is_array($Masterbookings->booking_id)
                ? $Masterbookings->booking_id
                : json_decode($Masterbookings->booking_id, true);

            if (!empty($bookingIds) && is_array($bookingIds)) {
                AccreditationBooking::whereIn('id', $bookingIds)->delete(); // Delete related bookings
            }

            $Masterbookings->delete(); // Delete master booking

            return response()->json([
                'status' => true,
                'message' => 'Master Booking and related bookings deleted successfully'
            ], 200);
        }

        // If not master, try deleting normal booking
        $normalBooking = AccreditationBooking::where('token', $token)->first();

        if ($normalBooking) {
            $normalBooking->delete();

            return response()->json([
                'status' => true,
                'message' => 'Booking deleted successfully'
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'Booking not found'
        ], 404);
    }

    public function restoreBooking($token)
    {
        // First, check if it's a master booking
        $Masterbooking = AccreditationMasterBooking::withTrashed()->where('order_id', $token)->first();

        if ($Masterbooking) {
            // Restore master booking
            $Masterbooking->restore();

            // Get related booking IDs and restore them
            $bookingIds = is_array($Masterbooking->booking_id)
                ? $Masterbooking->booking_id
                : json_decode($Masterbooking->booking_id, true);

            if (!empty($bookingIds) && is_array($bookingIds)) {
                AccreditationBooking::withTrashed()->whereIn('id', $bookingIds)->restore();
            }

            return response()->json([
                'status' => true,
                'message' => 'Master Booking and related bookings restored successfully'
            ], 200);
        }

        // Else try restoring a normal booking
        $normalBooking = AccreditationBooking::withTrashed()->where('token', $token)->first();

        if ($normalBooking) {
            $normalBooking->restore();

            return response()->json([
                'status' => true,
                'message' => 'Booking restored successfully'
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'Booking not found'
        ], 404);
    }

    // Export Bookings
    public function export(Request $request)
    {
        $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

        $query = AccreditationBooking::withTrashed()
            ->with(['ticket.event.user', 'user']);

        // Apply date filter
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

        $bookings = $query->latest()
            ->get()
            ->map(function ($booking) {
                $booking->event_name = $booking?->ticket?->event?->name ?? 'N/A';
                $booking->organizer = $booking?->ticket?->event?->user?->name ?? 'N/A';
                $booking->is_deleted = $booking?->trashed();
                $booking->quantity = 1;
                return $booking;
            });

        return Excel::download(new AccreditationBookingExport($bookings), 'AccreditationBooking_export.xlsx');
    }


    private function generateTransactionId()
    {
        return strtoupper(bin2hex(random_bytes(10)));
    }

    private function generateHexadecimalCode($length = 8)
    {
        $characters = '0123456789ABCDEF';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
