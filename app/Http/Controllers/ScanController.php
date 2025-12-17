<?php

namespace App\Http\Controllers;

use App\Models\AccessArea;
use App\Models\AccreditationBooking;
use App\Models\AccreditationMasterBooking;
use App\Models\Agent;
use App\Models\AgentEvent;
use App\Models\AgentMaster;
use App\Models\AmusementAgentBooking;
use App\Models\AmusementAgentMasterBooking;
use App\Models\AmusementBooking;
use App\Models\AmusementMasterBooking;
use App\Models\AmusementPosBooking;
use App\Models\Attndy;
use App\Models\Booking;
use App\Models\Category;
use App\Models\ComplimentaryBookings;
use App\Models\CorporateBooking;
use App\Models\CorporateUser;
use App\Models\ExhibitionBooking;
use App\Models\MasterBooking;
use App\Models\PosBooking;
use App\Models\ScanHistory;
use App\Models\SponsorBooking;
use App\Models\SponsorMasterBooking;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class ScanController extends Controller
{
    public function verifyTicket(Request $request, $orderId)
    {
        $loggedInUser = Auth::user();

        if ($loggedInUser->hasRole('Scanner')) {

            if (!$request->user_id || !$request->event_id) {
                return response()->json([
                    "status" => false,
                    "message" => "user_id and event_id are required for scanner"
                ], 400);
            }

            // Check agent_events permission
            $assigned = AgentEvent::where('user_id', $request->user_id)
                ->where('event_id', $request->event_id)
                ->exists();

            if (!$assigned) {
                return response()->json([
                    "status" => false,
                    "message" => "You are not assigned for this event"
                ], 403);
            }
        }


        try {
            $ticketRelations = [
                'ticket' => function ($q) {
                    $q->select('id', 'event_id', 'name');
                },
                'ticket.event' => function ($q) {
                    $q->select('id', 'event_key', 'name', 'category');
                },
                'ticket.event.category' => function ($q) {
                    $q->select('id', 'title', 'attendy_required');
                },
            ];

            $booking = Booking::with($ticketRelations + ['attendee', 'user:id,name,number,email'])
                ->where('event_id', $request->event_id)
                ->where('token', $orderId)
                ->first();

            $posBooking = PosBooking::with($ticketRelations + ['attendee'])
                ->where('event_id', $request->event_id)
                ->where('token', $orderId)
                ->first();

            $complimentaryBookings = ComplimentaryBookings::with($ticketRelations + ['attendee'])
                ->where('event_id', $request->event_id)
                ->where('token', $orderId)
                ->first();

            $masterBookings = MasterBooking::where('order_id', $orderId)->first();

            $sessionId = Str::uuid()->toString();
            $table = null;
            $bookingId = null;

            /*
            |--------------------------------------------------------------------------
            | CASE 1: POS BOOKING — CHECK FOR SET BOOKING FIRST
            |--------------------------------------------------------------------------
            */
            if ($posBooking) {

                $event = $posBooking->ticket->event;

                // Check if this is a SET booking (has set_id)
                if (!empty($posBooking->set_id)) {

                    // Fetch all POS bookings with same set_id
                    $setBookings = PosBooking::with($ticketRelations + ['attendee', 'user'])
                        ->where('set_id', $posBooking->set_id)
                        ->get();

                    if ($setBookings->count() > 1) {

                        // STORE SESSION FOR SET BOOKING
                        $bookingIds = $setBookings->pluck('id')->toArray();

                        Cache::put("scan_session:$sessionId", [
                            'order_id' => $orderId,
                            'booking_id' => implode(',', $bookingIds),
                            'table_name' => "pos_bookings_set",
                        ], now()->addMinutes(1));

                        // Total quantity
                        $totalQuantity = $setBookings->sum('quantity');

                        // Attendees array
                        $attendees = $setBookings->map(fn($b) => $b->attendee)->filter()->values();

                        // Tickets array (without event)
                        $tickets = $setBookings->filter(fn($b) => $b->ticket)->groupBy('ticket_id')->map(function ($group) {
                            $ticketData = $group->first()->ticket->toArray();
                            unset($ticketData['event']);
                            $ticketData['quantity'] = $group->sum('quantity') ?: $group->count();
                            return $ticketData;
                        })->values();

                        // Build set data
                        $setData = [
                            'set_id' => $posBooking->set_id,
                            'token' => $posBooking->token,
                            'total_bookings' => $setBookings->count(),
                            'quantity' => $totalQuantity,
                            'total_amount' => $setBookings->sum('total_amount'),
                            'discount' => $setBookings->sum('discount'),
                            'booking_date' => $setBookings->pluck('created_at')->filter()->first() ?? $posBooking->created_at,
                            'status' => $posBooking->status,
                            'name' => $setBookings->pluck('name')->filter()->first() ?? $posBooking->name,
                            'number' => $setBookings->pluck('number')->filter()->first() ?? $posBooking->number,
                            'email' => $setBookings->pluck('email')->filter()->first() ?? $posBooking->email,
                            'payment_method' => $posBooking->payment_method,
                            'attendees' => $attendees,
                            'tickets' => $tickets,
                            'user' => optional($setBookings->first())->user,
                        ];

                        return response()->json([
                            "status" => true,
                            "session_id" => $sessionId,
                            "is_master" => false,
                            "is_set" => true,
                            "bookings" => $setData,
                            "attendee_required" => $event->category->attendy_required ?? false,
                            "event" => $event,
                            "type" => "POS",
                        ]);
                    }
                }

                // SINGLE POS BOOKING (no set_id or only one in set)
                $table = "pos_bookings";
                $bookingId = $posBooking->id;

                Cache::put("scan_session:$sessionId", [
                    'order_id' => $orderId,
                    'booking_id' => $bookingId,
                    'table_name' => $table,
                ], now()->addMinutes(1));

                $ticketData = $posBooking->ticket->toArray();
                unset($ticketData['event']);

                return response()->json([
                    "status" => true,
                    "session_id" => $sessionId,
                    "is_master" => false,
                    "is_set" => false,
                    "bookings" => [
                        "id" => $posBooking->id,
                        "name" => $posBooking->name,
                        "number" => $posBooking->number,
                        "token" => $posBooking->token,
                        "quantity" => $posBooking->quantity ?? 1,
                        "status" => $posBooking->status,
                        "booking_date" => $posBooking->created_at,
                        "ticket_id" => $posBooking->ticket_id,
                        "attendee_id" => $posBooking->attendee_id,
                        "total_amount" => $posBooking->total_amount,
                        "attendee" => $posBooking->attendee,
                        "tickets" => $ticketData,
                        "user" => $posBooking->user,
                    ],
                    "attendee_required" => $event->category->attendy_required ?? false,
                    "event" => $event,
                    "type" => "POS",
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | CASE 2: NORMAL / COMPLIMENTARY — SINGLE BOOKING
            |--------------------------------------------------------------------------
            */
            if ($booking || $complimentaryBookings) {

                $b = $booking ?? $complimentaryBookings;
                $event = $b->ticket->event;

                if ($booking) {
                    $table = "bookings";
                } else {
                    $table = "complimentary_bookings";
                }

                $bookingId = $b->id;

                Cache::put("scan_session:$sessionId", [
                    'order_id' => $orderId,
                    'booking_id' => $bookingId,
                    'table_name' => $table,
                ], now()->addMinutes(1));

                // ✅ Remove event from ticket to avoid duplication
                $ticketData = $b->ticket->toArray();
                unset($ticketData['event']);

                return response()->json([
                    "status" => true,
                    "session_id" => $sessionId,
                    "is_master" => false,
                    "is_set" => false,
                    "bookings" => [
                        "id" => $b->id,
                        "name" => $b->name,
                        "number" => $b->number,
                        "token" => $b->token,
                        "quantity" => $b->quantity ?? 1,
                        "status" => $b->status,
                        "ticket_id" => $b->ticket_id,
                        "attendee_id" => $b->attendee_id,
                        "total_amount" => $b->total_amount,
                        "booking_date" => $b->created_at,
                        "attendee" => $b->attendee,
                        "tickets" => $ticketData,
                        "user" => $b->user ?? null,
                    ],
                    "attendee_required" => $event->category->attendy_required ?? false,
                    "event" => $event,
                    "type" => "Online",
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | CASE 3: MASTER BOOKING — MULTIPLE BOOKINGS
            |--------------------------------------------------------------------------
            */
            if ($masterBookings) {

                $bookingIds = $masterBookings->booking_id;

                // Fetch all linked bookings
                $relatedBookings = Booking::with($ticketRelations + ['attendee', 'user:id,name,number,email'])
                    ->whereIn('id', $bookingIds)
                    ->get();

                if ($relatedBookings->isEmpty()) {
                    return response()->json([
                        "status" => false,
                        "message" => "No bookings found"
                    ], 404);
                }

                // STORE SESSION FOR MASTER BOOKING
                Cache::put("scan_session:$sessionId", [
                    'order_id' => $orderId,
                    'booking_id' => implode(',', $bookingIds),
                    'table_name' => "master_bookings",
                ], now()->addMinutes(1));

                // Event from first booking
                $event = $relatedBookings->first()->ticket->event ?? null;

                $totalQuantity = $relatedBookings->count();

                // Attendees array
                $attendees = $relatedBookings->map(function ($b) {
                    return $b->attendee;
                })->filter()->values();

                // Tickets array (without event)
                $tickets = $relatedBookings->map(function ($b) {
                    $ticketData = $b->ticket->toArray();
                    unset($ticketData['event']);
                    return $ticketData;
                })->filter()->unique('id')->values();

                // Clean master booking data
                $masterData = $masterBookings->toArray();
                unset($masterData['bookings']);
                $masterData['quantity'] = $totalQuantity;
                $masterData['attendees'] = $attendees;
                $masterData['user'] = optional($relatedBookings->first())->user;
                $masterData['booking_date'] = $relatedBookings->pluck('created_at')->filter()->first();
                $masterData['tickets'] = $tickets;

                return response()->json([
                    "status" => true,
                    "session_id" => $sessionId,
                    "is_master" => true,
                    "is_set" => false,
                    "bookings" => $masterData,
                    "attendee_required" => $event->category->attendy_required ?? false,
                    "event" => $event,
                    "type" => "Online",
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | INVALID ORDER ID
            |--------------------------------------------------------------------------
            */
            return response()->json([
                "status" => false,
                "message" => "Invalid Ticket / Order ID"
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                "status" => false,
                "message" => "An error occurred: " . $e->getMessage()
            ], 500);
        }
    }

    public function ChekIn($orderId)
    {
        $booking = Booking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $agentBooking = Agent::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $AccreditationBooking = AccreditationBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $SponsorBooking = SponsorBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $amusementAgentBooking = AmusementAgentBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $ExhibitionBooking = ExhibitionBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $amusementBooking = AmusementBooking::where('token', $orderId)->with(['ticket.event.user', 'attendee'])->first();
        $posBooking = PosBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $corporateBooking = CorporateBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $amusementPosBooking = AmusementPosBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $complimentaryBookings = ComplimentaryBookings::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $masterBookings = MasterBooking::where('order_id', $orderId)->first();
        $amusementMasterBookings = AmusementMasterBooking::where('order_id', $orderId)->first();
        $agentMasterBookings = AgentMaster::where('order_id', $orderId)->first();
        $AccreditationMasterBooking = AccreditationMasterBooking::where('order_id', $orderId)->first();
        $SponsorMasterBooking = SponsorMasterBooking::where('order_id', $orderId)->first();
        $amusementAgentMasterBookings = AmusementAgentMasterBooking::where('order_id', $orderId)->first();
        $today = Carbon::now()->toDateTimeString();

        $eventData = $this->eventCheck($booking, $agentBooking, $posBooking, $corporateBooking, $complimentaryBookings, $masterBookings, $agentMasterBookings, $ExhibitionBooking, $amusementBooking, $amusementMasterBookings, $amusementAgentBooking, $amusementAgentMasterBookings, $amusementPosBooking, $AccreditationBooking, $AccreditationMasterBooking, $SponsorBooking, $SponsorMasterBooking);
        $organizer = $eventData['organizer'];
        $relatedBookings = $eventData['relatedBookings'];
        $event = $eventData['event'];
        // return response()->json($posBooking);
        if ($booking) {
            if ($event->multi_scan) {
                $booking->status = false;
            } else {
                $booking->status = true;
            }
            $booking->is_scaned = true;
            $booking->save();
            // $history = $this->logScanHistory($booking->user_id, auth()->id(), $booking->token, 'online');
            return response()->json([
                'status' => true,
                'bookings' => $booking->status
            ], 200);
        } else if ($amusementBooking) {
            if ($event->multi_scan) {
                $amusementBooking->status = false;
            } else {
                $amusementBooking->status = true;
            }
            $amusementBooking->is_scaned = true;
            $amusementBooking->save();
            // $history = $this->logScanHistory($booking->user_id, auth()->id(), $booking->token, 'amusementBooking');
            return response()->json([
                'status' => true,
                'bookings' => $amusementBooking->status
            ], 200);
        } else if ($agentBooking) {
            if ($event->multi_scan) {
                $agentBooking->status = false;
            } else {
                $agentBooking->status = true;
            }
            // $agentBooking->status = true;
            $agentBooking->is_scaned = true;
            $agentBooking->save();
            //$history = $this->logScanHistory($agentBooking->user_id, auth()->id(), $agentBooking->token, 'agentBooking');
            return response()->json([
                'status' => true,
                'bookings' => $agentBooking->status
            ], 200);
        } else if ($AccreditationBooking) {
            if ($event->multi_scan) {
                $AccreditationBooking->status = false;
            } else {
                $AccreditationBooking->status = true;
            }
            // $agentBooking->status = true;
            $AccreditationBooking->is_scaned = true;
            $AccreditationBooking->save();
            // $history = $this->logScanHistory($AccreditationBooking->user_id, auth()->id(), $AccreditationBooking->token, 'AccreditationBooking');
            return response()->json([
                'status' => true,
                'bookings' => $AccreditationBooking->status
            ], 200);
        } else if ($SponsorBooking) {
            if ($event->multi_scan) {
                $SponsorBooking->status = false;
            } else {
                $SponsorBooking->status = true;
            }
            // $agentBooking->status = true;
            $SponsorBooking->is_scaned = true;
            $SponsorBooking->save();
            //$history = $this->logScanHistory($SponsorBooking->user_id, auth()->id(), $SponsorBooking->token, 'SponsorBooking');

            return response()->json([
                'status' => true,
                'bookings' => $SponsorBooking->status
            ], 200);
        } else if ($amusementAgentBooking) {
            if ($event->multi_scan) {
                $amusementAgentBooking->status = false;
            } else {
                $amusementAgentBooking->status = true;
            }
            // $amusementAgentBooking->status = true;
            $amusementAgentBooking->is_scaned = true;
            $amusementAgentBooking->save();
            //$history = $this->logScanHistory($amusementAgentBooking->user_id, auth()->id(), $amusementAgentBooking->token, 'amusementAgentBooking');

            return response()->json([
                'status' => true,
                'bookings' => $amusementAgentBooking->status
            ], 200);
        } else if ($ExhibitionBooking) {
            if ($event->multi_scan) {
                $ExhibitionBooking->status = false;
            } else {
                $ExhibitionBooking->status = true;
            }
            // $ExhibitionBooking->status = true;
            $ExhibitionBooking->is_scaned = true;
            $ExhibitionBooking->save();
            //$history = $this->logScanHistory($ExhibitionBooking->user_id, auth()->id(), $ExhibitionBooking->token, 'ExhibitionBooking');

            return response()->json([
                'status' => true,
                'bookings' => $ExhibitionBooking->status
            ], 200);
        } else if ($posBooking) {
            if ($event->multi_scan) {
                $posBooking->status = false;
            } else {
                $posBooking->status = true;
            }
            $posBooking->is_scaned = true;
            $posBooking->status = true;
            $posBooking->save();
            //$history = $this->logScanHistory($posBooking->user_id, auth()->id(), $posBooking->token, 'posBooking');

            return response()->json([
                'status' => true,
                'bookings' => $posBooking->status
            ], 200);
        } else if ($corporateBooking) {
            if ($event->multi_scan) {
                $corporateBooking->status = false;
            } else {
                $corporateBooking->status = true;
            }
            $corporateBooking->is_scaned = true;
            $corporateBooking->status = true;
            $corporateBooking->save();
            // $history = $this->logScanHistory($corporateBooking->user_id, auth()->id(), $corporateBooking->token, 'corporateBooking');

            return response()->json([
                'status' => true,
                'bookings' => $corporateBooking->status
            ], 200);
        } else if ($amusementPosBooking) {
            if ($event->multi_scan) {
                $amusementPosBooking->status = false;
            } else {
                $amusementPosBooking->status = true;
            }
            $amusementPosBooking->is_scaned = true;
            $amusementPosBooking->save();
            //$history = $this->logScanHistory($amusementPosBooking->user_id, auth()->id(), $amusementPosBooking->token, 'amusementPosBooking');

            return response()->json([
                'status' => true,
                'bookings' => $amusementPosBooking->status
            ], 200);
        } else if ($complimentaryBookings) {
            if ($event->multi_scan) {
                $complimentaryBookings->status = false;
            } else {
                $complimentaryBookings->status = true;
            }
            $complimentaryBookings->is_scaned = true;
            $complimentaryBookings->save();
            //$history = $this->logScanHistory($complimentaryBookings->user_id, auth()->id(), $complimentaryBookings->token, 'complimentaryBookings');

            return response()->json([
                'status' => true,
                'bookings' => $complimentaryBookings->status
            ], 200);
        } else if ($masterBookings) {
            $bookingIds = $masterBookings->booking_id;
            $relatedBookings = Booking::with('ticket.event.user')->where('status', 0)->whereIn('id', $bookingIds)->get();

            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
                //$history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $masterBookings->order_id, 'masterBookings');
            }
            return response()->json([
                'status' => true,
            ], 200);
        } else if ($amusementMasterBookings) {
            $bookingIds = $amusementMasterBookings->booking_id;
            $relatedBookings = AmusementBooking::with('ticket.event.user')->where('status', 0)->whereIn('id', $bookingIds)->get();

            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
                // $history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $amusementMasterBookings->order_id, 'amusementMasterBookings');
            }
            return response()->json([
                'status' => true,
            ], 200);
        } else if ($agentMasterBookings) {
            $agent = $agentMasterBookings->booking_id;
            $relatedBookings = Agent::with('ticket.event.user')->where('status', 0)->whereIn('id', $agent)->get();
            // return response()->json([
            //     'data' => $relatedBookings,
            // ], 200);
            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
                // $history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $agentMasterBookings->order_id, 'agentMasterBookings');
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        } else if ($AccreditationMasterBooking) {
            $agent = $AccreditationMasterBooking->booking_id;
            $relatedBookings = AccreditationBooking::with('ticket.event.user')->where('status', 0)->whereIn('id', $agent)->get();

            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
                //$history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $AccreditationMasterBooking->order_id, 'AccreditationMasterBooking');
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        } else if ($SponsorMasterBooking) {
            $agent = $SponsorMasterBooking->booking_id;
            $relatedBookings = SponsorBooking::with('ticket.event.user')->where('status', 0)->whereIn('id', $agent)->get();

            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
                //$history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $SponsorMasterBooking->order_id, 'SponsorMasterBooking');
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        } else if ($amusementAgentMasterBookings) {
            $agent = $amusementAgentMasterBookings->booking_id;
            $relatedBookings = AmusementAgentBooking::with('ticket.event.user')->where('status', 0)->whereIn('id', $agent)->get();

            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
                //  $history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $amusementAgentMasterBookings->order_idn, 'amusementAgentMasterBookings');
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        }
    }

    private function eventCheck($booking, $agentBooking, $posBooking, $corporateBooking, $complimentaryBookings, $masterBookings, $agentMasterBookings, $ExhibitionBooking, $amusementBooking, $amusementMasterBookings, $amusementAgentBooking, $amusementAgentMasterBookings, $amusementPosBooking, $AccreditationBooking, $AccreditationMasterBooking, $SponsorBooking, $SponsorMasterBooking)
    {
        $organizer = null;
        $relatedBookings = collect();
        $event = null;

        if ($booking) {
            $organizer = $booking->ticket->event->user->id;
            $relatedBookings = collect([$booking]);
            $event = $booking->ticket->event;
        } elseif ($amusementBooking) {
            $organizer = $amusementBooking->ticket->event->user->id;
            $relatedBookings = collect([$amusementBooking]);
            $event = $amusementBooking->ticket->event;
            $event->load('category');
        } elseif ($agentBooking) {
            $organizer = $agentBooking->ticket->event->user->id;
            $relatedBookings = collect([$agentBooking]);
            $event = $agentBooking->ticket->event;
        } elseif ($AccreditationBooking) {
            $organizer = $AccreditationBooking->ticket->event->user->id;
            $relatedBookings = collect([$AccreditationBooking]);
            $event = $AccreditationBooking->ticket->event;
        } elseif ($SponsorBooking) {
            $organizer = $SponsorBooking->ticket->event->user->id;
            $relatedBookings = collect([$SponsorBooking]);
            $event = $SponsorBooking->ticket->event;
        } elseif ($amusementAgentBooking) {
            $organizer = $amusementAgentBooking->ticket->event->user->id;
            $relatedBookings = collect([$amusementAgentBooking]);
            $event = $amusementAgentBooking->ticket->event;
        } elseif ($ExhibitionBooking) {
            $organizer = $ExhibitionBooking->ticket->event->user->id;
            $relatedBookings = collect([$ExhibitionBooking]);
            $event = $ExhibitionBooking->ticket->event;
        } elseif ($posBooking) {
            $organizer = $posBooking->ticket->event->user->id;
            $relatedBookings = collect([$posBooking]);
            $event = $posBooking->ticket->event;
        } elseif ($corporateBooking) {
            $organizer = $corporateBooking->ticket->event->user->id;
            $relatedBookings = collect([$corporateBooking]);
            $event = $corporateBooking->ticket->event;
        } elseif ($amusementPosBooking) {
            $organizer = $amusementPosBooking->ticket->event->user->id;
            $relatedBookings = collect([$amusementPosBooking]);
            $event = $amusementPosBooking->ticket->event;
        } elseif ($complimentaryBookings) {
            $organizer = $complimentaryBookings->ticket->event->user->id;
            $relatedBookings = collect([$complimentaryBookings]);
            $event = $complimentaryBookings->ticket->event;
        } elseif ($masterBookings) {
            $bookingIds = $masterBookings->booking_id;
            $relatedBookings = Booking::with('ticket.event.user', 'attendee')->whereIn('id', $bookingIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        } elseif ($amusementMasterBookings) {
            $bookingIds = $amusementMasterBookings->booking_id;
            $relatedBookings = AmusementMasterBooking::with('ticket.event.user', 'attendee')->whereIn('id', $bookingIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        } elseif ($agentMasterBookings) {
            $agentIds = $agentMasterBookings->booking_id;
            $relatedBookings = Agent::with('ticket.event.user', 'attendee')->whereIn('id', $agentIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        } elseif ($AccreditationMasterBooking) {
            $agentIds = $AccreditationMasterBooking->booking_id;
            $relatedBookings = AccreditationBooking::with('ticket.event.user', 'attendee')->whereIn('id', $agentIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        } elseif ($SponsorMasterBooking) {
            $agentIds = $SponsorMasterBooking->booking_id;
            $relatedBookings = SponsorBooking::with('ticket.event.user', 'attendee')->whereIn('id', $agentIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        } elseif ($amusementAgentMasterBookings) {
            $agentIds = $amusementAgentMasterBookings->booking_id;
            $relatedBookings = AmusementAgentBooking::with('ticket.event.user', 'attendee')->whereIn('id', $agentIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        }

        return compact('organizer', 'relatedBookings', 'event');
    }

    private function logScanHistory($userId, $scannerId, $tokenId, $bookingSource = null)
    {
        $now = now()->toDateTimeString();

        $query = ScanHistory::where('user_id', $userId)
            ->where('scanner_id', $scannerId);

        if ($bookingSource !== null) {
            $query->where('booking_source', $bookingSource);
        }

        $history = $query->first();

        if ($history) {
            // Append scan time
            $times = json_decode($history->scan_time ?? '[]', true) ?: [];
            $times[] = $now;
            $history->scan_time = json_encode($times);

            // Ensure token is always an array
            $tokens = json_decode($history->token ?? '[]', true) ?: [];
            if (!in_array($tokenId, $tokens)) {
                $tokens[] = $tokenId;
            }
            $history->token = json_encode($tokens);

            $history->count += 1;
            $history->save();
        } else {
            // Create new entry with token array
            $history = ScanHistory::create([
                'user_id' => $userId,
                'scanner_id' => $scannerId,
                'token' => json_encode([$tokenId]), // Store as JSON array
                'booking_source' => $bookingSource,
                'scan_time' => json_encode([$now]),
                'count' => 1,
            ]);
        }

        return $history;
    }

    // private function logScanHistory($userId, $scannerId, $tokenId, $bookingSource = null)
    // {
    //     $now = now()->toDateTimeString();

    //     $query = ScanHistory::where('user_id', $userId)
    //         ->where('scanner_id', $scannerId);

    //     if ($bookingSource !== null) {
    //         $query->where('booking_source', $bookingSource);
    //     }

    //     $history = $query->first();


    //     if ($history) {
    //         $times = json_decode($history->scan_time ?? '[]', true);
    //         $times[] = $now;


    //         $history->scan_time = json_encode($times);
    //         $history->count += 1;
    //         $history->token = $tokenId;
    //         $history->save();
    //     } else {
    //         $history = ScanHistory::create([
    //             'user_id' => $userId,
    //             'scanner_id' => $scannerId,
    //             'token' => $tokenId,
    //             'booking_source' => $bookingSource,
    //             'scan_time' => json_encode([$now]),
    //             'count' => 1,
    //         ]);
    //     }

    //     return $history;
    // }

    // public function attendeesChekIn($orderId)
    // {
    //     try {
    //         $attendee = Attndy::where('token', $orderId)->with('event.user')->first();
    //         if (!$attendee) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No attendee found with this token'
    //             ], 404);
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'bookings' => $attendee
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'An error occurred: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function attendeesChekIn($orderId)
    {
        try {
            // First: Try to fetch from Attndy table
            $attendee = Attndy::where('token', $orderId)->with('event.user')->first();

            // If not found in Attndy, try in CorporateBooking
            if (!$attendee) {
                $corporate = CorporateUser::where('token', $orderId)->first();

                if (!$corporate) {
                    return response()->json([
                        'status' => false,
                        'message' => 'No attendee found with this token'
                    ], 404);
                }

                return response()->json([
                    'status' => true,
                    'bookings' => $corporate,
                    'source' => 'corporate'
                ], 200);
            }

            return response()->json([
                'status' => true,
                'bookings' => $attendee,
                'source' => 'attndy'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function attendeesVerify($orderId)
    {
        try {
            // First try in Attndy table
            $attendee = Attndy::where('token', $orderId)->with('event.user')->first();

            if ($attendee) {
                $attendee->status = true;
                $attendee->save();

                //$this->logScanHistory($attendee->user_id, auth()->id(), $attendee->token, 'attendee');

                return response()->json([
                    'status' => true,
                    'bookings' => $attendee,
                    'source' => 'attndy'
                ], 200);
            }

            // If not found in Attndy, try CorporateBooking table
            $corporate = CorporateUser::where('token', $orderId)->with('event.user')->first();

            if ($corporate) {
                $corporate->status = true;
                $corporate->save();

                // $this->logScanHistory($corporate->user_id, auth()->id(), $corporate->token, 'corporate');

                return response()->json([
                    'status' => true,
                    'bookings' => $corporate,
                    'source' => 'corporate'
                ], 200);
            }

            // If not found in both
            return response()->json([
                'status' => false,
                'message' => 'No attendee found with this token'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }


    // public function attendeesVerify($orderId)
    // {
    //     try {
    //         $attendee = Attndy::where('token', $orderId)->with('event.user')->first();
    //         if (!$attendee) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No attendee found with this token'
    //             ], 404);
    //         }
    //         $attendee->status = true;

    //         $attendee->save();

    //         $history = $this->logScanHistory($attendee->user_id, auth()->id(), $attendee->token, 'attendee');


    //         return response()->json([
    //             'status' => true,
    //             'bookings' => $attendee
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'An error occurred: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function getScanHistories(Request $request)
    {
        try {
            if ($request->has('date')) {
                $dates = explode(',', $request->date);

                if (count($dates) === 1 || ($dates[0] === $dates[1])) {
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[0])->endOfDay();
                } elseif (count($dates) === 2) {
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[1])->endOfDay();
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid date format'
                    ], 400);
                }
            } else {
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
            }

            $user = auth()->user();

            $histories = ScanHistory::with(['user:id,name', 'scanner:id,name'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $histories
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
