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
