<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserTicketController extends Controller
{
    public function index($userId)
    {
        $userData = UserTicket::where('user_id', $userId)->get();
        if ($userData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'user ticket not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $userData,
        ], 200);
    }

    // public function ticketTransfer(Request $request)
    // {
    //     $recipientData = $request->input('recipient');

    //     if (!$recipientData || !isset($recipientData['number'])) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Recipient number is required',
    //         ], 422);
    //     }

    //     $number = $recipientData['number'];

    //     $user = User::where('number', $number)->first();

    //     if (!$user) {
    //         $user = User::create([
    //             'name' => $recipientData['name'] ?? 'Unnamed User',
    //             'email' => $recipientData['email'] ?? null,
    //             'number' => $number,
    //             'password' => bcrypt('123456'),
    //             'status' => 1,
    //         ]);
    //     }
    //     $newUserId = $user->id;

    //     $bookingId = $request->input('bookingId');
    //     $eventId = $request->input('eventId');
    //     $ticketSelectionType = $request->input('ticketSelectionType');
    //     $tableName = $request->input('table');

    //     if (!$bookingId || !$eventId || !$tableName) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Required bookingId, eventId, or table missing.',
    //         ], 422);
    //     }

    //     $bookingModelClass = "App\\Models\\" . $tableName;

    //     if (!class_exists($bookingModelClass)) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Invalid table/model name.',
    //         ], 422);
    //     }

    //     $bookings = collect();

    //     if ($ticketSelectionType === 'all') {
    //         // All bookings of this event
    //         $bookings = $bookingModelClass::find($bookingId);
    //     } else {
    //         // Only the specific booking
    //         $booking = $bookingModelClass::find($bookingId);
    //         if ($booking) {
    //             $bookings = collect([$booking]);
    //         }
    //     }
    //     return response()->json($bookings);
    //     foreach ($bookings as $booking) {
    //         $oldUserId = $booking->user_id;

    //         // Transfer booking ownership
    //         $booking->user_id = $newUserId;
    //         $booking->save();

    //         $bookingIds = is_array($bookings->booking_id) ? $bookings->booking_id : json_decode($bookings->booking_id, true);
    //         // Optional: Update OnlineBale table if exists
    //         DB::table('online_bale')
    //             ->where('booking_id', $booking->id)
    //             ->where('user_id', $oldUserId)
    //             ->update(['user_id' => $newUserId]);
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Ticket transferred successfully',
    //         // 'data' => $ticket,
    //     ], 200);
    // }
    // public function ticketTransfer(Request $request)
    // {
    //     $recipientData = $request->input('recipient');

    //     if (!$recipientData || !isset($recipientData['number'])) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Recipient number is required',
    //         ], 422);
    //     }

    //     // Step 1: Check or Create User
    //     $number = $recipientData['number'];
    //     $user = User::where('number', $number)->first();

    //     if (!$user) {
    //         $user = User::create([
    //             'name' => $recipientData['name'] ?? 'Unnamed User',
    //             'email' => $recipientData['email'] ?? null,
    //             'number' => $number,
    //             'password' => bcrypt('123456'),
    //             'status' => 1,
    //         ]);
    //     }

    //     $newUserId = $user->id;

    //     // Step 2: Get master record
    //     $bookingId = $request->input('bookingId');
    //     $table = $request->input('table'); 
    //     $type = strtolower($request->input('type'));

    //     $masterModelClass = "\\App\\Models\\$table";

    //     if (!class_exists($masterModelClass)) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Invalid master table name',
    //         ], 400);
    //     }

    //     $masterBooking = $masterModelClass::find($bookingId);

    //     if (!$masterBooking) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Master booking not found',
    //         ], 404);
    //     }

    //     // Step 3: Update master table user_id
    //     $oldUserId = $masterBooking->user_id;
    //     $masterBooking->user_id = $newUserId;
    //     $masterBooking->save();

    //     // Step 4: Get child booking IDs
    //     $bookingIds = is_array($masterBooking->booking_id) ? $masterBooking->booking_id : json_decode($masterBooking->booking_id, true);

    //     // Step 5: Map type to child model
    //     $childModelMap = [
    //         'master' => \App\Models\Booking::class,
    //         'agent' => \App\Models\Agent::class,
    //         'sponsor' => \App\Models\SponsorBooking::class,
    //     ];

    //     if (!isset($childModelMap[$type])) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Invalid booking type provided',
    //         ], 400);
    //     }

    //     $childModel = $childModelMap[$type];

    //     // Step 6: Update all child bookings
    //     $childBookings = $childModel::whereIn('id', $bookingIds)->get();

    //     foreach ($childBookings as $childBooking) {
    //         $childBooking->user_id = $newUserId;
    //         $childBooking->save();
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Ticket transferred successfully',
    //         'transferred_booking_ids' => $bookingIds,
    //         'recipient_user_id' => $newUserId
    //     ], 200);
    // }

    public function ticketTransfer(Request $request)
    {
        $recipientData = $request->input('recipient');
        $table = $request->input('table'); // e.g., Agent, Booking
        $type = strtolower($request->input('type')); // single | master
        $ticketSelectionType = $request->input('ticketSelectionType'); // all | individual
        $bookingId = $request->input('bookingId');
        $ticketQuantity = $request->input('ticketQuantity');

        if (!$recipientData || !isset($recipientData['number'])) {
            return response()->json(['status' => false, 'message' => 'Recipient number is required'], 422);
        }

        $user = User::where('number', $recipientData['number'])->first();

        if (!$user) {
            $user = User::create([
                'name' => $recipientData['name'] ?? 'Unnamed',
                'email' => $recipientData['email'] ?? null,
                'number' => $recipientData['number'],
                'password' => bcrypt('123456'),
                'status' => 1,
            ]);
        }

        $newUserId = $user->id;

        $modelClass = "\\App\\Models\\$table";

        if (!class_exists($modelClass)) {
            return response()->json(['status' => false, 'message' => 'Invalid table name'], 400);
        }


        if ($type === 'single') {

            if ($ticketSelectionType === 'all') {
                $records = $modelClass::find($bookingId);
                $records->user_id = $newUserId;
                $records->save();
            } else {
                $records = $modelClass::find($bookingId)->limit($ticketQuantity)->get();
                foreach ($records as $record) {
                    $record->user_id = $newUserId;
                    $record->save();
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Ticket(s) transferred (single type)',
                'updated_count' => $records->count(),
            ]);
        } elseif ($type === 'master') {

            $masterClass = $modelClass;
            $master = $masterClass::find($bookingId);   

            if (!$master) {
                return response()->json(['status' => false, 'message' => 'Master record not found'], 404);
            }

            // Update master user
            $master->user_id = $newUserId;
            $master->save();

            // Decode booking_id field
            $bookingIds = is_array($master->booking_id) ? $master->booking_id : json_decode($master->booking_id, true);

            $childModelMap = [
                'MasterBooking' => \App\Models\Booking::class,
                'AgentMaster' => \App\Models\Agent::class,
                'SponsorMaster' => \App\Models\SponsorBooking::class,
            ];

            $tableName = class_basename($masterClass);

            if (!isset($childModelMap[$tableName])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Child booking model not mapped for this master table'
                ], 400);
            }

            $childModelClass = $childModelMap[$tableName];

            // Update each child booking
            foreach ($bookingIds as $childId) {
                $booking = $childModelClass::find($childId);
                if ($booking) {
                    $booking->user_id = $newUserId;
                    $booking->save();
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Master & child bookings transferred successfully',
                'master_id' => $master->id,
                'recipient_user_id' => $newUserId
            ]);
        }

        return response()->json(['status' => false, 'message' => 'Invalid type'], 400);
    }
}
