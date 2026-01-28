<?php

namespace App\Http\Controllers;

use App\Exports\SponsorBookingExport;
use App\Models\Balance;
use App\Models\Event;
use App\Models\SmsTemplate;
use App\Models\SponsorBooking;
use App\Models\SponsorMasterBooking;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsappApi;
use App\Services\SmsService;
use App\Services\WhatsappService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;


class SponsorBookingController extends Controller
{
    // List All Bookings
   public function list(Request $request, $id)
{
    try {
        $loggedInUser = Auth::user();
        $isAdmin = $loggedInUser->hasRole('Admin');
        $isOrganizer = $loggedInUser->hasRole('Organizer');
        $isSponsor = $loggedInUser->hasRole('Sponsor');

        // 🔹 Date filter
        if ($request->has('date')) {
            $dates = explode(',', $request->date);
            if (count($dates) === 1 || ($dates[0] === $dates[1])) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[0])->endOfDay();
            } elseif (count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            } else {
                return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
            }
        } else {
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        }

        // 🔹 Admin → બધા bookings
        if ($isAdmin) {
            $Masterbookings = SponsorMasterBooking::withTrashed()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest()
                ->get();

            $allBookingIds = [];
            $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $startDate, $endDate) {
                $bookingIds = $masterBooking->booking_id;

                if (is_array($bookingIds)) {
                    $allBookingIds = array_merge($allBookingIds, $bookingIds);
                    $masterBooking->bookings = SponsorBooking::withTrashed()
                        ->whereIn('id', $bookingIds)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->with(['ticket.event.user', 
                        'user:id,name,number,email,photo,reporting_user,company_name,designation',
                        'attendee:id,Seat_Name'])
                        ->latest()
                        ->get()
                        ->map(function ($booking) {
                            $booking->agent_name = $booking->sponsorUser->name ?? '';
                            $booking->event_name = $booking->ticket->event->name ?? '';
                            $booking->organizer = $booking->ticket->event->user->name ?? '';
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

            $normalBookings = SponsorBooking::withTrashed()
                ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation','attendee:id,Seat_Name'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest()
                ->get()
                ->map(function ($booking) {
                    $booking->agent_name = $booking->sponsorUser->name ?? '';
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
        }

        // 🔹 Organizer → ફક્ત પોતાના eventsના sponsors
        if ($isOrganizer) {
            $eventIds = Event::where('user_id', $loggedInUser->id)->pluck('id');
            $tickets = Ticket::whereIn('event_id', $eventIds)->pluck('id');

            $Masterbookings = SponsorMasterBooking::withTrashed()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest()
                ->get();

            $allBookingIds = [];
            $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $tickets, $startDate, $endDate) {
                $bookingIds = $masterBooking->booking_id;

                if (is_array($bookingIds)) {
                    $allBookingIds = array_merge($allBookingIds, $bookingIds);
                    $masterBooking->bookings = SponsorBooking::whereIn('id', $bookingIds)
                        ->whereIn('ticket_id', $tickets)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation', 'attendee:id,Seat_Name'])
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

            $normalBookings = SponsorBooking::withTrashed()
                ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation', 'attendee:id,Seat_Name'])
                ->whereIn('ticket_id', $tickets)
                ->whereBetween('created_at', [$startDate, $endDate])
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
                'bookings' => $sortedCombinedBookings,
            ], 200);
        }

        // 🔹 Sponsor → sponsor પોતાનાં + reporting_user bookings
        if ($isSponsor) {
            $reportingIds = User::where('reporting_user', $loggedInUser->id)->pluck('id')->toArray();
            $allSponsorIds = array_merge([$loggedInUser->id], $reportingIds);

            $Masterbookings = SponsorMasterBooking::withTrashed()
                ->whereIn('sponsor_id', $allSponsorIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest()
                ->get();

            $allBookingIds = [];
            $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $startDate, $endDate) {
                $bookingIds = $masterBooking->booking_id;

                if (is_array($bookingIds)) {
                    $allBookingIds = array_merge($allBookingIds, $bookingIds);
                    $masterBooking->bookings = SponsorBooking::whereIn('id', $bookingIds)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation'])
                        ->latest()
                        ->get();
                } else {
                    $masterBooking->bookings = collect();
                }
            })->map(function ($masterBooking) {
                $masterBooking->is_deleted = $masterBooking->trashed();
                $masterBooking->quantity = count($masterBooking->bookings);
                return $masterBooking;
            });

            $normalBookings = SponsorBooking::withTrashed()
                ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation', 'attendee:id,Seat_Name'])
                ->whereIn('sponsor_id', $allSponsorIds)
                ->whereBetween('created_at', [$startDate, $endDate])
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
                'bookings' => $sortedCombinedBookings,
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'Unauthorized access',
        ], 403);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'error' => $e->getMessage() . " on line " . $e->getLine(),
        ], 500);
    }
}


    //store booking
    public function store(Request $request, $id, SmsService $smsService, WhatsappService $whatsappService)
    {
        try {
            $user = auth()->user();
            if ($user->hasRole('Sponsor')) {
                $latestBalance = Balance::where('user_id', $user->id)
                    ->latest()
                    ->first();
                // return response()->json($latestBalance);

                if (!$latestBalance) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Insufficient Balance.'
                    ], 200);
                }

                $ticketAmount = $request->amount;

                if ($latestBalance->total_credits < $ticketAmount) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Not sufficient balance.'
                    ], 400);
                }
            }
            $bookings = [];
            $firstIteration = true;
            $attendees = $request->attendees ?? [];
            if (!is_array($attendees)) {
                $attendees = [];
            }
            $sessionId = $request->session_id;
            if (!$sessionId) {
                $getSession = $this->generateEncryptedSessionId();
                $sessionId = $getSession['original'];
            }
            if ($request->tickets['quantity'] > 0) {
                for ($i = 0; $i < $request->tickets['quantity']; $i++) {
                    $booking = new SponsorBooking();
                    $booking->ticket_id = $request->tickets['id'];
                    $booking->sponsor_id = $request->agent_id;
                    $booking->user_id = $request->user_id;
                    $booking->session_id = $sessionId;

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
                    $booking->load(['user', 'ticket.event.user.smsConfig']);
                    $bookings[] = $booking;

                    if ($i === 0 && $user->hasRole('Sponsor') && (int) $request->tickets['quantity'] === 1) {
                        // if ($i === 0 && $user->hasRole('Agent') && $request->tickets['quantity'] == 1) {
                        $newTotalCredits = $latestBalance->total_credits - $request->amount;

                        // Save new balance entry
                        $newBalance = new Balance();
                        $newBalance->user_id = $user->id;
                        $newBalance->total_credits = $newTotalCredits;
                        $newBalance->new_credit = $request->amount;
                        $newBalance->booking_id = $booking->id;
                        $newBalance->payment_method = 'cash';
                        $newBalance->payment_type = 'debit';
                        $newBalance->transaction_id = $this->generateTransactionId();
                        $newBalance->description = 'agentBooking';
                        $newBalance->save();
                    }

                    $orderId = $booking->token ?? '';
                    $shortLinksms = "getyourticket.in/t/{$orderId}";
                    $whatsappTemplate = WhatsappApi::where('title', 'Sponsor Booking')->first();
                    $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

                    $eventDateTime = str_replace(',', ' |', $event->date_range) . ' | ' . $event->start_time . ' - ' . $event->end_time;
                    $mediaurl =  $event->thumbnail;

                    $data = (object) [
                        'name' => $booking->name,
                        'number' => $booking->number,
                        'templateName' => 'Sponsor Booking Template',
                        'whatsappTemplateData' => $whatsappTemplateName,
                        'mediaurl' => $mediaurl,
                        'orderId' => $orderId,
                        'shortLink' => $shortLinksms,
                        'insta_whts_url' =>$event->insta_whts_url ?? 'helloinsta',
                        'values' => [
                            $booking->name,
                            $booking->number,
                            $event->name,
                            $request->tickets['quantity'],
                            $ticket->name,
                            $event->address,
                            $eventDateTime,
                            $event->whts_note ?? 'hello',
                        ],
                        'replacements' => [
                            ':C_Name' => $booking->name,
                            ':T_QTY' => $request->tickets['quantity'],
                            ':Ticket_Name' => $ticket->name,
                            ':Event_Name' => $event->name,
                            ':Event_Date' => $eventDateTime,
                            ':S_Link' => $shortLinksms,
                        ]
                    ];

                    if ($i === 0 && $request->tickets['quantity'] == 1) {
                        $smsService->send($data);
                        $whatsappService->send($data);
                    }
                }
            }

            return response()->json(['status' => true, 'message' => 'Tickets Booked Successfully', 'bookings' => $bookings, 'session_id' => $sessionId], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to book tickets', 'error' => $e->getMessage()], 500);
        }
    }

    //store master booking
    public function sponsorMaster(Request $request, $id ,SmsService $smsService, WhatsappService $whatsappService)
    {
        try {
            $user = auth()->user();
            if ($user->hasRole('Sponsor')) {
                $latestBalance = Balance::where('user_id', $user->id)->latest()->first();

                if (!$latestBalance) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Balance not found for the Sponsor.'
                    ], 400);
                }

                $totalAmount = $request->amount;

                if ($latestBalance->total_credits < $totalAmount) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Not sufficient balance.'
                    ], 400);
                }
            }
            $agentMasterBooking = new SponsorMasterBooking();
            $bookingIds = $request->input('bookingIds');

            if (is_string($bookingIds)) {
                $bookingIds = json_decode($bookingIds, true);

                if (is_null($bookingIds)) {
                    $bookingIds = explode(',', trim($bookingIds, '[]'));
                }
            }
            $sessionId = $request->session_id;
            // Save the master booking details
            $agentMasterBooking->booking_id = $bookingIds;
            $agentMasterBooking->user_id = $request->user_id;
            $agentMasterBooking->sponsor_id = $request->agent_id;
            $agentMasterBooking->session_id = $sessionId;
            // $agentMasterBooking->order_id = $this->generateRandomCode(); // Generate an order ID
            $agentMasterBooking->order_id = $this->generateHexadecimalCode(); // Generate an order ID
            $agentMasterBooking->amount = $request->amount;
            $agentMasterBooking->discount = $request->discount ?? 0;
            $agentMasterBooking->payment_method = $request->payment_method;
            $agentMasterBooking->save();

            if ($user->hasRole('Sponser')) {
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
            $agentMasterBookingDetails = SponsorMasterBooking::where('order_id', $agentMasterBooking->order_id)->with('user')->first();

            if ($agentMasterBookingDetails) {
                $bookingIds = $agentMasterBookingDetails->booking_id;
                if (is_array($bookingIds)) {
                    $agentMasterBookingDetails->bookings = SponsorBooking::whereIn('id', $bookingIds)->with('ticket.event.user.smsConfig')->get();
                } else {
                    $agentMasterBookingDetails->bookings = collect();
                }
            }

            if (
                $agentMasterBookingDetails &&
                isset($agentMasterBookingDetails->bookings[0])
            ) {
                $booking = $agentMasterBookingDetails->bookings[0];
                $ticket = $booking->ticket;
                $event = $ticket->event;

                $whatsappTemplate = WhatsappApi::where('title', 'Sponsor Booking')->first();
                $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

                $orderId = $agentMasterBooking->order_id ?? '';
                $shortLinksms = "getyourticket.in/t/{$orderId}";

                $eventDateTime = str_replace(',', ' |', $event->date_range) . ' | ' . $event->start_time . ' - ' . $event->end_time;
                $mediaurl = $event->thumbnail; // ✅ use correct event thumbnail

                $data = (object)[
                    'name' => $booking->name,
                    'number' => $booking->number,
                    'templateName' => 'Sponsor Booking Template',
                    'whatsappTemplateData' => $whatsappTemplateName,
                    'shortLink' => $shortLinksms,
                    'orderId' => $orderId,
                    'mediaurl' => $mediaurl,
                    'insta_whts_url' =>$event->insta_whts_url ?? 'helloinsta',
                    'values' => [
                        $booking->name,
                        $booking->number,
                        $event->name,
                        count($agentMasterBookingDetails->bookings),
                        $ticket->name,
                        $event->address,
                        $eventDateTime,
                        $event->whts_note ?? 'hello',
                    ],

                    'replacements' => [
                        ':C_Name' => $booking->name,
                        ':T_QTY' => count($agentMasterBookingDetails->bookings),
                        ':Ticket_Name' => $ticket->name,
                        ':Event_Name' => $event->name,
                        ':Event_Date' => $event->date_range,
                        ':S_Link' => $shortLinksms,
                    ]
                ];

                $smsService->send($data);
                $whatsappService->send($data);
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
                        'email' => $user->email
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
        $Masterbookings = SponsorMasterBooking::where('order_id', $token)->first();

        if ($Masterbookings) {
            $bookingIds = is_array($Masterbookings->booking_id)
                ? $Masterbookings->booking_id
                : json_decode($Masterbookings->booking_id, true);

            if (!empty($bookingIds) && is_array($bookingIds)) {
                SponsorBooking::whereIn('id', $bookingIds)->delete(); // Delete related bookings
            }

            $Masterbookings->delete(); // Delete master booking

            return response()->json([
                'status' => true,
                'message' => 'Master Booking and related bookings deleted successfully'
            ], 200);
        }

        // If not master, try deleting normal booking
        $normalBooking = SponsorBooking::where('token', $token)->first();

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
        $Masterbooking = SponsorMasterBooking::withTrashed()->where('order_id', $token)->first();

        if ($Masterbooking) {
            // Restore master booking
            $Masterbooking->restore();

            // Get related booking IDs and restore them
            $bookingIds = is_array($Masterbooking->booking_id)
                ? $Masterbooking->booking_id
                : json_decode($Masterbooking->booking_id, true);

            if (!empty($bookingIds) && is_array($bookingIds)) {
                SponsorBooking::withTrashed()->whereIn('id', $bookingIds)->restore();
            }

            return response()->json([
                'status' => true,
                'message' => 'Master Booking and related bookings restored successfully'
            ], 200);
        }

        // Else try restoring a normal booking
        $normalBooking = SponsorBooking::withTrashed()->where('token', $token)->first();

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
    // public function export(Request $request)
    // {
    //     $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

    //     $query = SponsorBooking::withTrashed()
    //         ->with(['ticket.event.user', 'user', 'sponsorUser']);

    //     if ($dates) {
    //         if (count($dates) === 1) {
    //             $singleDate = Carbon::parse($dates[0])->toDateString();
    //             $query->whereDate('created_at', $singleDate);
    //         } elseif (count($dates) === 2) {
    //             $startDate = Carbon::parse($dates[0])->startOfDay();
    //             $endDate = Carbon::parse($dates[1])->endOfDay();
    //             $query->whereBetween('created_at', [$startDate, $endDate]);
    //         }
    //     }

    //     $bookings = $query->latest()->get();

    //     $grouped = $bookings->groupBy('session_id')->map(function ($group) {
    //         $first = $group->first();
    //         $first->event_name = $first?->ticket?->event?->name ?? 'N/A';
    //         $first->organizer = $first?->ticket?->event?->user?->name ?? 'N/A';
    //         $first->is_deleted = $first?->trashed();
    //         $first->quantity = $group->count(); // Number of bookings in that session
    //         return $first;
    //     })->values();

    //     return Excel::download(new SponsorBookingExport($grouped), 'SponsorBooking_export.xlsx');
    // }
 public function export(Request $request)
{
    $user = auth()->user(); // logged-in user
    $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

    // SponsorBooking query
    $query = SponsorBooking::withTrashed()
        ->with(['ticket.event.user', 'user', 'sponsorUser']);

    // Organizer filter
    if ($user->hasRole('Organizer')) {
        $eventIds = Event::where('user_id', $user->id)->pluck('id');
        $ticketIds = Ticket::whereIn('event_id', $eventIds)->pluck('id');
        $query->whereIn('ticket_id', $ticketIds);
    }

    // Date filter
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

    $bookings = $query->latest()->get();

    $grouped = $bookings->groupBy('session_id')->map(function ($group) use ($dates) {
        $first = $group->first();

        // Step 1: check SponsorMasterBooking
        $masterQuery = SponsorMasterBooking::where('session_id', $first->session_id);
        if ($dates) {
            if (count($dates) === 1) {
                $singleDate = Carbon::parse($dates[0])->toDateString();
                $masterQuery->whereDate('created_at', $singleDate);
            } elseif (count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
                $masterQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
        }

        $masterBooking = $masterQuery->first();

        // Step 2: assign token
        $first->token = $masterBooking ? $masterBooking->order_id : $first->token;

        $first->event_name = $first?->ticket?->event?->name ?? 'N/A';
        $first->organizer = $first?->ticket?->event?->user?->name ?? 'N/A';
        $first->is_deleted = $first?->trashed();
        $first->quantity = $group->count(); // Number of bookings in that session

        return $first;
    })->values();

    return Excel::download(new SponsorBookingExport($grouped), 'SponsorBooking_export.xlsx');
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

    private function generateEncryptedSessionId()
    {
        // Generate a random session ID
        $originalSessionId = Str::random(32);
        // Encrypt it
        $encryptedSessionId = encrypt($originalSessionId);

        return [
            'original' => $originalSessionId,
            'encrypted' => $encryptedSessionId
        ];
    }
}
