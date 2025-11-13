<?php

namespace App\Http\Controllers;

use App\Models\AmusementBooking;
use App\Models\AmusementMasterBooking;
use App\Models\Event;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AmusementBookingController extends Controller
{

    public function onlineBookings(Request $request, $id)
    {
        try {
            $loggedInUser = Auth::user(); // Fetch authenticated user
            $isAdmin = $loggedInUser->hasRole('Admin');

            if ($request->has('date')) {

                $dates = $request->date ? explode(',', $request->date) : null;

                if (count($dates) === 1) {
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[0])->endOfDay();
                } elseif (count($dates) === 2) {
                    $startDate = Carbon::parse($dates[0])->startOfDay()->addDay(1);
                    $endDate = Carbon::parse($dates[1])->endOfDay();
                } else {
                    return response()->json(['status' => 'false', 'message' => 'Invalid date format'], 400);
                }
            } else {
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
            }

            if ($isAdmin) {
                $Masterbookings = AmusementMasterBooking::withTrashed()
                    ->where('agent_id', null)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get();
                $allBookingIds = [];
                $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $startDate, $endDate) {
                    $bookingIds = $masterBooking->booking_id;
                    $bookingIds = is_string($bookingIds) && is_array(json_decode($bookingIds, true)) ? json_decode($bookingIds, true) : $bookingIds;
                    if (is_array($bookingIds)) {
                        $allBookingIds = array_merge($allBookingIds, $bookingIds);
                        $masterBooking->bookings = AmusementBooking::whereIn('id', $bookingIds)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->with(['ticket.event.user', 'user'])
                            ->latest()
                            ->get()
                            ->map(function ($booking) {
                                $booking->event_name = $booking->ticket->event->name ?? '';
                                $booking->organizer = $booking->ticket->event->user->name ?? '';
                                return $booking;
                            });
                    } else {
                        $masterBooking->bookings = collect();
                    }
                })->map(function ($masterBooking) {
                    $masterBooking->is_deleted = $masterBooking->trashed();
                    $masterBooking->quantity = count($masterBooking->bookings);
                    return $masterBooking;
                });

                $normalBookings = AmusementBooking::withTrashed()
                    ->with(['ticket.event.user', 'user', 'attendee'])
                    ->where('agent_id', null)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get()
                    ->map(function ($booking) {
                        $booking->event_name = $booking->ticket->event->name ?? '';
                        $booking->organizer = $booking->ticket->event->user->name ?? '';
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


                $Masterbookings = AmusementMasterBooking::withTrashed()
                    ->where('agent_id', null)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get();
                $allBookingIds = [];
                $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $tickets, $startDate, $endDate) {
                    $bookingIds = $masterBooking->booking_id;
                    $bookingIds = is_string($bookingIds) && is_array(json_decode($bookingIds, true)) ? json_decode($bookingIds, true) : $bookingIds;

                    if (is_array($bookingIds)) {
                        $allBookingIds = array_merge($allBookingIds, $bookingIds);
                        $masterBooking->bookings = AmusementBooking::whereIn('id', $bookingIds)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->whereHas('ticket', function ($query) use ($tickets) {
                                $query->whereIn('id', $tickets);
                            })
                            ->with(['ticket.event.user', 'user'])
                            ->latest()
                            ->get()
                            ->map(function ($booking) {
                                $booking->event_name = $booking->ticket->event->name;
                                $booking->organizer = $booking->ticket->event->user->name;
                                return $booking;
                            });
                    } else {
                        $masterBooking->bookings = collect();
                    }
                })->map(function ($masterBooking) {
                    $masterBooking->is_deleted = $masterBooking->trashed();
                    $masterBooking->quantity = count($masterBooking->bookings);
                    return $masterBooking;
                });

                $normalBookings = AmusementBooking::withTrashed()
                    ->with(['ticket.event.user', 'user', 'attendee'])
                    ->where('agent_id', null)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereHas('ticket', function ($query) use ($tickets) {
                        $query->whereIn('id', $tickets);
                    })
                    ->latest()
                    ->get()
                    ->map(function ($booking) {
                        $booking->event_name = $booking->ticket->event->name;
                        $booking->organizer = $booking->ticket->event->user->name;
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
                    'bookings' => $Masterbookings,
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                // 'message' => 'Failed to retrieve bookings',
                'error' => $e->getMessage() . "on line" . $e->getLine(),
            ], 500);
        }
    }
}
