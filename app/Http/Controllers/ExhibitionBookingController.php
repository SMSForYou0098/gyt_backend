<?php

namespace App\Http\Controllers;

use App\Models\ExhibitionBooking;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExhibitionBookingController extends Controller
{

    public function index(Request $request ,$id)
    {
        // Get logged-in user and role check
        $loggedInUser = Auth::user(); // Authenticated user
        $isAdmin = $loggedInUser->hasRole('Admin'); // Role-checking logic

        $userIds = collect();
        $dates = null;
        if ($request->has('date')) {

            $dates = $request->date ? explode(',', $request->date) : null;
        }

        $query = ExhibitionBooking::query(); // Base query initialization

        // Handle date filtering
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
            // Default to today's date if no date parameter
            $todayDate = Carbon::today();
            $query->whereDate('created_at', $todayDate);
        }

        if ($isAdmin) {
            // Admin can access all bookings (trashed included)
            $bookingsQuery = $query->withTrashed();
            $activeBookingsQuery = $query;
        } else {
            // Organizer and other roles can access only their bookings and those under them
            $underUserIds = $loggedInUser->usersUnder()->pluck('id');
            $userIds = $underUserIds->push($loggedInUser->id);

            $bookingsQuery = $query->withTrashed()->whereIn('user_id', $userIds);
            $activeBookingsQuery = $query->whereIn('user_id', $userIds);
        }

        // Fetch bookings with related data
        $bookings = $bookingsQuery->latest()
            ->with([
                'ticket.event',
                'user:id,name,reporting_user',
                'user.reportingUser:id,name',
            ])
            ->get()
            ->map(function ($booking) {
                $booking->is_deleted = $booking->trashed();
                $booking->user_name = $booking->user->name ?? 'Unknown User';
                $booking->reporting_user_name = $booking->user->reportingUser->name ?? 'No Reporting User';
                $booking->attendee = $booking->attendee ?? 'No attendee User';
                return $booking;
            });

        // Calculate totals for active bookings
        $amount = $activeBookingsQuery->sum('amount');
        $discount = $activeBookingsQuery->sum('discount');

        return response()->json([
            'status' => $bookings->isNotEmpty(),
            'bookings' => $bookings,
            'amount' => $amount,
            'discount' => $discount,
            'message' => $bookings->isNotEmpty() ? null : 'No Bookings Found',
        ],  200 );
    }

    public function create(Request $request)
    {
        try {
            // return response()->json(['bookings' => $request->tickets[0]['id']], 201);
            $booking = new ExhibitionBooking();

            $ticket = Ticket::findOrFail($request->tickets['id']);
            $event = $ticket->event;

            // if ($event->rfid_required == 1) {
            //     $booking->token = $this->generateHexadecimalCode();
            // } else {
            //     $booking->token = $this->generateRandomCode();
            // }

            // $booking->token = $this->generateRandomCode();
            $booking->token = $this->generateHexadecimalCode();
            $booking->user_id = $request->user_id;
            $booking->agent_id = $request->agent_id;
            $booking->attendee_id = $request->attendee_id;
            $booking->ticket_id = $request->tickets['id'];
            $booking->quantity = $request->tickets['quantity'];
            $booking->discount = $request->discount;
            $booking->amount = $request->amount;
            $booking->payment_method = $request->payment_method;
            $booking->type = $request->type;
            $booking->date = $request->date;
            $booking->status = 0;
            $booking->save();
            $booking->load(['ticket.event.user','attendee']);
            return response()->json(['status' => true, 'message' => 'Exhibition Tickets Booked Successfully', 'bookings' => $booking], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to book tickets', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        $booking = ExhibitionBooking::findOrFail($id);
        $booking->delete();
        return response()->json(['status' => true], 200);

    }

    public function restoreBooking($id)
    {
        $bookings = ExhibitionBooking::withTrashed()->findOrFail($id);

        if ($bookings) {
            $bookings->restore();
            return response()->json(['status' => true, 'message' => 'Booking restored successfully']);
        } else {
            return response()->json(['message' => 'Booking not found']);
        }
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

    private function generateHexadecimalCode($length = 8)
    {
        $characters = '0123456789ABCDEF'; // Hexadecimal characters
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

