<?php

namespace App\Http\Controllers;

use App\Exports\AgentBookingExport;
use App\Models\Agent;
use App\Models\AgentMaster;
use App\Models\Balance;
use App\Models\Booking;
use App\Models\Event;
use App\Models\MasterBooking;
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
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{

    public function list(Request $request, $id, PermissionService $permissionService)
    {
        try {
            $loggedInUser = Auth::user();
            $permissions = $permissionService->check(['View Username', 'View Contact']);
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();

            if ($request->has('date')) {
                $dates = explode(',', $request->date);
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = count($dates) === 2
                    ? Carbon::parse($dates[1])->endOfDay()
                    : Carbon::parse($dates[0])->endOfDay();
            }

            // Get organizer's ticket IDs if needed
            $organizerTicketIds = null;
            if ($loggedInUser->hasRole('Organizer')) {
                $eventIds = Event::where('user_id', $loggedInUser->id)->pluck('id');
                $organizerTicketIds = Ticket::whereIn('event_id', $eventIds)->pluck('id');
            }

            // Get master bookings
            $masterQuery = AgentMaster::withTrashed()
                ->whereBetween('created_at', [$startDate, $endDate]);

            if ($loggedInUser->hasRole('Agent')) {
                $masterQuery->where('agent_id', $loggedInUser->id);
            }

            $Masterbookings = $masterQuery->get();

            // Filter master bookings for organizer after fetching
            if ($loggedInUser->hasRole('Organizer') && $organizerTicketIds) {
                $Masterbookings = $Masterbookings->filter(function ($master) use ($organizerTicketIds) {
                    if (is_array($master->booking_id)) {
                        // Check if any booking in this master belongs to organizer's events
                        $bookingIds = $master->booking_id;
                        $belongsToOrganizer = Agent::whereIn('id', $bookingIds)
                            ->whereIn('ticket_id', $organizerTicketIds)
                            ->exists();
                        return $belongsToOrganizer;
                    }
                    return false;
                });
            }

            $allBookingIds = $Masterbookings->flatMap(function ($master) {
                return is_array($master->booking_id) ? $master->booking_id : [];
            })->unique()->values();

            // Pre-fetch all agent bookings for master bookings
            $agentBookingsCollection = Agent::withTrashed()
                ->whereIn('id', $allBookingIds)
                ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation', 'agentUser:id,name', 'attendee:id,Seat_Name'])
                ->get()->keyBy('id');

            // Transform master bookings
            $Masterbookings = $Masterbookings->map(function ($master) use ($agentBookingsCollection) {
                $ids = is_array($master->booking_id) ? $master->booking_id : [];
                $bookings = collect($ids)->map(function ($id) use ($agentBookingsCollection) {
                    return $agentBookingsCollection->get($id);
                })->filter()->values();

                // Get first booking for master info
                $firstBooking = $bookings->first();

                if ($firstBooking) {
                    $master->agent_name = $firstBooking->agentUser->name ?? '';
                    $master->event_name = $firstBooking->ticket->event->name ?? '';
                    $master->organizer = $firstBooking->ticket->event->user->name ?? '';
                }

                $master->bookings = $bookings;
                $master->is_deleted = $master->trashed();
                $master->quantity = $bookings->count();
                $master->is_master = true; // Flag to identify master booking
                return $master;
            });

            // Get normal bookings (single bookings that are NOT part of master bookings)
            $normalQuery = Agent::withTrashed()
                ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation', 'agentUser:id,name', 'attendee:id,Seat_Name'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotIn('id', $allBookingIds); // Exclude master booking IDs

            if ($loggedInUser->hasRole('Agent')) {
                $normalQuery->where('agent_id', $loggedInUser->id);
            } elseif ($loggedInUser->hasRole('Organizer') && $organizerTicketIds) {
                $normalQuery->whereIn('ticket_id', $organizerTicketIds);
            }
            // Admin sees all - no additional filtering needed

            $normalBookings = $normalQuery->get()
                ->map(function ($booking) {
                    $booking->agent_name = $booking->agentUser->name ?? '';
                    $booking->event_name = $booking->ticket->event->name ?? '';
                    $booking->organizer = $booking->ticket->event->user->name ?? '';
                    $booking->quantity = 1;
                    $booking->is_deleted = $booking->trashed();
                    $booking->is_master = false; // Flag for single booking
                    return $booking;
                })->values();

            $combinedBookings = $Masterbookings->concat($normalBookings)
                ->sortByDesc('created_at')
                ->values();

            return response()->json([
                'status' => true,
                'bookings' => $combinedBookings,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage() . " on line " . $e->getLine(),
            ], 500);
        }
    }


    //store agent
    public function store(Request $request, $id, SmsService $smsService, WhatsappService $whatsappService)
    {
        try {
            $user = auth()->user();
            if ($user->hasRole('Agent')) {
                $latestBalance = Balance::where('user_id', $user->id)
                    ->latest()
                    ->first();
                // return response()->json($latestBalance);

                if (!$latestBalance) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Balance not found for the agent.'
                    ], 200);
                }

                $ticketAmount = $request->amount;

                if ($latestBalance->total_credits < $ticketAmount) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Not sufficient amount in balance.'
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
                    $booking = new Agent();
                    $booking->ticket_id = $request->tickets['id'];
                    $booking->batch_id = Ticket::where('id', $request->tickets['id'])->value('batch_id');
                    $booking->agent_id = $request->agent_id;
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

                    if ($i === 0 && $user->hasRole('Agent') && (int) $request->tickets['quantity'] === 1) {
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

                    $whatsappTemplate = WhatsappApi::where('title', 'Agent Booking')->first();
                    $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

                    $orderId = $booking->token ?? '';
                    $shortLink =  $orderId;
                    $shortLinksms = "getyourticket.in/t/{$orderId}";

                    $dates = explode(',', $event->date_range);
                    $formattedDates = [];
                    foreach ($dates as $date) {
                        $formattedDates[] = \Carbon\Carbon::parse($date)->format('d-m-Y');
                    }
                    $dateRangeFormatted = implode(' | ', $formattedDates);

                    $eventDateTime = $dateRangeFormatted . ' | ' . $event->start_time . ' - ' . $event->end_time;

                    //$eventDateTime = str_replace(',', ' |', $event->date_range) . ' | ' . $event->start_time . ' - ' . $event->end_time;

                    $mediaurl =  $event->thumbnail;
                    $data = (object) [
                        'name' => $booking->name,
                        'number' => $booking->number,
                        'templateName' => 'Agent Booking Template',
                        'orderId' => $orderId,
                        'whatsappTemplateData' => $whatsappTemplateName,
                        'shortLink' => $shortLink,
                        'insta_whts_url' => $event->insta_whts_url ?? 'helloinsta',
                        'mediaurl' => $mediaurl,
                        'values' => [
                            (string) ($booking->name ?? 'Guest'),
                            (string) ($booking->number ?? '0000000000'),
                            (string) ($event->name ?? 'Event'),
                            (string) ($request->tickets['quantity'] ?? '1'),
                            (string) ($ticket->name ?? 'Ticket'),
                            (string) ($event->address ?? 'Venue'),
                            // $shortLink,
                            (string) ($eventDateTime ?? 'DateTime'),
                            (string) ($event->whts_note ?? 'hello'),
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
                    //return $data;
                    if ($i === 0  && $request->tickets['quantity'] == 1) {
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


    public function agentMaster(Request $request, $id, SmsService $smsService, WhatsappService $whatsappService)
    {
        try {
            $user = auth()->user();
            if ($user->hasRole('Agent')) {
                $latestBalance = Balance::where('user_id', $user->id)->latest()->first();

                if (!$latestBalance) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Balance not found for the agent.'
                    ], 400);
                }

                $totalAmount = $request->amount;

                if ($latestBalance->total_credits < $totalAmount) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Not sufficient amount in balance.'
                    ], 400);
                }
            }

            $agentMasterBooking = new AgentMaster();
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
            $agentMasterBooking->agent_id = $request->agent_id;
            $agentMasterBooking->session_id = $sessionId;

            // $agentMasterBooking->order_id = $this->generateRandomCode(); // Generate an order ID
            $agentMasterBooking->order_id = $this->generateHexadecimalCode(); // Generate an order ID
            $agentMasterBooking->amount = $request->amount;
            $agentMasterBooking->discount = $request->discount;
            $agentMasterBooking->payment_method = $request->payment_method;
            $agentMasterBooking->save();

            if ($user->hasRole('Agent')) {
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
            $agentMasterBookingDetails = AgentMaster::where('order_id', $agentMasterBooking->order_id)->with('user')->first();

            if ($agentMasterBookingDetails) {
                $bookingIds = $agentMasterBookingDetails->booking_id;
                if (is_array($bookingIds)) {
                    $agentMasterBookingDetails->bookings = Agent::whereIn('id', $bookingIds)->with('ticket.event.user.smsConfig')->get();
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

                $whatsappTemplate = WhatsappApi::where('title', 'Agent Booking')->first();
                $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

                $orderId = $agentMasterBooking->order_id ?? '';
                $shortLink = $orderId;
                $shortLinksms = "getyourticket.in/t/{$orderId}";

                $dates = explode(',', $event->date_range);
                $formattedDates = [];
                foreach ($dates as $date) {
                    $formattedDates[] = \Carbon\Carbon::parse($date)->format('d-m-Y');
                }
                $dateRangeFormatted = implode(' | ', $formattedDates);

                $eventDateTime = $dateRangeFormatted . ' | ' . $event->start_time . ' - ' . $event->end_time;

                //$eventDateTime = str_replace(',', ' |', $event->date_range) . ' | ' . $event->start_time . ' - ' . $event->end_time;
                $mediaurl = $event->thumbnail; // ✅ use correct event thumbnail

                $data = (object)[
                    'name' => $booking->name,
                    'number' => $booking->number,
                    'templateName' => 'Agent Booking Template',
                    'orderId' => $orderId,
                    'whatsappTemplateData' => $whatsappTemplateName,
                    'shortLink' => $shortLink,
                    'insta_whts_url' => $event->insta_whts_url ?? 'helloinsta',
                    'mediaurl' => $mediaurl,
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
                        ':Event_Date' => $eventDateTime,
                        ':S_Link' => $shortLinksms,
                    ]
                ];

                $smsService->send($data);
                $whatsappService->send($data);
            }

            return response()->json([
                'status' => true,
                'message' => 'Agent Master Ticket Created Successfully',
                'booking' => $agentMasterBookingDetails
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create agent master booking', 'error' => $e->getMessage()], 500);
        }
    }


    //generateRandomCode
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

    public function export(Request $request)
    {
        $loggedInUser = Auth::user();
        $dates = $request->input('date') ? explode(',', $request->input('date')) : [Carbon::today()->format('Y-m-d')];

        $query = Agent::withTrashed()
            ->with(['ticket.event.user', 'user', 'agentUser']);

        // Role-based filtering
        if ($loggedInUser->hasRole('Admin')) {
            // Admin can see all bookings
        } elseif ($loggedInUser->hasRole('Organizer')) {
            // Organizer can only see bookings from their agents
            $query->whereHas('ticket.event', function ($q) use ($loggedInUser) {
                $q->where('user_id', $loggedInUser->id);
            });
        } elseif ($loggedInUser->hasRole('Agent')) {
            // Agent can only see their own bookings
            $query->where('agent_id', $loggedInUser->id);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Apply date filters
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

        $grouped = $bookings->groupBy('session_id')->map(function ($group) {
            $first = $group->first();
            $token = null;

            // Check if AgentMaster has record for this session
            $masterRecord = AgentMaster::where('session_id', $first->session_id)->first();
            if ($masterRecord) {
                $token = $masterRecord->order_id ?? '';
            } else {
                $token = $first->token ?? '';
            }

            // Determine status: Active if not deleted, Disabled if deleted
            $status = $first?->trashed() ? 'Disabled' : 'Active';

            return [
                'event_name' => $first?->ticket?->event?->name ?? 'N/A',
                'organizer' => $first?->ticket?->event?->user?->name ?? 'N/A',
                'agent_name' => $first?->agentUser?->name ?? 'N/A',
                'user_name' => $first?->user?->name ?? 'No User',
                'booking_number' => $first?->number ?? '',
                'ticket_name' => $first?->ticket?->name ?? '',
                'quantity' => $group->count(), // total tickets booked in this session
                'base_amount' => $group->sum('base_amount') ?? 0,
                'discount' => $group->sum('discount') ?? 0,
                'amount' => $group->sum('amount') ?? 0,
                'mode' => $first?->payment_method ?? 'N/A',
                'status' => $status,
                'token' => $token ?? '',
                'booking_date' => $first?->created_at?->format('d-m-Y | h:i:s A') ?? 'N/A',
            ];
        })->values();

        // return Excel::download(new AgentBookingExport($bookings), 'AgentBooking_export.xlsx');
        return Excel::download(new AgentBookingExport($grouped), 'AgentBooking_export.xlsx');
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
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'photo' => $user->photo,
                        'doc' => $user->doc,
                        'company_name' => $user->company_name,
                        'designation' => $user->designation,
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
        $Masterbookings = AgentMaster::where('order_id', $token)->first();

        if ($Masterbookings) {
            $bookingIds = is_array($Masterbookings->booking_id)
                ? $Masterbookings->booking_id
                : json_decode($Masterbookings->booking_id, true);

            if (!empty($bookingIds) && is_array($bookingIds)) {
                Agent::whereIn('id', $bookingIds)->delete(); // Delete related bookings
            }

            $Masterbookings->delete(); // Delete master booking

            return response()->json([
                'status' => true,
                'message' => 'Master Booking and related bookings deleted successfully'
            ], 200);
        }

        // If not master, try deleting normal booking
        $normalBooking = Agent::where('token', $token)->first();

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
        $Masterbooking = AgentMaster::withTrashed()->where('order_id', $token)->first();

        if ($Masterbooking) {
            // Restore master booking
            $Masterbooking->restore();

            // Get related booking IDs and restore them
            $bookingIds = is_array($Masterbooking->booking_id)
                ? $Masterbooking->booking_id
                : json_decode($Masterbooking->booking_id, true);

            if (!empty($bookingIds) && is_array($bookingIds)) {
                Agent::withTrashed()->whereIn('id', $bookingIds)->restore();
            }

            return response()->json([
                'status' => true,
                'message' => 'Master Booking and related bookings restored successfully'
            ], 200);
        }

        // Else try restoring a normal booking
        $normalBooking = Agent::withTrashed()->where('token', $token)->first();

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

    private function generateTransactionId()
    {
        return strtoupper(bin2hex(random_bytes(10))); // Generates a 20-character alphanumeric ID
    }




    public function ganerateCard($token)
    {
        // $order_id = Cache::get($token);
        $cacheKey = 'token_order_' . $token;
        $order_id = Cache::get($cacheKey);


        if (!$order_id) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired token'
            ], 403);
        }

        // Common helper function
        $getUserDetails = function ($userId) {
            $user = User::find($userId);
            return $user ? [
                'name' => $user->name ?? null,
                'number' => $user->number ?? null,
                'email' => $user->email ?? null,
            ] : null;
        };

        // ===================== Agent Master =====================
        $master = AgentMaster::withTrashed()->where('order_id', $order_id)->first();

        if ($master) {
            $ids = is_string($master->booking_id) ? json_decode($master->booking_id, true) : $master->booking_id;
            $bookings = Agent::withTrashed()->whereIn('id', $ids)->with('attendee', 'ticket.event')->get();
        }
        // ===================== Booking Master =====================
        elseif ($master = MasterBooking::withTrashed()->where('order_id', $order_id)->first()) {
            $ids = is_string($master->booking_id) ? json_decode($master->booking_id, true) : $master->booking_id;
            $bookings = Booking::withTrashed()->whereIn('id', $ids)->with('attendee', 'ticket.event')->get();
        }
        // ===================== SponsorBooking Master =====================
        elseif ($master = SponsorMasterBooking::withTrashed()->where('order_id', $order_id)->first()) {
            $ids = is_string($master->booking_id) ? json_decode($master->booking_id, true) : $master->booking_id;
            $bookings = SponsorBooking::withTrashed()->whereIn('id', $ids)->with('attendee', 'ticket.event')->get();
        }

        if (!empty($bookings ?? null)) {
            $userArray = [];
            $firstCardUrl = null;

            foreach ($bookings as $index => $booking) {
                if ($index === 0) {
                    $firstCardUrl = $booking->ticket->background_image ?? null;
                }

                $attendee = $booking->attendee;
                $userArray[] = [
                    'token' => $booking->token,
                    'booking_date' => $booking->created_at->format('d-m-Y'),
                    'amount' => $booking->amount ?? 0,
                    'attendee' => $attendee ? [
                        'name' => $attendee->Name ?? '',
                        'Seat_Name' => $attendee->Seat_Name ?? '',
                        'email' => $attendee->Email ?? '',
                        'phone' => $attendee->Mo ?? '',
                        'photo' => $attendee->Photo ?? null,
                    ] : null,
                ];
            }

            return response()->json([
                'status' => true,
                'type' => 'master',
                'card_url' => $firstCardUrl,
                'ticket' => [
                    'price' => $bookings[0]->ticket->price ?? null,
                    'amount' => $bookings[0]->amount ?? null,
                    'name' => $bookings[0]->ticket->name ?? null,
                    'currency' => $bookings[0]->ticket->currency ?? null,
                ],
                'event' => [
                    'name' => $bookings[0]->ticket->event->name ?? null,
                    'country' => $bookings[0]->ticket->event->country ?? null,
                    'state' => $bookings[0]->ticket->event->state ?? null,
                    'city' => $bookings[0]->ticket->event->city ?? null,
                    'date_range' => $bookings[0]->ticket->event->date_range ?? null,
                    'start_time' => $bookings[0]->ticket->event->start_time ?? null,
                    'entry_time' => $bookings[0]->ticket->event->entry_time ?? null,
                    'end_time' => $bookings[0]->ticket->event->end_time ?? null,
                    'address' => $bookings[0]->ticket->event->address ?? null,
                    'ticket_terms' => $bookings[0]->ticket->event->ticket_terms ?? null,
                ],
                'tokendata' => $token,
                'data' => $userArray,
                'users' => $getUserDetails($master->user_id)
            ]);
        }

        // ===================== Normal Agent =====================
        $booking = Agent::withTrashed()->with('attendee', 'ticket.event')->where('token', $order_id)->first();
        // ===================== Normal Booking =====================
        if (!$booking) {
            $booking = Booking::withTrashed()->with('attendee', 'ticket.event')->where('token', $order_id)->first();
        }
        // ===================== Normal Sponsor =====================
        if (!$booking) {
            $booking = SponsorBooking::withTrashed()->with('attendee', 'ticket.event')->where('token', $order_id)->first();
        }

        if ($booking) {
            $attendee = $booking->attendee;
            return response()->json([
                'status' => true,
                'type' => 'normal',
                'card_url' => $booking->ticket->background_image ?? null,
                'ticket' => [
                    'price' => $booking->ticket->price ?? null,
                    'amount' => $booking->amount ?? null,
                    'name' => $booking->ticket->name ?? null,
                    'currency' => $booking->ticket->currency ?? null,
                ],
                'event' => [
                    'name' => $booking->ticket->event->name ?? null,
                    'country' => $booking->ticket->event->country ?? null,
                    'state' => $booking->ticket->event->state ?? null,
                    'city' => $booking->ticket->event->city ?? null,
                    'date_range' => $booking->ticket->event->date_range ?? null,
                    'start_time' => $booking->ticket->event->start_time ?? null,
                    'entry_time' => $booking->ticket->event->entry_time ?? null,
                    'end_time' => $booking->ticket->event->end_time ?? null,
                    'address' => $booking->ticket->event->address ?? null,
                    'ticket_terms' => $booking->ticket->event->ticket_terms ?? null,
                ],
                'tokendata' => $token,
                'data' => [[
                    'token' => $booking->token,
                    'booking_date' => $booking->created_at->format('d-m-Y'),
                    'amount' => $booking->amount ?? 0,
                    'attendee' => $attendee ? [
                        'name' => $attendee->Name ?? '',
                        'email' => $attendee->Email ?? '',
                        'Seat_Name' => $attendee->Seat_Name ?? '',
                        'phone' => $attendee->Mo ?? '',
                        'photo' => $attendee->Photo ?? null,
                    ] : null,
                ]],
                'users' => $getUserDetails($booking->user_id)
            ]);
        }

        // ===================== Not Found =====================
        return response()->json(['status' => false, 'message' => 'Booking not found.'], 404);
    }

    public function generate(Request $request, $order_id)
    {
        $orderId = $order_id;

        if (!$orderId) {
            return response()->json(['status' => false, 'message' => 'Missing order_id'], 400);
        }

        // ✅ Check existence in all relevant tables
        $existsInAgent = Agent::where('token', $orderId)->exists();
        $existsInAgentMaster = AgentMaster::where('order_id', $orderId)->exists();
        $existsInBooking = Booking::where('token', $orderId)->exists();
        $existsInBookingMaster = MasterBooking::where('order_id', $orderId)->exists();
        $existsInSponsor = SponsorBooking::where('token', $orderId)->exists();
        $existsInSponsorMaster = SponsorMasterBooking::where('order_id', $orderId)->exists();

        if (
            !$existsInAgent &&
            !$existsInAgentMaster &&
            !$existsInBooking &&
            !$existsInBookingMaster &&
            !$existsInSponsor &&
            !$existsInSponsorMaster
        ) {
            return response()->json(['status' => false, 'message' => 'Invalid order_id'], 403);
        }

        // ✅ Allowed domain check
        $allowedDomain = env('ALLOWED_DOMAIN', 'https://getyourticket.in/');
        $referer = $request->headers->get('referer');

        if (!$referer || strpos($referer, $allowedDomain) !== 0) {
            // return response()->json(['status' => false, 'message' => 'Forbidden'], 200);
        }

        // ✅ Token generation and caching
        $cacheKeyByOrder = 'token_for_order_' . $orderId;

        if (Cache::has($cacheKeyByOrder)) {
            $token = Cache::get($cacheKeyByOrder);

            // ✅ Even if token already exists, store reverse mapping again
            Cache::put('token_order_' . $token, $orderId, now()->addMinutes(05));
        } else {
            $token = Str::random(32);
            Cache::put($cacheKeyByOrder, $token, now()->addMinutes(05));
            Cache::put('token_order_' . $token, $orderId, now()->addMinutes(05));
        }

        $group = !$existsInSponsorMaster;

        return response()->json([
            'status' => true,
            'order_id' => $orderId,
            'token' => $token,
            'group' => $group
        ]);
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

    public function checkSplitUser(Request $request)
    {

        $number = $request->number;

        $user = User::where('number', $number)->first();

        if (!$user && $request->email) {
            $emailExists = User::where('email', $request->email)->exists();

            if ($emailExists) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Email already exists with another user'
                ], 422);
            }
        }
        if (!$user) {
            $user = User::create([
                'number' => $number,
                'name' => $request->name ?? 'Guest User',
                'email'  => $request->email ?? ($number . '@gyt.com'),
                'password' => Hash::make($number),
                'status'   => 1,

            ]);
        }

        $cacheKey = 'user_hash_' . $user->id;

        if ($request->hash_key) {

            $cachedHash = Cache::get($cacheKey);

            if (!$cachedHash || $cachedHash !== $request->hash_key) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid or expired hash key'
                ], 401);
            }

            Cache::forget($cacheKey);
        }

        $hashKey = hash('sha256', $user->id . '|' . Str::random(10));

        Cache::put(
            $cacheKey,
            $hashKey,
            now()->addMinutes(10)
        );

        return response()->json([
            'status' => true,
            'message' => 'User fetched successfully',
            'data' => [
                'user_id' => $user->id,
                'hash_key' => $hashKey,
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'photo' => $user->photo ?? '',
                'doc' => $user->doc ?? '',
                'company_name' => $user->company_name ?? '',
                'designation' => $user->designation ?? '',
            ]
        ]);
    }

    // public function splitBookingTransfer(Request $request, SmsService $smsService, WhatsappService $whatsappService)
    // {
    //     $request->validate([
    //         'order_id'   => 'required',
    //         'is_master'  => 'required|boolean',
    //         'qty'        => 'required|integer|min:1',
    //         'user_id'    => 'required|integer',
    //         'hash_key'   => 'required|string',
    //     ]);

    //     DB::beginTransaction();

    //     try {

    //         //  Validate User and Hash Key
    //         $user = User::find($request->user_id);

    //         if (!$user) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'User not found'
    //             ], 404);
    //         }

    //         $cacheKey = 'user_hash_' . $user->id;
    //         $cachedHash = Cache::get($cacheKey);

    //         if (!$cachedHash || $cachedHash !== $request->hash_key) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Invalid or expired hash key'
    //             ], 401);
    //         }

    //         $masterMap = [
    //             'AgentMasterBooking'   => AgentMaster::class,
    //             'SponsorMasterBooking' => SponsorMasterBooking::class,
    //         ];

    //         if ($request->is_master == true) {
    //             // Fetch Master Booking

    //             if (!isset($masterMap[$request->type])) {
    //                 return response()->json(['status' => false, 'message' => 'Invalid master type'], 422);
    //             }

    //             $master = $masterMap[$request->type]::where('order_id', $request->order_id)->first();

    //             if (!$master) {
    //                 return response()->json(['status' => false, 'message' => 'Master booking not found'], 404);
    //             }

    //             $bookingIds = $master->booking_id;

    //             if (!is_array($bookingIds)) {
    //                 $bookingIds = json_decode($bookingIds, true);
    //             }

    //             if (!is_array($bookingIds)) {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => 'Invalid booking data'
    //                 ], 422);
    //             }


    //             $availableQty = count($bookingIds);

    //             if ($availableQty < $request->qty) {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => 'Not enough quantity to transfer'
    //                 ], 422);
    //             }

    //             $selectedBookingIds = array_slice($bookingIds, 0, $request->qty);
    //             // Fetch Single Bookings
    //             $bookings = Agent::whereIn('id', $selectedBookingIds)->get();

    //             if ($bookings->count() < $request->qty) {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => 'Insufficient bookings available'
    //                 ], 422);
    //             }
    //             $getSession = $this->generateEncryptedSessionId();
    //             $sessionId = $getSession['original'];

    //             // Update Single Bookings
    //             foreach ($bookings as $booking) {
    //                 $booking->update([
    //                     'token' => $this->generateHexadecimalCode(),
    //                     'session_id' => $sessionId,
    //                     'user_id'  => $user->id,
    //                     'name'     => $user->name,
    //                     'number'   => $user->number,
    //                     'email'    => $user->email,
    //                     'transferred_status'    => 1,
    //                     'assigned_by'    => auth()->id(),
    //                     'assigned_to'    => $user->id,
    //                 ]);
    //             }


    //             $oldBookingIds = is_array($master->booking_id)
    //                 ? $master->booking_id
    //                 : json_decode($master->booking_id, true);

    //             // Remove transferred IDs
    //             $remainingIds = array_values(array_diff($oldBookingIds, $selectedBookingIds));

    //             // 🔹 NEW AgentMaster entry (Transferred tickets)
    //             AgentMaster::create([
    //                 'order_id'   => $this->generateHexadecimalCode(),
    //                 'user_id'    => $user->id,
    //                 'agent_id'    => auth()->id(),
    //                 'amount'    => $master->amount,
    //                 'payment_method'    => $master->payment_method,
    //                 'session_id' => $sessionId,
    //                 'booking_id' => $selectedBookingIds,
    //                 'transferred_status'    => 1,
    //                 'assigned_by'    => auth()->id(),
    //                 'assigned_to'    => $user->id,
    //             ]);

    //             // 🔹 UPDATE OLD AgentMaster (Remaining tickets)
    //             $master->update([
    //                 'booking_id' =>  $remainingIds,
    //             ]);
    //         } else {


    //             if ($request->type === 'Agent') {
    //                 $booking = Agent::where('token', $request->order_id)
    //                     ->where('transferred_status', 0)
    //                     ->first();
    //             } elseif ($request->type === 'SponsorBooking') {
    //                 $master = SponsorBooking::where('order_id', $request->order_id)->first();
    //                 if (!$master) {
    //                     return response()->json(['status' => false, 'message' => 'Sponsor  not found'], 404);
    //                 }


    //                 $booking = Agent::where('token', $request->order_id)
    //                     ->where('transferred_status', 0)
    //                     ->first();

    //                 if (!$booking) {
    //                     return response()->json([
    //                         'status' => false,
    //                         'message' => 'Booking not found or already transferred'
    //                     ], 404);
    //                 }

    //                 // Generate session
    //                 $getSession = $this->generateEncryptedSessionId();
    //                 $sessionId = $getSession['original'];

    //                 // Update Agent booking only
    //                 $booking->update([
    //                     'token' => $this->generateHexadecimalCode(),
    //                     'session_id' => $sessionId,
    //                     'user_id' => $user->id,
    //                     'name' => $user->name,
    //                     'number' => $user->number,
    //                     'email' => $user->email,
    //                     'transferred_status' => 1,
    //                     'assigned_by' => auth()->id(),
    //                     'assigned_to' => $user->id,
    //                 ]);

    //                 $booking->load('ticket.event');
    //                 $ticket = $booking->ticket;
    //                 $event  = $ticket->event;

    //                 // Assigned TO
    //                 $this->sendSmsWhatsapp(
    //                     'to',
    //                     $booking->name,
    //                     $booking->number,
    //                     $booking,
    //                     $booking->ticket,
    //                     $booking->ticket?->event,
    //                     1,
    //                     $booking->token,
    //                     $smsService,
    //                     $whatsappService
    //                 );

    //                 // Assigned BY
    //                 $sender = auth()->user();
    //                 $this->sendSmsWhatsapp(
    //                     'from',
    //                     $sender->name,
    //                     $sender->number,
    //                     $booking,
    //                     $booking->ticket,
    //                     $booking->ticket?->event,
    //                     1,
    //                     $booking->token,
    //                     $smsService,
    //                     $whatsappService
    //                 );
    //             }

    //             DB::commit();

    //             return response()->json([
    //                 'status' => true,
    //                 'message' => 'Booking transferred successfully'
    //             ]);
    //         }
    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         return response()->json([
    //             'status' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    public function splitBookingTransfer(Request $request, SmsService $smsService, WhatsappService $whatsappService)
    {
        $request->validate([
            'order_id'   => 'required',
            'is_master'  => 'required|boolean',
            'qty'        => 'required|integer|min:1',
            'user_id'    => 'required|integer',
            'hash_key'   => 'required|string',
            'type'       => 'required|string',
        ]);

        DB::beginTransaction();

        try {

            // Validate User
            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'User not found'], 404);
            }

            // Validate hash
            $cacheKey   = 'user_hash_' . $user->id;
            $cachedHash = Cache::get($cacheKey);

            if (!$cachedHash || $cachedHash !== $request->hash_key) {
                return response()->json(['status' => false, 'message' => 'Invalid or expired hash key'], 401);
            }

            // Master mapping
            $masterMap = [
                'AgentMasterBooking'   => AgentMaster::class,
                'SponsorMasterBooking' => SponsorMasterBooking::class,
            ];

            // Single booking mapping
            $singleBookingMap = [
                'AgentMasterBooking'   => Agent::class,
                'SponsorMasterBooking' => SponsorBooking::class,
            ];

            /* ===========================
            MASTER BOOKING TRANSFER
        ============================ */
            if ($request->is_master == true) {

                if (!isset($masterMap[$request->type])) {
                    return response()->json(['status' => false, 'message' => 'Invalid master type'], 422);
                }

                $master = $masterMap[$request->type]::where('order_id', $request->order_id)->first();
                if (!$master) {
                    return response()->json(['status' => false, 'message' => 'Master booking not found'], 404);
                }

                $bookingIds = is_array($master->booking_id)
                    ? $master->booking_id
                    : json_decode($master->booking_id, true);

                if (!is_array($bookingIds)) {
                    return response()->json(['status' => false, 'message' => 'Invalid booking data'], 422);
                }

                if (count($bookingIds) < $request->qty) {
                    return response()->json(['status' => false, 'message' => 'Not enough quantity to transfer'], 422);
                }

                $selectedBookingIds = array_slice($bookingIds, 0, $request->qty);

                // ✅ Correct table selection
                $bookingModel = $singleBookingMap[$request->type];

                $bookings = $bookingModel::whereIn('id', $selectedBookingIds)
                    ->where('transferred_status', 0)
                    ->get();

                if ($bookings->count() < $request->qty) {
                    return response()->json(['status' => false, 'message' => 'Insufficient bookings available'], 422);
                }

                $getSession = $this->generateEncryptedSessionId();
                $sessionId  = $getSession['original'];

                foreach ($bookings as $booking) {
                    $booking->update([
                        'token' => $this->generateHexadecimalCode(),
                        'session_id' => $sessionId,
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'number' => $user->number,
                        'email' => $user->email,
                        'transferred_status' => 1,
                        'assigned_by' => auth()->id(),
                        'assigned_to' => $user->id,
                    ]);
                }


                // Remaining IDs
                $remainingIds = array_values(array_diff($bookingIds, $selectedBookingIds));

                // ✅ Create NEW master of SAME TYPE
                $masterMap[$request->type]::create([
                    'order_id' => $this->generateHexadecimalCode(),
                    'user_id' => $user->id,
                    'agent_id' => auth()->id(),
                    'amount' => $master->amount,
                    'payment_method' => $master->payment_method,
                    'session_id' => $sessionId,
                    'booking_id' => $selectedBookingIds,
                    'transferred_status' => 1,
                    'assigned_by' => auth()->id(),
                    'assigned_to' => $user->id,
                ]);

                // Update old master
                $master->update(['booking_id' => $remainingIds]);

                /* =========================
       SMS / WHATSAPP (MASTER)
    ========================== */

                $sampleBooking = $bookings->first();
                $sampleBooking->load('ticket.event');

                $sender = auth()->user();

                // TO → AUTH USER (TOTAL QTY)
                $this->sendSmsWhatsapp(
                    'to',
                    $sender->name,
                    $sender->number,
                    $booking->name,
                    $booking->number,
                    $sampleBooking,
                    $sampleBooking->ticket,
                    $sampleBooking->ticket?->event,
                    $request->qty,          // ✅ TOTAL QTY
                    $request->order_id,
                    $smsService,
                    $whatsappService
                );

                // FROM → RECEIVER USER
                $this->sendSmsWhatsapp(
                    'from',
                    $sender->name,
                    $sender->number,
                    $booking->name,
                    $booking->number,
                    $sampleBooking,
                    $sampleBooking->ticket,
                    $sampleBooking->ticket?->event,
                    $request->qty,
                    $request->order_id,
                    $smsService,
                    $whatsappService
                );
            }

            /* ===========================
            SINGLE BOOKING TRANSFER
        ============================ */ else {

                if ($request->type === 'Agent') {

                    $booking = Agent::where('token', $request->order_id)
                        ->where('transferred_status', 0)
                        ->first();
                } elseif ($request->type === 'SponsorBooking') {

                    $booking = SponsorBooking::where('token', $request->order_id)
                        ->where('transferred_status', 0)
                        ->first();
                }

                if (!$booking) {
                    return response()->json(['status' => false, 'message' => 'Booking not found'], 404);
                }

                $getSession = $this->generateEncryptedSessionId();
                $sessionId  = $getSession['original'];

                $booking->update([
                    'token' => $this->generateHexadecimalCode(),
                    'session_id' => $sessionId,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'number' => $user->number,
                    'email' => $user->email,
                    'transferred_status' => 1,
                    'assigned_by' => auth()->id(),
                    'assigned_to' => $user->id,
                ]);

                $booking->load('ticket.event');

                // SMS / WhatsApp

                $sender = auth()->user();
                $this->sendSmsWhatsapp(
                    'to',
                    $sender->name,
                    $sender->number,
                    $booking->name,
                    $booking->number,
                    $booking,
                    $booking->ticket,
                    $booking->ticket?->event,
                    1,
                    $booking->token,
                    $smsService,
                    $whatsappService
                );

                $this->sendSmsWhatsapp(
                    'from',
                    $sender->name,
                    $sender->number,
                    $booking->name,
                    $booking->number,
                    $booking,
                    $booking->ticket,
                    $booking->ticket?->event,
                    1,
                    $booking->token,
                    $smsService,
                    $whatsappService,
                );
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Booking transferred successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    private function sendSmsWhatsapp(
        string $direction, // to | from
        $senderName,
        $senderNumber,
        $receiverName,
        $receiverNumber,
        $booking,
        $ticket,
        $event,
        $qty,
        $orderId,
        SmsService $smsService,
        WhatsappService $whatsappService
    ) {
        $sender = auth()->user();

        if ($direction === 'to') {
            $templateTitle = 'Ticket Transfer';
            $messageTitle  = 'Transfer Ticket To';
        } elseif ($direction === 'from') {
            $templateTitle = 'Ticket Receive';
            $messageTitle  = 'Transfer Ticket From';
        } else {
            return; // safety
        }

        $whatsappTemplate = WhatsappApi::where('title', $templateTitle)->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $shortLink = "https://getyourticket.in/{$booking->token}";

        /* ================= MESSAGE DATA ================= */

        if ($direction == 'to') {

            $data = (object)[
                'name' => $receiverName,
                'number' => $sender->number,
                'templateName' => $messageTitle,
                'orderId' => $orderId,
                'whatsappTemplateData' => $whatsappTemplateName,

                // WhatsApp template variables (order matters)
                'values' => [
                    $senderNumber,        // :C_number
                    $receiverName,          // :A_Name
                    $receiverNumber,        // :C_number
                    $qty,                   // :T_QTY
                    $event->name,
                ],

                // SMS replacement mapping
                'replacements' => [
                    ':A_Name'     => $senderName,
                    ':C_Name'   => $receiverName,
                    ':C_number'   => $receiverNumber,
                    ':T_QTY'      => $qty,
                    ':Event_Name' => $event->name,
                ]
            ];
        } else {

            // 🔹 TRANSFER FROM
            $data = (object)[
                'name' => $receiverName,
                'number' => $receiverNumber,
                'templateName' => $messageTitle,
                'orderId' => $orderId,
                'whatsappTemplateData' => $whatsappTemplateName,

                'values' => [
                    $receiverName,   // :A_Number
                    $sender->name,     // :A_Name
                    $sender->number,
                    $qty,              // :T_QTY
                    $event->name,      // :Event_Name   
                    '/' . $booking->token              
                ],

                'replacements' => [
                    ':C_Name'     => $receiverName,
                    ':A_Name'     => $sender->name,
                    ':C_number'   => $receiverNumber,
                    ':T_QTY'      => $qty,
                    ':Event_Name' => $event->name,
                    ':Token'      => $booking->token,
                ]
            ];
        }

        /* ================= SEND ================= */
        $smsService->send($data);
        $whatsappService->send($data);
    }
}
