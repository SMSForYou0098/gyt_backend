<?php

namespace App\Http\Controllers;

use App\Models\AccreditationBooking;
use App\Models\Agent;
use App\Models\AmusementAgentBooking;
use App\Models\AmusementBooking;
use App\Models\AmusementPosBooking;
use App\Models\Booking;
use App\Models\CashfreeConfig;
use App\Models\CorporateBooking;
use App\Models\EasebuzzConfig;
use App\Models\Event;
use App\Models\ExhibitionBooking;
use App\Models\Instamojo;
use App\Models\MasterBooking;
use App\Models\PaymentLog;
use App\Models\PenddingBooking;
use App\Models\PhonePe;
use App\Models\PosBooking;
use App\Models\Razorpay;
use App\Models\ScanHistory;
use App\Models\SponsorBooking;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function BookingCounts($id)
    {
        $loggedInUser = Auth::user();

        // Regular user (not Admin or Organizer)
        if (!$loggedInUser->hasRole(['Admin', 'Organizer'])) {
            return response()->json([
                'status' => true,
                'bookingsCount' => Booking::where('user_id', $loggedInUser->id)->count(),
            ]);
        }

        // For Admin: get all data
        // For Organizer: get only their hierarchy data
        $isAdmin = $loggedInUser->hasRole('Admin');

        // Get user IDs and ticket IDs based on role
        if ($isAdmin) {
            $userIds = User::pluck('id'); // All users
            $ticketIds = null; // Admin sees all tickets
        } else {
            // Organizer: get all users in hierarchy (recursive)
            $userIds = $this->getAllUsersUnder($loggedInUser->id);
            //return $userIds;
            // Get ticket IDs from organizer's events
            $ticketIds = Event::where('user_id', $loggedInUser->id)
                ->with('tickets')
                ->get()
                ->pluck('tickets.*.id')
                ->flatten();
        }

        // Get role counts for users in scope
        $roleCounts = Role::whereIn('name', ['Agent', 'Sponsor', 'POS', 'Scanner', 'Organizer'])
            ->withCount([
                'users' => function ($query) use ($userIds) {
                    $query->whereIn('id', $userIds);
                }
            ])
            ->pluck('users_count', 'name');

        // Booking counts based on scope
        if ($isAdmin) {
            // Admin sees all bookings
            $bookingCounts = [
                'online_ticket' => Booking::count(),
                'online_session' => Booking::distinct('session_id')->count('session_id'),
                'agent_ticket' => Agent::count(),
                'agent_session' => Agent::distinct('session_id')->count('session_id'),
                'sponsor_ticket' => SponsorBooking::count(),
                'sponsor_session' => SponsorBooking::distinct('session_id')->count('session_id'),
                'pos_ticket' => PosBooking::sum('quantity') ?? 0,
                'pos_count' => PosBooking::count(),
            ];
        } else {
            // Organizer sees only their event's online bookings + hierarchy's offline bookings
            $bookingCounts = [
                'online_ticket' => Booking::whereIn('ticket_id', $ticketIds)->count(),
                'online_session' => Booking::whereIn('ticket_id', $ticketIds)
                    ->distinct('session_id')->count('session_id'),
                'agent_ticket' => Agent::whereIn('agent_id', $userIds)->count(),
                'agent_session' => Agent::whereIn('agent_id', $userIds)
                    ->distinct('session_id')->count('session_id'),
                'sponsor_ticket' => SponsorBooking::whereIn('user_id', $userIds)->count(),
                'sponsor_session' => SponsorBooking::whereIn('user_id', $userIds)
                    ->distinct('session_id')->count('session_id'),
                'pos_ticket' => PosBooking::whereIn('user_id', $userIds)->sum('quantity') ?? 0,
                'pos_count' => PosBooking::whereIn('user_id', $userIds)->count(),
            ];
        }

        $offlineBookingsTicket = $bookingCounts['pos_ticket']
            + $bookingCounts['agent_ticket']
            + $bookingCounts['sponsor_ticket'];

        $offlineBookings = $bookingCounts['agent_session']
            + $bookingCounts['sponsor_session']
            + $bookingCounts['pos_count'];

        return response()->json([
            'onlineBookingsTicket' => $bookingCounts['online_ticket'],
            'onlineBookings' => $bookingCounts['online_session'],
            'offlineBookingsTicket' => $offlineBookingsTicket,
            'offlineBookings' => $offlineBookings,
            'userCount' => count($userIds),
            'agentCount' => $roleCounts['Agent'] ?? 0,
            'sponsorCount' => $roleCounts['Sponsor'] ?? 0,
            'posCount' => $roleCounts['POS'] ?? 0,
            'organizerCount' => $roleCounts['Organizer'] ?? 0,
            'scannerCount' => $roleCounts['Scanner'] ?? 0,
            'status' => true,
        ]);
    }

    /**
     * Recursively get all users under a given user (including nested levels)
     */
    private function getAllUsersUnder($userId)
    {
        $userIds = collect();

        // Get direct children
        $directUsers = User::where('reporting_user', $userId)->pluck('id');
        //return $directUsers;
        if ($directUsers->isEmpty()) {
            return $userIds;
        }

        // Add direct children
        $userIds = $userIds->merge($directUsers);

        // Recursively get children of each direct child
        foreach ($directUsers as $childId) {
            $userIds = $userIds->merge($this->getAllUsersUnder($childId));
        }

        return $userIds->unique();
    }
    public function calculateSale(Request $request)
    {
        $loggedInUser = Auth::user();
        $isAdmin = $loggedInUser->hasRole('Admin');
        $isOrganizer = $loggedInUser->hasRole('Organizer');
        $isPOS = $loggedInUser->hasRole('POS');
        $isAgent = $loggedInUser->hasRole('Agent');
        $isSponsor = $loggedInUser->hasRole('Sponsor');
        $isCorporate = $loggedInUser->hasRole('Corporate');

        $cacheKey = $this->generateCacheKey($isAdmin, $isOrganizer, $isPOS, $isAgent, $isSponsor, $isCorporate, $loggedInUser->id);

        $data = Cache::remember($cacheKey, 60, function () use ($request, $isAdmin, $isOrganizer, $isAgent, $isSponsor, $isPOS, $isCorporate, $loggedInUser) {

            $onlineBookings = collect();
            $offlineBookings = collect();
            $sponsorBookings = collect();
            $posTotals = ['total_base_amount' => 0, 'total_convenience_fee' => 0];

            if ($isAdmin) {
                $allBookings = Booking::latest()->get();
                $easebuzzTotalAmount = Booking::where('gateway', 'easebuzz')->sum('amount');
                $instamojoTotalAmount = Booking::where('gateway', 'instamojo')->sum('amount');
                $phonepeTotalAmount = Booking::where('gateway', 'phonepe')->sum('amount');
                $cashfreeTotalAmount = Booking::where('gateway', 'cashfree')->sum('amount');
                $razorpayTotalAmount = Booking::where('gateway', 'razorpay')->sum('amount');
                $onlineBookings = $allBookings;
                $allAgentBookings = Agent::latest()->get();
                $allsponsorBookings = SponsorBooking::latest()->get();
                $offlineBookings = $allAgentBookings;
                $sponsorBookings = $allsponsorBookings;
                $posTotals = PosBooking::selectRaw('SUM(amount) as total_base_amount, SUM(convenience_fee) as total_convenience_fee')->first();

                $weeklyData = $this->getWeeklySalesData();
                $weeklyDataForCF = $this->getWeeklySalesDataForCF();

                $activeGateways = [
                    'easebuzz' => EasebuzzConfig::where('status', '1')->exists(),
                    'instamojo' => Instamojo::where('status', '1')->exists(),
                    'phonepe' => PhonePe::where('status', '1')->exists(),
                    'cashfree' => CashfreeConfig::where('status', '1')->exists(),
                    'razorpay' => Razorpay::where('status', '1')->exists(),
                ];

                return array_merge(
                    $this->calculation($easebuzzTotalAmount, $instamojoTotalAmount, $phonepeTotalAmount, $cashfreeTotalAmount, $razorpayTotalAmount, $onlineBookings, $offlineBookings, $sponsorBookings, $posTotals, $isCorporate, 'Admin'),
                    ['weeklyData' => $weeklyData],
                    ['weeklyDataForCF' => $weeklyDataForCF],
                    ['activeGateways' => $activeGateways]
                );
            } elseif ($isOrganizer) {

                $userIds = $request->user()->usersUnder()->pluck('id')->push($request->user()->id);

                $weeklyData = $this->getWeeklySalesDataForEvents($userIds);

                $weeklyDataForCF = $this->getWeeklyCFDataForEvents($userIds);

                $ticketIds = Ticket::whereIn('event_id', Event::where('user_id', $request->user()->id)->pluck('id'))->pluck('id');
                $allBookings = Booking::whereIn('ticket_id', $ticketIds)->get();
                $easebuzzTotalAmount = Booking::whereIn('ticket_id', $ticketIds)->where('gateway', 'easebuzz')->sum('amount');
                $instamojoTotalAmount = Booking::whereIn('ticket_id', $ticketIds)->where('gateway', 'instamojo')->sum('amount');
                $phonepeTotalAmount = Booking::whereIn('ticket_id', $ticketIds)->where('gateway', 'phonepe')->sum('amount');
                $cashfreeTotalAmount = Booking::whereIn('ticket_id', $ticketIds)->where('gateway', 'cashfree')->sum('amount');
                $razorpayTotalAmount = Booking::whereIn('ticket_id', $ticketIds)->where('gateway', 'razorpay')->sum('amount');

                $onlineBookings = $allBookings;
                $allAgentBookings = Agent::whereIn('ticket_id', $ticketIds)->get();
                $allSponsorBookings = SponsorBooking::whereIn('ticket_id', $ticketIds)->get();
                $offlineBookings = $allAgentBookings->filter(function ($booking) use ($userIds) {
                    return $userIds->contains($booking->agent_id);
                });
                $sponsorBookings = $allSponsorBookings->filter(function ($booking) use ($userIds) {
                    return $userIds->contains($booking->sponsor_id);
                });
                $posTotals = PosBooking::whereIn('user_id', $userIds)
                    ->selectRaw('SUM(amount) as total_base_amount, SUM(convenience_fee) as total_convenience_fee')
                    ->first();

                $activeGateways = [
                    'easebuzz' => EasebuzzConfig::where('status', '1')->exists(),
                    'instamojo' => Instamojo::where('status', '1')->exists(),
                    'phonepe' => PhonePe::where('status', '1')->exists(),
                    'cashfree' => CashfreeConfig::where('status', '1')->exists(),
                    'razorpay' => Razorpay::where('status', '1')->exists(),
                ];

                return array_merge(
                    $this->calculation($easebuzzTotalAmount, $instamojoTotalAmount, $phonepeTotalAmount, $cashfreeTotalAmount, $razorpayTotalAmount, $onlineBookings, $offlineBookings, $sponsorBookings, $posTotals, $isCorporate, 'Organizer'),
                    ['weeklyData' => $weeklyData],
                    ['weeklyDataForCF' => $weeklyDataForCF],
                    ['activeGateways' => $activeGateways]
                );
            } elseif ($isPOS) {
                $todayStart = Carbon::now()->startOfDay(); // Start of today
                $todayEnd = Carbon::now()->endOfDay(); // End of today

                $totalCash = PosBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'Cash');
                    })
                    ->whereNull('deleted_at')
                    ->sum('amount');

                $totalUPI = PosBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'UPI');
                    })
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $totalNB = PosBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'Card');
                    })
                    ->whereNull('deleted_at')
                    ->sum('amount');

                $todayCash = PosBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'Cash');
                    })
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $todayUPI = PosBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'UPI');
                    })
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $todayNB = PosBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'Card');
                    })
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $totalBookings = PosBooking::where('user_id', $loggedInUser->id)
                    ->whereNotNull('amount')
                    ->count();
                $todayTotalBookings = PosBooking::where('user_id', $loggedInUser->id)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNotNull('amount')
                    ->count();

                $totalTickets = PosBooking::where('user_id', $loggedInUser->id)->count('quantity');
                $todayTotalTickets = PosBooking::where('user_id', $loggedInUser->id)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->sum('quantity');

                $offlineBookings = [
                    'cash' => [
                        'today' => $todayCash,
                        'total' => $totalCash
                    ],
                    'upi' => [
                        'today' => $todayUPI,
                        'total' => $totalUPI
                    ],
                    'net_banking' => [
                        'today' => $todayNB,
                        'total' => $totalNB
                    ],
                    'overall' => [
                        'today' => $todayCash + $todayUPI + $todayNB,
                        'total' => $totalCash + $totalUPI + $totalNB,
                        'totalBookings' => $totalBookings,
                        'todayTotalBookings' => $todayTotalBookings,
                        'totalTickets' => $totalTickets,
                        'todayTotalTickets' => $todayTotalTickets,
                    ]
                ];

                return array_merge(
                    $this->calculation(0, 0, 0, 0, 0, $onlineBookings, $offlineBookings, 0, $posTotals, $isCorporate, 'POS'),
                    ['weeklyData' => $this->getWeeklySalesDataForAgents($loggedInUser->id)],
                    ['weeklyDataForCF' => $this->getWeeklyCFDataForAgents($loggedInUser->id)]
                );
            } elseif ($isAgent) {

                $todayStart = Carbon::now()->startOfDay(); // Start of today
                $todayEnd = Carbon::now()->endOfDay(); // End of today

                $totalCash = Agent::where('agent_id', $loggedInUser->id)->where('payment_method', 'Cash')
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $totalUPI = Agent::where('agent_id', $loggedInUser->id)->where('payment_method', 'UPI')
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $totalNB = Agent::where('agent_id', $loggedInUser->id)->where('payment_method', 'Net Banking')
                    ->whereNull('deleted_at')
                    ->sum('amount');

                $todayCash = Agent::where('agent_id', $loggedInUser->id)->where('payment_method', 'Cash')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $todayUPI = Agent::where('agent_id', $loggedInUser->id)->where('payment_method', 'UPI')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $todayNB = Agent::where('agent_id', $loggedInUser->id)->where('payment_method', 'Net Banking')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $totalBookings = Agent::where('agent_id', $request->user()->id)
                    ->whereNotNull('amount')
                    // ->where('amount', '>', 0)
                    ->count();
                $todayTotalBookings = Agent::where('agent_id', $request->user()->id)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNotNull('amount')
                    // ->where('amount', '>', 0)
                    ->count('id');

                $totalTickets = Agent::where('agent_id', $request->user()->id)->count('token');
                $todayTotalTickets = Agent::where('agent_id', $request->user()->id)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    // ->sum('token');
                    ->count('id');

                $offlineBookings = [
                    'cash' => [
                        'today' => $todayCash,
                        'total' => $totalCash
                    ],
                    'upi' => [
                        'today' => $todayUPI,
                        'total' => $totalUPI
                    ],
                    'net_banking' => [
                        'today' => $todayNB,
                        'total' => $totalNB
                    ],
                    'overall' => [
                        'today' => $todayCash + $todayUPI + $todayNB,
                        'total' => $totalCash + $totalUPI + $totalNB,
                        'totalBookings' => $totalBookings,
                        'todayTotalBookings' => $todayTotalBookings,
                        'totalTickets' => $totalTickets,
                        'todayTotalTickets' => $todayTotalTickets,
                    ]
                ];

                return array_merge(
                    $this->calculation(0, 0, 0, 0, 0, $onlineBookings, $offlineBookings, 0, $posTotals, $isCorporate, 'Agent'),
                    ['weeklyData' => $this->getWeeklySalesDataForAgents($loggedInUser->id)],
                    ['weeklyDataForCF' => $this->getWeeklyCFDataForAgents($loggedInUser->id)]
                );
            } elseif ($isSponsor) {

                $todayStart = Carbon::now()->startOfDay(); // Start of today
                $todayEnd = Carbon::now()->endOfDay(); // End of today

                $totalCash = SponsorBooking::where('sponsor_id', $loggedInUser->id)->where('payment_method', 'Cash')
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $totalUPI = SponsorBooking::where('sponsor_id', $loggedInUser->id)->where('payment_method', 'UPI')
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $totalNB = SponsorBooking::where('sponsor_id', $loggedInUser->id)->where('payment_method', 'Net Banking')
                    ->whereNull('deleted_at')
                    ->sum('amount');

                $todayCash = SponsorBooking::where('sponsor_id', $loggedInUser->id)->where('payment_method', 'Cash')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $todayUPI = SponsorBooking::where('sponsor_id', $loggedInUser->id)->where('payment_method', 'UPI')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $todayNB = SponsorBooking::where('sponsor_id', $loggedInUser->id)->where('payment_method', 'Net Banking')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $totalBookings = SponsorBooking::where('sponsor_id', $request->user()->id)
                    ->whereNotNull('amount')
                    ->where('amount', '>', 0)
                    ->count();
                $todayTotalBookings = SponsorBooking::where('sponsor_id', $request->user()->id)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNotNull('amount')
                    ->where('amount', '>', 0)
                    ->count('id');

                $totalTickets = SponsorBooking::where('sponsor_id', $request->user()->id)->count('token');
                $todayTotalTickets = SponsorBooking::where('sponsor_id', $request->user()->id)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    // ->sum('token');
                    ->count('id');

                $sponsorBookings = [
                    'cash' => [
                        'today' => $todayCash ?? 0,
                        'total' => $totalCash
                    ],
                    'upi' => [
                        'today' => $todayUPI,
                        'total' => $totalUPI
                    ],
                    'net_banking' => [
                        'today' => $todayNB,
                        'total' => $totalNB
                    ],
                    'overall' => [
                        'today' => $todayCash + $todayUPI + $todayNB,
                        'total' => $totalCash + $totalUPI + $totalNB,
                        'totalBookings' => $totalBookings,
                        'todayTotalBookings' => $todayTotalBookings,
                        'totalTickets' => $totalTickets,
                        'todayTotalTickets' => $todayTotalTickets,
                    ]
                ];

                return array_merge(
                    $this->calculation(0, 0, 0, 0, 0, $onlineBookings, $offlineBookings, $sponsorBookings,  $posTotals, $isCorporate, 'Sponsor'),
                    ['weeklyData' => $this->getWeeklySalesDataForSponsor($loggedInUser->id)],
                    ['weeklyDataForCF' => $this->getWeeklyCFDataForSponsor($loggedInUser->id)]
                );
            } elseif ($isCorporate) {
                $todayStart = Carbon::now()->startOfDay(); // Start of today
                $todayEnd = Carbon::now()->endOfDay(); // End of today

                $totalCash = CorporateBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'Cash');
                    })
                    ->whereNull('deleted_at')
                    ->sum('amount');

                $totalUPI = CorporateBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'UPI');
                    })
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $totalNB = CorporateBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'Card');
                    })
                    ->whereNull('deleted_at')
                    ->sum('amount');

                $todayCash = CorporateBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'Cash');
                    })
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $todayUPI = CorporateBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'UPI');
                    })
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $todayNB = CorporateBooking::where('user_id', $loggedInUser->id)
                    ->whereHas('user', function ($query) {
                        $query->where('payment_method', 'Card');
                    })
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $totalBookings = CorporateBooking::where('user_id', $loggedInUser->id)
                    ->whereNotNull('amount')
                    ->count();
                $todayTotalBookings = CorporateBooking::where('user_id', $loggedInUser->id)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNotNull('amount')
                    ->count();

                $totalTickets = CorporateBooking::where('user_id', $loggedInUser->id)->count('quantity');
                $todayTotalTickets = CorporateBooking::where('user_id', $loggedInUser->id)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->sum('quantity');

                $offlineBookings = [
                    'cash' => [
                        'today' => $todayCash,
                        'total' => $totalCash
                    ],
                    'upi' => [
                        'today' => $todayUPI,
                        'total' => $totalUPI
                    ],
                    'net_banking' => [
                        'today' => $todayNB,
                        'total' => $totalNB
                    ],
                    'overall' => [
                        'today' => $todayCash + $todayUPI + $todayNB,
                        'total' => $totalCash + $totalUPI + $totalNB,
                        'totalBookings' => $totalBookings,
                        'todayTotalBookings' => $todayTotalBookings,
                        'totalTickets' => $totalTickets,
                        'todayTotalTickets' => $todayTotalTickets,
                    ]
                ];

                return array_merge(
                    $this->calculation(0, 0, 0, 0, 0, $onlineBookings, $offlineBookings, 0, $posTotals, $isCorporate, $isCorporate, 'POS'),
                    ['weeklyData' => $this->getWeeklySalesDataForAgents($loggedInUser->id)],
                    ['weeklyDataForCF' => $this->getWeeklyCFDataForAgents($loggedInUser->id)]
                );
            }
        });

        if ($isAgent) {
            return response()->json([
                'status' => true,
                'cashSales' => $data['cashSales'] ?? 0,
                'upiSales' => $data['upiSales'],
                'netBankingSales' => $data['netBankingSales'],
                'agentBooking' => $data['overallSales']['total'],
                'agentToday' => $data['overallSales']['today'],
                'totalBookings' => $data['overallSales']['totalBookings'],
                'totalTickets' => $data['overallSales']['totalTickets'],
                'todayTotalBookings' => $data['overallSales']['todayTotalBookings'],
                'todayTotalTickets' => $data['overallSales']['todayTotalTickets'],
                'weeklySales' => $data['weeklyData'],
                'convenienceFee' => $data['weeklyDataForCF'],
            ], 200);
        } elseif ($isSponsor) {
            return response()->json([
                'status' => true,
                'cashSales' => $data['cashSales'] ?? 0,
                'upiSales' => $data['upiSales'] ?? 0,
                'netBankingSales' => $data['netBankingSales'] ?? 0,
                'sponsorBooking' => $data['overallSales']['total'] ?? 0,
                'sponsorToday' => $data['overallSales']['today'] ?? 0,
                'totalBookings' => $data['overallSales']['totalBookings'] ?? 0,
                'totalTickets' => $data['overallSales']['totalTickets'] ?? 0,
                'todayTotalBookings' => $data['overallSales']['todayTotalBookings'] ?? 0,
                'todayTotalTickets' => $data['overallSales']['todayTotalTickets'] ?? 0,
                'weeklySales' => $data['weeklyData'] ?? [],
                'convenienceFee' => $data['weeklyDataForCF'] ?? [],
            ], 200);
        } elseif ($isPOS) {
            return response()->json([
                'status' => true,
                'cashSales' => $data['cashSales'] ?? 0,
                'upiSales' => $data['upiSales'],
                'netBankingSales' => $data['netBankingSales'],
                'agentBooking' => $data['overallSales']['total'],
                'agentToday' => $data['overallSales']['today'],
                'totalBookings' => $data['overallSales']['totalBookings'],
                'totalTickets' => $data['overallSales']['totalTickets'],
                'todayTotalBookings' => $data['overallSales']['todayTotalBookings'],
                'todayTotalTickets' => $data['overallSales']['todayTotalTickets'],
                'weeklySales' => $data['weeklyData'],
                'convenienceFee' => $data['weeklyDataForCF'],

            ], 200);
        } elseif ($isCorporate) {
            return response()->json([
                'status' => true,
                'cashSales' => $data['cashSales'] ?? 0,
                'upiSales' => $data['upiSales'],
                'netBankingSales' => $data['netBankingSales'],
                'agentBooking' => $data['overallSales']['total'],
                'agentToday' => $data['overallSales']['today'],
                'totalBookings' => $data['overallSales']['totalBookings'],
                'totalTickets' => $data['overallSales']['totalTickets'],
                'todayTotalBookings' => $data['overallSales']['todayTotalBookings'],
                'todayTotalTickets' => $data['overallSales']['todayTotalTickets'],
                'weeklySales' => $data['weeklyData'],
                'convenienceFee' => $data['weeklyDataForCF'],

            ], 200);
        } elseif ($isAdmin) {
            return response()->json([
                'status' => true,
                'posAmount' => $data['posAmount'] ?? [],
                'easebuzzTotalAmount' => $data['easebuzzTotalAmount'] ?? [],
                'instamojoTotalAmount' => $data['instamojoTotalAmount'] ?? [],
                'phonepeTotalAmount' => $data['phonepeTotalAmount'] ?? [],
                'cashfreeTotalAmount' => $data['cashfreeTotalAmount'] ?? [],
                'razorpayTotalAmount' => $data['razorpayTotalAmount'] ?? [],
                'onlineAmount' => $data['onlineAmount'] ?? [],
                'agentBooking' => $data['agentBooking'] ?? [],
                'sponsorBooking' => $data['sponsorBooking'] ?? [],
                'offlineAmount' => $data['offlineAmount'] ?? [],
                'onlineDiscount' => $data['onlineDiscount'] ?? [],
                'offlineDiscount' => $data['offlineDiscount'] ?? [],
                'agentCNC' => $data['agentCNC'] ?? [],
                'onlineCNC' => $data['onlineCNC'] ?? [],
                'offlineCNC' => $data['offlineCNC'] ?? [],
                'posCNC' => $data['posCNC'] ?? [],
                'salesDataNew' => $data['weeklyData'] ?? [],
                'convenienceFee' => $data['weeklyDataForCF'] ?? [],
                'activeGateways' => $data['activeGateways'] ?? [],
            ], 200);
        } else {
            return response()->json([
                'status' => true,
                'posAmount' => $data['posAmount'] ?? [],
                'easebuzzTotalAmount' => $data['easebuzzTotalAmount'] ?? [],
                'instamojoTotalAmount' => $data['instamojoTotalAmount'] ?? [],
                'phonepeTotalAmount' => $data['phonepeTotalAmount'] ?? [],
                'cashfreeTotalAmount' => $data['cashfreeTotalAmount'] ?? [],
                'razorpayTotalAmount' => $data['razorpayTotalAmount'] ?? [],
                'onlineAmount' => $data['onlineAmount'] ?? [],
                'agentBooking' => $data['agentBooking'] ?? [],
                'sponsorBooking' => $data['sponsorBooking'] ?? [],
                'offlineAmount' => $data['offlineAmount'] ?? [],
                'onlineDiscount' => $data['onlineDiscount'] ?? [],
                'offlineDiscount' => $data['offlineDiscount'] ?? [],
                'agentCNC' => $data['agentCNC'] ?? [],
                'onlineCNC' => $data['onlineCNC'] ?? [],
                'offlineCNC' => $data['offlineCNC'] ?? [],
                'posCNC' => $data['posCNC'] ?? [],
                'salesData' => $data['weeklyData'] ?? [],
                'convenienceFee' => $data['weeklyDataForCF'] ?? [],
                'activeGateways' => $data['activeGateways'] ?? [],

            ], 200);
        }
    }

    private function generateCacheKey($isAdmin, $isOrganizer, $isPOS, $isAgent, $isSponsor, $isCorporate, $userId)
    {
        if ($isAdmin) {
            return "admin_sales";
        }
        if ($isOrganizer) {
            return "organizer_sales_{$userId}";
        }
        if ($isPOS) {
            return "pos_sales_{$userId}";
        }
        if ($isAgent) {
            return "agent_sales_{$userId}";
        }
        if ($isSponsor) {
            return "sponsor_sales_{$userId}";
        }
        if ($isCorporate) {
            return "corporate_sales_{$userId}";
        }
    }

    private function calculation($easebuzzTotalAmount, $instamojoTotalAmount, $phonepeTotalAmount, $cashfreeTotalAmount, $razorpayTotalAmount, $onlineBookings, $offlineBookings, $sponsorBookings, $posTotals, $isCorporate, $role)
    {
        if ($role == 'Agent') {
            return [
                'cashSales' => $offlineBookings['cash'],
                'upiSales' => $offlineBookings['upi'],
                'netBankingSales' => $offlineBookings['net_banking'],
                'overallSales' => $offlineBookings['overall']
            ];
        } elseif ($role == 'Sponsor') {
            return [
                'cashSales' => $sponsorBookings['cash'] ?? [],
                'upiSales' => $sponsorBookings['upi'],
                'netBankingSales' => $sponsorBookings['net_banking'],
                'overallSales' => $sponsorBookings['overall']
            ];
        } elseif ($role == 'POS') {
            return [
                'cashSales' => $offlineBookings['cash'],
                'upiSales' => $offlineBookings['upi'],
                'netBankingSales' => $offlineBookings['net_banking'],
                'overallSales' => $offlineBookings['overall']
                // 'posAmount' => $posTotals['total_base_amount'] ?? 0,
                // 'posTodayAmount' => $posTotals['today_base_amount'] ?? 0,
            ];
        } elseif ($role == 'Corporate') {
            return [
                'cashSales' => $offlineBookings['cash'],
                'upiSales' => $offlineBookings['upi'],
                'netBankingSales' => $offlineBookings['net_banking'],
                'overallSales' => $offlineBookings['overall']

            ];
        } else {
            return [
                'easebuzzTotalAmount' => $easebuzzTotalAmount,
                'instamojoTotalAmount' => $instamojoTotalAmount,
                'phonepeTotalAmount' => $phonepeTotalAmount,
                'cashfreeTotalAmount' => $cashfreeTotalAmount,
                'razorpayTotalAmount' => $razorpayTotalAmount,
                'onlineAmount' => $onlineBookings->sum('amount'),
                'posAmount' => $posTotals['total_base_amount'] ?? 0,
                'posTodayAmount' => $posTotals['today_base_amount'] ?? 0,
                // 'agentBooking' => $offlineBookings->sum('base_amount'),
                'agentBooking' => $offlineBookings->sum('base_amount') - $offlineBookings->sum('discount'),
                'sponsorBooking' => $sponsorBookings->sum('base_amount') - $sponsorBookings->sum('discount'),
                'offlineAmount' => ($offlineBookings->sum('base_amount') + ($posTotals['total_base_amount'] ?? 0)) - ($offlineBookings->sum('discount') + ($posTotals['discount'] ?? 0)),
                'sponsorOfflineAmount' => ($sponsorBookings->sum('base_amount') + ($posTotals['total_base_amount'] ?? 0)) - ($sponsorBookings->sum('discount') + ($posTotals['discount'] ?? 0)),
                // 'offlineAmount' => $offlineBookings->sum('base_amount') + ($posTotals['total_base_amount'] ?? 0),
                'onlineDiscount' => $onlineBookings->sum('discount'),
                'offlineDiscount' => $offlineBookings->sum('discount'),
                'sponsorOfflineDiscount' => $sponsorBookings->sum('discount'),
                'agentCNC' => $offlineBookings->sum('convenience_fee'),
                'sponsorCNC' => $sponsorBookings->sum('convenience_fee'),
                'onlineCNC' => $onlineBookings->sum('convenience_fee'),
                'offlineCNC' => $offlineBookings->sum('convenience_fee') + ($posTotals['total_convenience_fee'] ?? 0),
                'sponsorOfflineCNC' => $sponsorBookings->sum('convenience_fee') + ($posTotals['total_convenience_fee'] ?? 0),
                'posCNC' => $posTotals['total_convenience_fee'] ?? 0,
                'corporateCNC' => $isCorporate['total_convenience_fee'] ?? 0,
            ];
        }
    }

    //admin sale
    private function getWeeklySalesData()
    {
        $onlineSales = [];
        $agentSales = [];
        $sponsorSales = [];
        $posSales = [];
        $offlineSales = []; // Initialize offlineSales array here

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $startOfDay = Carbon::parse($date)->startOfDay();
            $endOfDay = Carbon::parse($date)->endOfDay();


            $onlineSales[] = (int) Booking::whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');

            $agentSalesForDay = (int) Agent::whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');
            $sponsorSalesForDay = (int) SponsorBooking::whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');
            $posSalesForDay = (int) PosBooking::whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');

            $agentSales[] = $agentSalesForDay;
            $sponsorSales[] = $sponsorSalesForDay;
            $posSales[] = $posSalesForDay;

            $offlineSales[] = $agentSalesForDay + $posSalesForDay + $sponsorSalesForDay;
        }

        return [
            [
                'name' => 'Online Sale',
                'data' => $onlineSales,
            ],
            [
                'name' => 'Offline Sale',
                'data' => $offlineSales,
            ],
        ];
    }

    //admin convenience_fee
    private function getWeeklySalesDataForCF()
    {
        $onlineCF = [];
        $agentCF = [];
        $sponsorCF = [];
        $posCF = [];
        $offlineCF = []; // Initialize offlineSales array here

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $startOfDay = Carbon::parse($date)->startOfDay();
            $endOfDay = Carbon::parse($date)->endOfDay();


            $onlineCF[] = (int) Booking::whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee');

            $agentCFForDay = (int) Agent::whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee');
            $sponsorCFForDay = (int) SponsorBooking::whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee');
            $posCFForDay = (int) PosBooking::whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee');

            $agentCF[] = $agentCFForDay;
            $sponsorCF[] = $sponsorCFForDay;
            $posCF[] = $posCFForDay;

            $offlineCF[] = $agentCFForDay + $posCFForDay + $sponsorCFForDay;
        }

        return [
            [
                'name' => 'Online Convenience Fee',
                'data' => $onlineCF,
            ],
            [
                'name' => 'Offline Convenience Fee',
                'data' => $offlineCF,
            ],
        ];
    }

    //Organizer sale
    private function getWeeklySalesDataForEvents($userIds)
    {

        $onlineSales = [];
        $agentSales = [];
        $sponsorSales = [];
        $posSales = [];
        $offlineSales = []; // Initialize offlineSales array here

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $startOfDay = Carbon::parse($date)->startOfDay();
            $endOfDay = Carbon::parse($date)->endOfDay();

            // Online sales for the organizer's events
            $onlineSales[] = (int) Booking::whereIn('user_id', $userIds)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');

            // Offline sales for the organizer's events
            $agentSalesForDay = (int) Agent::whereIn('user_id', $userIds)
                // ->where('payment_method','offline')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');
            $sponsorSalesForDay = (int) SponsorBooking::whereIn('user_id', $userIds)
                // ->where('payment_method','offline')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');
            $posSalesForDay = (int) PosBooking::whereIn('user_id', $userIds)->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');

            $agentSales[] = $agentSalesForDay;
            $sponsorSales[] = $sponsorSalesForDay;
            $posSales[] = $posSalesForDay;

            $offlineSales[] = $agentSalesForDay + $posSalesForDay + $sponsorSalesForDay;
        }

        return [
            [
                'name' => 'Online Sale',
                'data' => $onlineSales,
            ],
            [
                'name' => 'Offline Sale',
                'data' => $offlineSales,
            ],
        ];
    }

    // Organizer convenience_fee
    private function getWeeklyCFDataForEvents($userIds)
    {

        $onlineCF = [];
        $agentCF = [];
        $sponsorCF = [];
        $posCF = [];
        $offlineCF = []; // Initialize offlineSales array here

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $startOfDay = Carbon::parse($date)->startOfDay();
            $endOfDay = Carbon::parse($date)->endOfDay();

            // Online sales for the organizer's events
            $onlineCF[] = (int) Booking::whereIn('user_id', $userIds)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee');

            // Offline sales for the organizer's events
            $agentCFForDay = (int) Agent::whereIn('user_id', $userIds)
                // ->where('payment_method','offline')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee');
            $sponsorCFForDay = (int) SponsorBooking::whereIn('user_id', $userIds)
                // ->where('payment_method','offline')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee');
            $posCFForDay = (int) PosBooking::whereIn('user_id', $userIds)->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->sum('convenience_fee');

            $agentCF[] = $agentCFForDay;
            $sponsorCF[] = $sponsorCFForDay;
            $posCF[] = $posCFForDay;

            $offlineCF[] = $agentCFForDay + $posCFForDay + $sponsorCFForDay;
        }

        return [
            [
                'name' => 'Online Convenience Fee',
                'data' => $onlineCF,
            ],
            [
                'name' => 'Offline Convenience Fee',
                'data' => $offlineCF,
            ],
        ];
    }

    private function getWeeklySalesDataForAgents($userId)
    {
        // Ensure $userId is an array
        $userId = is_array($userId) ? $userId : [$userId];

        $onlineSales = [];
        $offlineSales = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $startOfDay = Carbon::parse($date)->startOfDay();
            $endOfDay = Carbon::parse($date)->endOfDay();

            // Online sales
            $dailyOnlineSales = (int) Agent::whereIn('user_id', $userId)
                ->where('payment_method', 'online')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');

            // Offline sales
            $dailyOfflineSales = (int) Agent::whereIn('user_id', $userId)
                ->where('payment_method', 'offline')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');

            $onlineSales[] = $dailyOnlineSales;
            $offlineSales[] = $dailyOfflineSales;
        }

        return [
            [
                'name' => 'Online Sale',
                'data' => $onlineSales,
            ],
            [
                'name' => 'Offline Sale',
                'data' => $offlineSales,
            ],
        ];
    }
    private function getWeeklySalesDataForSponsor($userId)
    {
        // Ensure $userId is an array
        $userId = is_array($userId) ? $userId : [$userId];

        $onlineSales = [];
        $offlineSales = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $startOfDay = Carbon::parse($date)->startOfDay();
            $endOfDay = Carbon::parse($date)->endOfDay();

            // Online sales
            $dailyOnlineSales = (int) SponsorBooking::whereIn('user_id', $userId)
                ->where('payment_method', 'online')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');

            // Offline sales
            $dailyOfflineSales = (int) SponsorBooking::whereIn('user_id', $userId)
                ->where('payment_method', 'offline')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount');

            $onlineSales[] = $dailyOnlineSales;
            $offlineSales[] = $dailyOfflineSales;
        }

        return [
            [
                'name' => 'Online Sale',
                'data' => $onlineSales,
            ],
            [
                'name' => 'Offline Sale',
                'data' => $offlineSales,
            ],
        ];
    }

    private function getWeeklyCFDataForAgents($userId)
    {
        // Ensure $userId is an array
        $userId = is_array($userId) ? $userId : [$userId];

        $onlineCF = [];
        $offlineCF = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $startOfDay = Carbon::parse($date)->startOfDay();
            $endOfDay = Carbon::parse($date)->endOfDay();

            // Online convenience fee
            $dailyOnlineCF = (int) Agent::whereIn('user_id', $userId)
                ->where('payment_method', 'online')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee');

            // Offline convenience fee
            $dailyOfflineCF = (int) Agent::whereIn('user_id', $userId)
                ->where('payment_method', 'offline')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee');

            $onlineCF[] = $dailyOnlineCF;
            $offlineCF[] = $dailyOfflineCF;
        }

        return [
            [
                'name' => 'Online Convenience Fee',
                'data' => $onlineCF,
            ],
            [
                'name' => 'Offline Convenience Fee',
                'data' => $offlineCF,
            ],
        ];
    }
    //sponsor sale
    private function getWeeklyCFDataForSponsor($userId)
    {
        // Ensure $userId is an array
        $userId = is_array($userId) ? $userId : [$userId];

        $onlineCF = [];
        $offlineCF = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $startOfDay = Carbon::parse($date)->startOfDay();
            $endOfDay = Carbon::parse($date)->endOfDay();

            // Online convenience fee
            $dailyOnlineCF = (int) SponsorBooking::whereIn('user_id', $userId)
                ->where('payment_method', 'online')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee');

            // Offline convenience fee
            $dailyOfflineCF = (int) SponsorBooking::whereIn('user_id', $userId)
                ->where('payment_method', 'offline')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee');

            $onlineCF[] = $dailyOnlineCF;
            $offlineCF[] = $dailyOfflineCF;
        }

        return [
            [
                'name' => 'Online Convenience Fee',
                'data' => $onlineCF,
            ],
            [
                'name' => 'Offline Convenience Fee',
                'data' => $offlineCF,
            ],
        ];
    }
    // pos sale
    private function getWeeklySalesDataForPOS($userId)
    {
        $onlineSales = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $startOfDay = Carbon::parse($date)->startOfDay();
            $endOfDay = Carbon::parse($date)->endOfDay();

            // Online sales for POS user
            $dailyOnlineSales = (int) PosBooking::whereIn('user_id', $userId)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('amount'); // Casting to integer

            $onlineSales[] = $dailyOnlineSales;
        }

        return [
            [
                'name' => 'Offline Sale',
                'data' => $onlineSales, // Ensure all values are numeric
            ],
        ];
    }

    //pos convenience_fee
    private function getWeeklyCFDataForPOS($userId)
    {
        $onlineCF = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $startOfDay = Carbon::parse($date)->startOfDay();
            $endOfDay = Carbon::parse($date)->endOfDay();

            // Online sales for POS user
            $dailyOnlineCF = (int) PosBooking::whereIn('user_id', $userId)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->whereNull('deleted_at')
                ->sum('convenience_fee'); // Casting to integer
            $onlineCF[] = $dailyOnlineCF;
        }

        return [
            [
                'name' => 'Offline Convenience Fee',
                'data' => $onlineCF, // Ensure all values are numeric
            ],
        ];
    }

    public function getDashboardSummary($type, Request $request)
    {
        $user = auth()->user();

        $totalAmount = 0;
        $totalDiscount = 0;
        $totalBookings = 0;
        $totalTickets = 0;
        $easebuzzTotalAmount = 0;
        $instamojoTotalAmount = 0;
        $phonepeTotalAmount = 0;
        $cashfreeTotalAmount = 0;
        $razorpayTotalAmount = 0;
        $cashAmount = 0;
        $upiAmount = 0;
        $cardAmount = 0;
        $totalCountScanHistory = 0;
        $todayCountScanHistory = 0;


        $startDate = null;
        $endDate = null;

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
        $ticketSales = $this->eventWiseTicketSales($startDate, $endDate, $type);
        // Base query
        switch ($type) {
            case 'online':
                $query = Booking::whereBetween('created_at', [$startDate, $endDate]);
                $easebuzzTotalAmount = (clone $query)->where('gateway', 'easebuzz')->sum('amount');
                $instamojoTotalAmount = (clone $query)->where('gateway', 'instamojo')->sum('amount');
                $phonepeTotalAmount  = (clone $query)->where('gateway', 'phonepe')->sum('amount');
                $cashfreeTotalAmount = (clone $query)->where('gateway', 'cashfree')->sum('amount');
                $razorpayTotalAmount = (clone $query)->where('gateway', 'razorpay')->sum('amount');
                break;

            case 'amusement-online':
                $query = AmusementBooking::whereBetween('created_at', [$startDate, $endDate]);
                break;

            case 'agent':
                $query = Agent::whereBetween('created_at', [$startDate, $endDate]);
                if ($user->hasRole('Agent')) {
                    $query->where('agent_id', $user->id);
                } elseif ($user->hasRole('Organizer')) {
                    $eventIds = Event::where('user_id', $user->id)->pluck('id');
                    $ticketIds = Ticket::whereIn('event_id', $eventIds)->pluck('id');
                    $query->whereIn('ticket_id', $ticketIds);
                }
                $agentBookings = $query->get();
                $cashAmount = $agentBookings->filter(fn($b) => strtolower($b->payment_method ?? '') === 'cash')->sum('amount');
                $upiAmount  = $agentBookings->filter(fn($b) => strtolower($b->payment_method ?? '') === 'upi')->sum('amount');
                $cardAmount = $agentBookings->filter(fn($b) => strtolower($b->payment_method ?? '') === 'net banking')->sum('amount');
                break;

            case 'sponsor':
                $query = SponsorBooking::whereBetween('created_at', [$startDate, $endDate]);
                if ($user->hasRole('Sponsor')) {
                    $query->where('sponsor_id', $user->id);
                }
                break;

            case 'accreditation':
                $query = AccreditationBooking::whereBetween('created_at', [$startDate, $endDate]);
                if ($user->hasRole('Accreditation')) {
                    $query->where('accreditation_id', $user->id);
                }
                break;

            case 'amusement-agent':
                $query = AmusementAgentBooking::whereBetween('created_at', [$startDate, $endDate]);
                break;

            case 'pos':
                $query = PosBooking::whereBetween('created_at', [$startDate, $endDate]);
                if ($user->hasRole('POS')) {
                    $query->where('user_id', $user->id);
                }
                $posBookings = $query->with('user')->get();
                $cashAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'cash')->sum('amount');
                $upiAmount  = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'upi')->sum('amount');
                $cardAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'card')->sum('amount');
                break;

            case 'corporate':
                $query = CorporateBooking::whereBetween('created_at', [$startDate, $endDate]);
                if ($user->hasRole('Corporate')) {
                    $query->where('user_id', $user->id);
                }
                $posBookings = $query->with('user')->get();
                $cashAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'cash')->sum('amount');
                $upiAmount  = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'upi')->sum('amount');
                $cardAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'card')->sum('amount');
                break;

            case 'amusement-pos':
                $query = AmusementPosBooking::whereBetween('created_at', [$startDate, $endDate]);
                break;

            case 'pending bookings':
                $query = PenddingBooking::whereBetween('created_at', [$startDate, $endDate]);
                break;

            case 'exhibition':
                $query = ExhibitionBooking::whereBetween('created_at', [$startDate, $endDate]);
                break;

            case 'scan history':
                $totalCountScanHistory = ScanHistory::count();
                $todayCountScanHistory = ScanHistory::whereBetween('created_at', [$startDate, $endDate])->count();
                $query = null;
                break;

            default:
                return response()->json([
                    'error' => 'Invalid type provided. Use online, agent, pos, or pending.',
                ], 400);
        }

        // Role-based filtering (except agent handled above)
        if ($type !== 'agent' && $query !== null) {
            if ($user->hasRole('Admin')) {
                $ticketIds = Ticket::pluck('id');
                $query->whereIn('ticket_id', $ticketIds);
            } elseif ($user->hasRole('Organizer')) {
                $eventIds = Event::where('user_id', $user->id)->pluck('id');
                $ticketIds = Ticket::whereIn('event_id', $eventIds)->pluck('id');
                $query->whereIn('ticket_id', $ticketIds);
            } elseif ($user->hasRole('Agent')) {
                $query->where('agent_id', $user->id);
            } elseif ($user->hasRole('Sponsor')) {
                $query->where('sponsor_id', $user->id);
            } elseif ($user->hasRole('Accreditation')) {
                $query->where('accreditation_id', $user->id);
            }
        }

        // Totals
        if ($query !== null) {
            $totalAmount = $query->sum('amount');
            $totalDiscount = $query->sum('discount');

            if (in_array($type, ['pos', 'corporate', 'amusement-pos', 'exhibition'])) {
                $totalTickets = $query->sum('quantity');
            } else {
                $totalTickets = $query->count('token');
            }

            if ($type == 'accreditation') {
                $totalBookings = $query->whereNotNull('amount')->count();
            } else {
                $totalBookings = $query->whereNotNull('amount')->where('amount', '>', 0)->count();
            }
        }

        return response()->json([
            'totalAmount' => $totalAmount,
            'totalDiscount' => $totalDiscount,
            'totalBookings' => $totalBookings,
            'totalTickets' => $totalTickets,
            'easebuzzTotalAmount' => $easebuzzTotalAmount,
            'instamojoTotalAmount' => $instamojoTotalAmount,
            'phonepeTotalAmount' => $phonepeTotalAmount,
            'cashfreeTotalAmount' => $cashfreeTotalAmount,
            'razorpayTotalAmount' => $razorpayTotalAmount,
            'cashAmount' => $cashAmount,
            'upiAmount' => $upiAmount,
            'cardAmount' => $cardAmount,
            'totalCountScanHistory' => $totalCountScanHistory,
            'todayCountScanHistory' => $todayCountScanHistory,
            'ticketSales' => $ticketSales
        ]);
    }

    // new descount code
    // public function getDashboardSummary($type, Request $request)
    // {
    //     $user = auth()->user();

    //     $totalAmount = 0;
    //     $totalDiscount = 0;
    //     $totalBookings = 0;
    //     $totalTickets = 0;

    //     $easebuzzTotalAmount = 0;
    //     $instamojoTotalAmount = 0;
    //     $phonepeTotalAmount = 0;
    //     $cashfreeTotalAmount = 0;
    //     $razorpayTotalAmount = 0;

    //     $cashAmount = 0;
    //     $upiAmount = 0;
    //     $cardAmount = 0;

    //     $totalCountScanHistory = 0;
    //     $todayCountScanHistory = 0;

    //     $ticketDiscounts = [];

    //     // 📅 Date filter
    //     if ($request->has('date')) {
    //         $dates = explode(',', $request->date);

    //         if (count($dates) === 1 || $dates[0] === $dates[1]) {
    //             $startDate = Carbon::parse($dates[0])->startOfDay();
    //             $endDate   = Carbon::parse($dates[0])->endOfDay();
    //         } elseif (count($dates) === 2) {
    //             $startDate = Carbon::parse($dates[0])->startOfDay();
    //             $endDate   = Carbon::parse($dates[1])->endOfDay();
    //         } else {
    //             return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
    //         }
    //     } else {
    //         $startDate = Carbon::today()->startOfDay();
    //         $endDate   = Carbon::today()->endOfDay();
    //     }

    //     $ticketSales = $this->eventWiseTicketSales($startDate, $endDate, $type);

    //     // 🔀 Base query
    //     switch ($type) {

    //         case 'online':
    //             $query = Booking::whereBetween('created_at', [$startDate, $endDate]);

    //             $easebuzzTotalAmount = (clone $query)->where('gateway', 'easebuzz')->sum('amount');
    //             $instamojoTotalAmount = (clone $query)->where('gateway', 'instamojo')->sum('amount');
    //             $phonepeTotalAmount  = (clone $query)->where('gateway', 'phonepe')->sum('amount');
    //             $cashfreeTotalAmount = (clone $query)->where('gateway', 'cashfree')->sum('amount');
    //             $razorpayTotalAmount = (clone $query)->where('gateway', 'razorpay')->sum('amount');
    //             break;

    //         case 'amusement-online':
    //             $query = AmusementBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             break;

    //         case 'agent':
    //             $query = Agent::whereBetween('created_at', [$startDate, $endDate]);

    //             if ($user->hasRole('Agent')) {
    //                 $query->where('agent_id', $user->id);
    //             }

    //             $agentBookings = $query->get();
    //             $cashAmount = $agentBookings->where('payment_method', 'cash')->sum('amount');
    //             $upiAmount  = $agentBookings->where('payment_method', 'upi')->sum('amount');
    //             $cardAmount = $agentBookings->where('payment_method', 'net banking')->sum('amount');
    //             break;

    //         case 'sponsor':
    //             $query = SponsorBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             if ($user->hasRole('Sponsor')) {
    //                 $query->where('sponsor_id', $user->id);
    //             }
    //             break;

    //         case 'accreditation':
    //             $query = AccreditationBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             if ($user->hasRole('Accreditation')) {
    //                 $query->where('accreditation_id', $user->id);
    //             }
    //             break;

    //         case 'pos':
    //             $query = PosBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             if ($user->hasRole('POS')) {
    //                 $query->where('user_id', $user->id);
    //             }
    //             break;

    //         case 'corporate':
    //             $query = CorporateBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             break;

    //         case 'pending bookings':
    //             $query = PenddingBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             break;

    //         case 'scan history':
    //             $totalCountScanHistory = ScanHistory::count();
    //             $todayCountScanHistory = ScanHistory::whereBetween('created_at', [$startDate, $endDate])->count();
    //             $query = null;
    //             break;

    //         default:
    //             return response()->json(['error' => 'Invalid type'], 400);
    //     }

    //     // 🔐 Role-based filtering
    //     if ($query !== null && $type !== 'agent') {

    //         if ($user->hasRole('Organizer')) {
    //             $eventIds = Event::where('user_id', $user->id)->pluck('id');
    //             $ticketIds = Ticket::whereIn('event_id', $eventIds)->pluck('id');
    //             $query->whereIn('ticket_id', $ticketIds);
    //         }
    //     }

    //     // 🧮 Totals + Ticket-wise Discount
    //     if ($query !== null) {

    //         $totalAmount = $query->sum('amount');

    //         $bookings = $query->with('ticket')->get();

    //         foreach ($bookings as $booking) {
    //             if (!$booking->ticket) continue;

    //             $ticketName = $booking->ticket->name;
    //             $discount   = $booking->ticket->discount ?? 0;
    //             $qty        = $booking->quantity ?? 1;

    //             $discountAmount = $discount * $qty;

    //             $ticketDiscounts[$ticketName] = ($ticketDiscounts[$ticketName] ?? 0) + $discountAmount;
    //             $totalDiscount += $discountAmount;
    //         }

    //         $totalTickets = in_array($type, ['pos', 'corporate'])
    //             ? $query->sum('quantity')
    //             : $query->count('token');

    //         $totalBookings = $query->whereNotNull('amount')->where('amount', '>', 0)->count();
    //     }

    //     // ✅ Response
    //     return response()->json([
    //         'totalAmount' => $totalAmount,
    //         'totalDiscount' => $totalDiscount,
    //         'ticketDiscounts' => $ticketDiscounts,
    //         'totalBookings' => $totalBookings,
    //         'totalTickets' => $totalTickets,
    //         'easebuzzTotalAmount' => $easebuzzTotalAmount,
    //         'instamojoTotalAmount' => $instamojoTotalAmount,
    //         'phonepeTotalAmount' => $phonepeTotalAmount,
    //         'cashfreeTotalAmount' => $cashfreeTotalAmount,
    //         'razorpayTotalAmount' => $razorpayTotalAmount,
    //         'cashAmount' => $cashAmount,
    //         'upiAmount' => $upiAmount,
    //         'cardAmount' => $cardAmount,
    //         'totalCountScanHistory' => $totalCountScanHistory,
    //         'todayCountScanHistory' => $todayCountScanHistory,
    //         'ticketSales' => $ticketSales
    //     ]);
    // }


    public function eventWiseTicketSales($startDate = null, $endDate = null, $type = 'online')
    {
        $loggedInUser = Auth::user();
        $isAdmin = $loggedInUser->hasRole('Admin');
        $isOrganizer = $loggedInUser->hasRole('Organizer');

        // Check authorization
        if (
            !$isAdmin &&
            !$isOrganizer &&
            !$loggedInUser->hasRole('POS') &&
            !$loggedInUser->hasRole('Scanner') &&
            !$loggedInUser->hasRole('Sponsor') &&
            !$loggedInUser->hasRole('Agent')
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }

        // Determine which booking relationship to use based on type
        $bookingRelation = match ($type) {
            'agent' => 'agentBooking',
            'pos' => 'posBookings',
            'sponsor' => 'sponsorBookings',
            'online' => 'bookings',
            default => 'bookings',
        };

        // Build base query
        $query = Event::query();

        // Apply user filter for non-admin
        if (!$isAdmin) {
            if ($isOrganizer) {
                $query->where('user_id', $loggedInUser->id);
            } else {
                $reportingUserId = $loggedInUser->reporting_user;

                if (!$reportingUserId) {
                    return response()->json([
                        'status' => false,
                        'message' => 'No reporting user assigned',
                    ], 400);
                }

                $query->where('user_id', $reportingUserId);
            }
        }

        // Determine if we need to filter bookings by user_id (for POS, Agent, Sponsor)
        $filterBookingsByUser = !$isAdmin && !$isOrganizer;
        $bookingUserId = $loggedInUser->id;

        $events = $query->with([
            'tickets' => function ($q) use ($startDate, $endDate, $bookingRelation, $type, $filterBookingsByUser, $bookingUserId) {

                // Build the booking constraint closure
                $bookingConstraint = function ($query) use ($startDate, $endDate, $filterBookingsByUser, $bookingUserId) {
                    if ($startDate && $endDate) {
                        $query->whereBetween('created_at', [$startDate, $endDate]);
                    }
                    // Filter by user_id for POS, Agent, Sponsor
                    if ($filterBookingsByUser) {
                        $query->where('user_id', $bookingUserId);
                    }
                };

                if ($type === 'pos') {
                    // For POS, sum the quantity field
                    $q->withSum([$bookingRelation => $bookingConstraint], 'quantity');
                } else {
                    // For others, count the records
                    $q->withCount([$bookingRelation => $bookingConstraint]);
                }

                // Add amount sum for all types
                $q->withSum([$bookingRelation => $bookingConstraint], 'amount');
            }
        ])->get();

        $eventSales = [];

        foreach ($events as $event) {
            $ticketsArr = [];

            foreach ($event->tickets as $ticket) {
                if ($type === 'pos') {
                    $countField = Str::snake($bookingRelation) . '_sum_quantity';
                } else {
                    $countField = Str::snake($bookingRelation) . '_count';
                }

                $amountField = Str::snake($bookingRelation) . '_sum_amount';

                $bookingsCount = (int) ($ticket->$countField ?? 0);
                $totalAmount = (float) ($ticket->$amountField ?? 0);

                if ($bookingsCount > 0) {
                    $ticketsArr[] = [
                        'name' => $ticket->name,
                        'count' => $bookingsCount,
                        'total_amount' => $totalAmount,
                    ];
                }
            }

            if (!empty($ticketsArr)) {
                $eventSales[] = [
                    'name' => $event->name,
                    'tickets' => $ticketsArr,
                ];
            }
        }

        return $eventSales;
    }

    public function getAllData()
    {
        try {
            // Fetch booking from Booking, Agent, and ExhibitionBooking tables
            $booking = Booking::select(
                'bookings.token',
                'bookings.user_id',
                'users.name as user_name',
                'users.email as user_email',
                'users.number as user_number',
                'attndies.name as attendee_name',
                'attndies.mo as attendee_number',
                'attndies.email as attendee_email',
                'attndies.photo as attendee_photo'
            )
                ->leftJoin('attndies', 'bookings.attendee_id', '=', 'attndies.id')
                ->leftJoin('users', 'bookings.user_id', '=', 'users.id')
                ->get();

            //agent booking
            $agentBooking = Agent::select(
                'agents.token',
                'agents.user_id',
                'users.name as user_name',
                'users.email as user_email',
                'users.number as user_number',
                'attndies.name as attendee_name',
                'attndies.mo as attendee_number',
                'attndies.email as attendee_email',
                'attndies.photo as attendee_photo'
            )
                ->leftJoin('attndies', 'agents.attendee_id', '=', 'attndies.id')
                ->leftJoin('users', 'agents.user_id', '=', 'users.id')
                ->get();
            //sponsor booking
            $sponsorBooking = SponsorBooking::select(
                'sponsor_bookings.token',
                'sponsor_bookings.user_id',
                'users.name as user_name',
                'users.email as user_email',
                'users.number as user_number',
                'attndies.name as attendee_name',
                'attndies.mo as attendee_number',
                'attndies.email as attendee_email',
                'attndies.photo as attendee_photo'
            )
                ->leftJoin('attndies', 'sponsor_bookings.attendee_id', '=', 'attndies.id')
                ->leftJoin('users', 'sponsor_bookings.user_id', '=', 'users.id')
                ->get();

            //exhibitionBooking
            $exhibitionBooking = ExhibitionBooking::select(
                'exhibition_bookings.token',
                'exhibition_bookings.user_id',
                'users.name as user_name',
                'users.email as user_email',
                'users.number as user_number',
                'attndies.name as attendee_name',
                'attndies.mo as attendee_number',
                'attndies.email as attendee_email',
                'attndies.photo as attendee_photo'
            )
                ->leftJoin('attndies', 'exhibition_bookings.attendee_id', '=', 'attndies.id')
                ->leftJoin('users', 'exhibition_bookings.user_id', '=', 'users.id')
                ->get();

            $booking->each(function ($item) {
                $item->type = 'Online Booking';
            });
            $agentBooking->each(function ($item) {
                $item->type = 'Offline Booking';
            });
            $sponsorBooking->each(function ($item) {
                $item->type = 'Sponsor Booking';
            });
            $exhibitionBooking->each(function ($item) {
                $item->type = 'Exhibition Booking';
            });

            // Combine all data
            $allBookings = array_merge($booking->toArray(), $agentBooking->toArray(), $sponsorBooking->toArry(), $exhibitionBooking->toArray());

            if (empty($allBookings)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found in any table'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Booking details retrieved successfully',
                'data' => $allBookings
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPaymentLog(Request $request)
    {
        try {

            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();

            // If date is provided, override the range
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
            }

            // Build query
            $query = PaymentLog::whereBetween('created_at', [$startDate, $endDate]);


            $PaymentLog = $query->get();

            return response()->json([
                'status' => true,
                'PaymentLog' => $PaymentLog
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function PaymentLogDelet(Request $request)
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
                    return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
                }

                // Soft delete the logs
                $deletedCount = PaymentLog::whereBetween('created_at', [$startDate, $endDate])->delete();

                return response()->json([
                    'status' => true,
                    'message' => "{$deletedCount} payment logs soft deleted successfully."
                ]);
            }

            return response()->json(['status' => false, 'message' => 'Date parameter missing'], 400);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting payment logs.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function gatewayWiseSales()
    {
        $loggedInUser = Auth::user();
        $isAdmin = $loggedInUser->hasRole('Admin');
        $isOrganizer = $loggedInUser->hasRole('Organizer');

        // Check authorization
        if (
            !$isAdmin &&
            !$loggedInUser->hasRole('Organizer') &&
            !$loggedInUser->hasRole('POS') &&
            !$loggedInUser->hasRole('Scanner') &&
            !$loggedInUser->hasRole('Sponsor') &&
            !$loggedInUser->hasRole('Agent')
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }

        // Define date ranges
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Get user IDs for filtering (null for admin = all users)
        // Get user IDs for filtering
        if ($isAdmin) {
            // Admin can see all, but we still return null to indicate no filtering needed
            $userIds = null;
        } else {
            // For non-admin, get all users under them and add themselves
            $userIds = $this->getAllUsersUnder($loggedInUser->id)->toArray();
            $userIds = array_unique(
                array_merge($userIds, [$loggedInUser->id])
            );
        }
        //return $userIds;
        $responseData = [];

        // Gateway-wise data (only for admin)
        if ($isAdmin) {
            $responseData['gateway_wise'] = $this->getGatewayWiseData($today, $yesterday);
        }

        // Get online bookings data
        $onlineData = $this->getOnlineBookingData($today, $yesterday, $userIds);

        // POS, Agent, and Sponsor data (for both admin and organizer)
        $posData = $this->getBookingTypeData(PosBooking::class, $today, $yesterday, $userIds, true);

        $agentData = $this->getBookingTypeData(Agent::class, $today, $yesterday, $userIds);

        $sponsorData = $this->getBookingTypeData(SponsorBooking::class, $today, $yesterday, $userIds);

        // Add individual booking type data
        $responseData['pos'] = $posData;
        $responseData['agent'] = $agentData;
        $responseData['sponsor'] = $sponsorData;

        // Calculate online total
        $responseData['online'] = $onlineData;

        // Calculate offline total (POS + Agent)
        $responseData['offline'] = [
            'today' => [
                'count' => $posData['today']['count'] + $agentData['today']['count'],
                'total_amount' => $posData['today']['total_amount'] + $agentData['today']['total_amount'],
            ],
            'yesterday' => [
                'count' => $posData['yesterday']['count'] + $agentData['yesterday']['count'],
                'total_amount' => $posData['yesterday']['total_amount'] + $agentData['yesterday']['total_amount'],
            ],
        ];

        return response()->json([
            'status' => true,
            'data' => $responseData,
        ]);
    }

    /**
     * Get online booking data (from Booking table)
     */
    private function getOnlineBookingData($today, $yesterday, $userIds = null)
    {
        // Build queries for online bookings
        $todayQuery = Booking::whereDate('created_at', $today)
            ->selectRaw('COUNT(*) as count, SUM(amount) as total_amount');

        $yesterdayQuery = Booking::whereDate('created_at', $yesterday)
            ->selectRaw('COUNT(*) as count, SUM(amount) as total_amount');

        // Apply user filter if provided (for organizer - filter by ticket_id)
        if ($userIds !== null) {
            $ticketIds = Event::where('user_id', auth()->id())
                ->with('tickets')
                ->get()
                ->pluck('tickets.*.id')
                ->flatten();

            if ($ticketIds->isNotEmpty()) {
                $todayQuery->whereIn('ticket_id', $ticketIds);
                $yesterdayQuery->whereIn('ticket_id', $ticketIds);
            }
        }

        // Get results
        $todayData = $todayQuery->first();
        $yesterdayData = $yesterdayQuery->first();

        return [
            'today' => [
                'count' => (int) ($todayData->count ?? 0),
                'total_amount' => (float) ($todayData->total_amount ?? 0),
            ],
            'yesterday' => [
                'count' => (int) ($yesterdayData->count ?? 0),
                'total_amount' => (float) ($yesterdayData->total_amount ?? 0),
            ],
        ];
    }

    /**
     * Get gateway-wise online booking data
     */
    private function getGatewayWiseData($today, $yesterday)
    {
        $todaySales = Booking::whereDate('created_at', $today)
            ->selectRaw('gateway, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('gateway')
            ->get()
            ->keyBy('gateway');

        $yesterdaySales = Booking::whereDate('created_at', $yesterday)
            ->selectRaw('gateway, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('gateway')
            ->get()
            ->keyBy('gateway');

        $allGateways = Booking::distinct()->pluck('gateway');

        $gatewayData = [];
        foreach ($allGateways as $gateway) {
            $gatewayData[] = [
                'gateway' => $gateway,
                'today' => [
                    'count' => (int) ($todaySales[$gateway]->count ?? 0),
                    'total_amount' => (float) ($todaySales[$gateway]->total_amount ?? 0),
                ],
                'yesterday' => [
                    'count' => (int) ($yesterdaySales[$gateway]->count ?? 0),
                    'total_amount' => (float) ($yesterdaySales[$gateway]->total_amount ?? 0),
                ],
            ];
        }

        return $gatewayData;
    }

    /**
     * Get booking data for a specific type (POS, Agent, or Sponsor)
     * 
     * @param string $model Model class name
     * @param string $today Today's date
     * @param string $yesterday Yesterday's date
     * @param Collection|null $userIds User IDs to filter (null = all users)
     * @param bool $useQuantity Whether to sum quantity field (for POS)
     */
    private function getBookingTypeData($model, $today, $yesterday, $userIds = null, $useQuantity = false)
    {
        // Determine aggregation field
        $countField = $useQuantity ? 'SUM(quantity)' : 'COUNT(*)';

        // Determine the user column based on model
        $userColumn = $model === Agent::class ? 'agent_id' : 'user_id';

        // Build queries
        $todayQuery = $model::whereDate('created_at', $today)
            ->selectRaw("{$countField} as count, SUM(amount) as total_amount");

        $yesterdayQuery = $model::whereDate('created_at', $yesterday)
            ->selectRaw("{$countField} as count, SUM(amount) as total_amount");

        // Apply user filter if provided
        if ($userIds !== null) {
            $todayQuery->whereIn($userColumn, $userIds);
            $yesterdayQuery->whereIn($userColumn, $userIds);
        }

        // Get results
        $todayData = $todayQuery->first();
        $yesterdayData = $yesterdayQuery->first();

        return [
            'today' => [
                'count' => (int) ($todayData->count ?? 0),
                'total_amount' => (float) ($todayData->total_amount ?? 0),
            ],
            'yesterday' => [
                'count' => (int) ($yesterdayData->count ?? 0),
                'total_amount' => (float) ($yesterdayData->total_amount ?? 0),
            ],
        ];
    }

    public function organizerTotals()
    {
        try {
            $today = now()->toDateString();
            $yesterday = now()->subDay()->toDateString();

            // ✔ Only those organizers who have at least 1 active event
            $organizers = User::role('Organizer')
                ->whereHas('events', function ($q) {
                    $q->where('status', '1');
                })
                ->select('id', 'name', 'organisation')
                ->latest()        // sort before executing
                ->get();

            $response = [];

            foreach ($organizers as $org) {

                $organizerId = $org->id;

                // Helper closure for event filtering
                $eventFilter = function ($q) use ($organizerId) {
                    $q->where('user_id', (int) $organizerId);
                };

                // Today Online
                $todayOnline = Booking::whereHas('ticket.event', $eventFilter)
                    ->whereDate('created_at', $today)
                    ->sum('amount');

                // Today Offline (Agent)
                $todayOfflineBooking = Agent::whereHas('ticket.event', $eventFilter)
                    ->whereDate('created_at', $today)
                    ->sum('amount');

                // Today Offline (POS)
                $todayOfflinePOS = PosBooking::whereHas('ticket.event', $eventFilter)
                    ->whereDate('created_at', $today)
                    ->sum('amount');

                // Yesterday Online
                $yesterdayOnline = Booking::whereHas('ticket.event', $eventFilter)
                    ->whereDate('created_at', $yesterday)
                    ->sum('amount');

                // Yesterday Offline Booking
                $yesterdayOfflineBooking = Agent::whereHas('ticket.event', $eventFilter)
                    ->whereDate('created_at', $yesterday)
                    ->sum('amount');

                // Yesterday Offline POS
                $yesterdayOfflinePOS = PosBooking::whereHas('ticket.event', $eventFilter)
                    ->whereDate('created_at', $yesterday)
                    ->sum('amount');

                // Overall totals
                $overallOnline = Booking::whereHas('ticket.event', $eventFilter)->sum('amount');
                //return $overallOnline;
                $overallAgent  = Agent::whereHas('ticket.event', $eventFilter)->sum('amount');
                $overallPOS    = PosBooking::whereHas('ticket.event', $eventFilter)->sum('amount');

                // ✨ NEW: Gateway-wise totals for this organizer (as array)
                $gatewayTotals = Booking::whereHas('ticket.event', $eventFilter)
                    ->selectRaw('gateway, SUM(amount) as total_amount, COUNT(*) as total_bookings')
                    ->groupBy('gateway')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'name' => $item->gateway ?? 'unknown',
                            'total_amount' => (float) $item->total_amount,
                            'total_bookings' => (int) $item->total_bookings,
                        ];
                    })
                    ->values()
                    ->toArray();

                // ✨ NEW: Today's gateway-wise totals (as array)
                $todayGatewayTotals = Booking::whereHas('ticket.event', $eventFilter)
                    ->whereDate('created_at', $today)
                    ->selectRaw('gateway, SUM(amount) as total_amount, COUNT(*) as total_bookings')
                    ->groupBy('gateway')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'name' => $item->gateway ?? 'unknown',
                            'total_amount' => (float) $item->total_amount,
                            'total_bookings' => (int) $item->total_bookings,
                        ];
                    })
                    ->values()
                    ->toArray();

                // ✨ NEW: Yesterday's gateway-wise totals (as array)
                $yesterdayGatewayTotals = Booking::whereHas('ticket.event', $eventFilter)
                    ->whereDate('created_at', $yesterday)
                    ->selectRaw('gateway, SUM(amount) as total_amount, COUNT(*) as total_bookings')
                    ->groupBy('gateway')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'name' => $item->gateway ?? 'unknown',
                            'total_amount' => (float) $item->total_amount,
                            'total_bookings' => (int) $item->total_bookings,
                        ];
                    })
                    ->values()
                    ->toArray();

                $response[] = [
                    'organizer_id'   => $organizerId,
                    'organizer_name' => $org->name,
                    'organisation'   => $org->organisation,

                    'today' => [
                        'online'  => $todayOnline,
                        'offline' => $todayOfflineBooking + $todayOfflinePOS,
                        'gateway_wise' => $todayGatewayTotals, // ✨ NEW
                    ],

                    'yesterday' => [
                        'online'  => $yesterdayOnline,
                        'offline' => $yesterdayOfflineBooking + $yesterdayOfflinePOS,
                        'gateway_wise' => $yesterdayGatewayTotals, // ✨ NEW
                    ],

                    'online_overall_total'  => $overallOnline,
                    'offline_overall_total' => $overallAgent + $overallPOS,
                    'overall_total'         => $overallOnline + $overallAgent + $overallPOS,
                    'gateway_wise_overall' => $gatewayTotals, // ✨ NEW
                ];
            }
            return response()->json([
                'status' => true,
                'data' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }


    // public function getDashboardOrgTicket()
    // {
    //     $loggedInUser = Auth::user();
    //     $isAdmin = $loggedInUser->hasRole('Admin');
    //     $isOrganizer = $loggedInUser->hasRole('Organizer');

    //     if (!$isAdmin && !$isOrganizer) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Unauthorized access',
    //         ], 403);
    //     }

    //     $todayStart     = Carbon::today()->startOfDay();
    //     $todayEnd       = Carbon::today()->endOfDay();
    //     $yesterdayStart = Carbon::yesterday()->startOfDay();
    //     $yesterdayEnd   = Carbon::yesterday()->endOfDay();

    //     $query = Event::where('status', 1);

    //     if ($isOrganizer) {
    //         $query->where('user_id', $loggedInUser->id);
    //     }

    //     $events = $query->with([
    //         'tickets.bookings' => fn($q) => $q->select('id', 'ticket_id', 'amount', 'created_at'),
    //         'tickets.posBookings' => fn($q) => $q->select('id', 'ticket_id', 'quantity', 'amount', 'created_at'),
    //         'tickets.agentBooking' => fn($q) => $q->select('id', 'ticket_id', 'amount', 'created_at'),
    //         'tickets.sponsorBookings' => fn($q) => $q->select('id', 'ticket_id', 'amount', 'created_at'),
    //     ])->get(['id', 'name', 'date_range']);

    //     $response = [];

    //     foreach ($events as $event) {
    //         $ticketData = [];

    //         // Event-level totals
    //         $eventTodayCount      = 0;
    //         $eventYesterdayCount  = 0;
    //         $eventOverallCount    = 0;

    //         $eventTodayAmount     = 0;
    //         $eventYesterdayAmount = 0;
    //         $eventOverallAmount   = 0;

    //         $eventOnlineTodayCount      = 0;
    //         $eventOnlineYesterdayCount  = 0;
    //         $eventOnlineOverallCount    = 0;

    //         $eventOfflineTodayCount     = 0;
    //         $eventOfflineYesterdayCount = 0;
    //         $eventOfflineOverallCount   = 0;

    //         $eventOnlineTodayAmount      = 0;
    //         $eventOnlineYesterdayAmount  = 0;
    //         $eventOnlineOverallAmount    = 0;

    //         $eventOfflineTodayAmount     = 0;
    //         $eventOfflineYesterdayAmount = 0;
    //         $eventOfflineOverallAmount   = 0;

    //         // 🔹 Event-level sponsor totals
    //         $eventSponsorTodayCount      = 0;
    //         $eventSponsorYesterdayCount  = 0;
    //         $eventSponsorOverallCount    = 0;

    //         $eventSponsorTodayAmount     = 0;
    //         $eventSponsorYesterdayAmount = 0;
    //         $eventSponsorOverallAmount   = 0;

    //         foreach ($event->tickets as $ticket) {

    //             // ---------- ONLINE (WEB) ----------
    //             // Counts
    //             $todayOnlineCount = $ticket->bookings
    //                 ->whereBetween('created_at', [$todayStart, $todayEnd])
    //                 ->count();

    //             $yesterdayOnlineCount = $ticket->bookings
    //                 ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
    //                 ->count();

    //             $overallOnlineCount = $ticket->bookings->count();

    //             // Amounts
    //             $todayOnlineAmount = $ticket->bookings
    //                 ->whereBetween('created_at', [$todayStart, $todayEnd])
    //                 ->sum('amount');

    //             $yesterdayOnlineAmount = $ticket->bookings
    //                 ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
    //                 ->sum('amount');

    //             $overallOnlineAmount = $ticket->bookings->sum('amount');

    //             // ---------- POS (OFFLINE) ----------
    //             // Counts
    //             $todayPOSCount = $ticket->posBookings
    //                 ->whereBetween('created_at', [$todayStart, $todayEnd])
    //                 ->sum('quantity');

    //             $yesterdayPOSCount = $ticket->posBookings
    //                 ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
    //                 ->sum('quantity');

    //             $overallPOSCount = $ticket->posBookings->sum('quantity');

    //             // Amounts
    //             $todayPOSAmount = $ticket->posBookings
    //                 ->whereBetween('created_at', [$todayStart, $todayEnd])
    //                 ->sum('amount');

    //             $yesterdayPOSAmount = $ticket->posBookings
    //                 ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
    //                 ->sum('amount');

    //             $overallPOSAmount = $ticket->posBookings->sum('amount');

    //             // ---------- AGENT (OFFLINE) ----------
    //             // Counts
    //             $todayAgentCount = $ticket->agentBooking
    //                 ->whereBetween('created_at', [$todayStart, $todayEnd])
    //                 ->count();

    //             $yesterdayAgentCount = $ticket->agentBooking
    //                 ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
    //                 ->count();

    //             $overallAgentCount = $ticket->agentBooking->count();

    //             // Amounts
    //             $todayAgentAmount = $ticket->agentBooking
    //                 ->whereBetween('created_at', [$todayStart, $todayEnd])
    //                 ->sum('amount');

    //             $yesterdayAgentAmount = $ticket->agentBooking
    //                 ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
    //                 ->sum('amount');

    //             $overallAgentAmount = $ticket->agentBooking->sum('amount');

    //             // ---------- SPONSOR ----------
    //             $todaySponsorCount = $ticket->sponsorBookings
    //                 ->whereBetween('created_at', [$todayStart, $todayEnd])
    //                 ->count();

    //             $yesterdaySponsorCount = $ticket->sponsorBookings
    //                 ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
    //                 ->count();

    //             $overallSponsorCount = $ticket->sponsorBookings->count();

    //             $todaySponsorAmount = $ticket->sponsorBookings
    //                 ->whereBetween('created_at', [$todayStart, $todayEnd])
    //                 ->sum('amount');

    //             $yesterdaySponsorAmount = $ticket->sponsorBookings
    //                 ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
    //                 ->sum('amount');

    //             $overallSponsorAmount = $ticket->sponsorBookings->sum('amount');

    //             // ---------- TOTALS PER TICKET ----------

    //             // Online
    //             $todayOnlineTotalCount      = $todayOnlineCount;
    //             $yesterdayOnlineTotalCount  = $yesterdayOnlineCount;
    //             $overallOnlineTotalCount    = $overallOnlineCount;

    //             $todayOnlineTotalAmount     = $todayOnlineAmount;
    //             $yesterdayOnlineTotalAmount = $yesterdayOnlineAmount;
    //             $overallOnlineTotalAmount   = $overallOnlineAmount;

    //             // Offline (POS + Agent)
    //             $todayOfflineCount          = $todayPOSCount + $todayAgentCount;
    //             $yesterdayOfflineCount      = $yesterdayPOSCount + $yesterdayAgentCount;
    //             $overallOfflineCount        = $overallPOSCount + $overallAgentCount;

    //             $todayOfflineAmount         = $todayPOSAmount + $todayAgentAmount;
    //             $yesterdayOfflineAmount     = $yesterdayPOSAmount + $yesterdayAgentAmount;
    //             $overallOfflineAmount       = $overallPOSAmount + $overallAgentAmount;

    //             // TOTAL including sponsor
    //             $todayTotalCount            = $todayOnlineTotalCount + $todayOfflineCount;
    //             $yesterdayTotalCount        = $yesterdayOnlineTotalCount + $yesterdayOfflineCount;
    //             $overallTotalCount          = $overallOnlineTotalCount + $overallOfflineCount;

    //             $todayTotalAmount           = $todayOnlineTotalAmount + $todayOfflineAmount;
    //             $yesterdayTotalAmount       = $yesterdayOnlineTotalAmount + $yesterdayOfflineAmount;
    //             $overallTotalAmount         = $overallOnlineTotalAmount + $overallOfflineAmount;

    //             // ---------- EVENT TOTALS (AGGREGATE) ----------
    //             $eventTodayCount      += $todayTotalCount;
    //             $eventYesterdayCount  += $yesterdayTotalCount;
    //             $eventOverallCount    += $overallTotalCount;

    //             $eventTodayAmount     += $todayTotalAmount;
    //             $eventYesterdayAmount += $yesterdayTotalAmount;
    //             $eventOverallAmount   += $overallTotalAmount;

    //             $eventOnlineTodayCount      += $todayOnlineTotalCount;
    //             $eventOnlineYesterdayCount  += $yesterdayOnlineTotalCount;
    //             $eventOnlineOverallCount    += $overallOnlineTotalCount;

    //             $eventOfflineTodayCount     += $todayOfflineCount;
    //             $eventOfflineYesterdayCount += $yesterdayOfflineCount;
    //             $eventOfflineOverallCount   += $overallOfflineCount;

    //             $eventOnlineTodayAmount      += $todayOnlineTotalAmount;
    //             $eventOnlineYesterdayAmount  += $yesterdayOnlineTotalAmount;
    //             $eventOnlineOverallAmount    += $overallOnlineTotalAmount;

    //             $eventOfflineTodayAmount     += $todayOfflineAmount;
    //             $eventOfflineYesterdayAmount += $yesterdayOfflineAmount;
    //             $eventOfflineOverallAmount   += $overallOfflineAmount;

    //             // 🔹 EVENT SPONSOR TOTALS
    //             $eventSponsorTodayCount      += $todaySponsorCount;
    //             $eventSponsorYesterdayCount  += $yesterdaySponsorCount;
    //             $eventSponsorOverallCount    += $overallSponsorCount;

    //             $eventSponsorTodayAmount     += $todaySponsorAmount;
    //             $eventSponsorYesterdayAmount += $yesterdaySponsorAmount;
    //             $eventSponsorOverallAmount   += $overallSponsorAmount;

    //             // ---------- TICKET DATA ----------
    //             $ticketData[] = [
    //                 'name' => $ticket->name,

    //                 // Total counts
    //                 'today_count'     => $todayTotalCount,
    //                 'yesterday_count' => $yesterdayTotalCount,
    //                 'overall_count'   => $overallTotalCount,

    //                 // Total amounts
    //                 'today_amount'     => $todayTotalAmount,
    //                 'yesterday_amount' => $yesterdayTotalAmount,
    //                 'overall_amount'   => $overallTotalAmount,

    //                 // Online breakdown
    //                 'online' => [
    //                     'today_count'     => $todayOnlineTotalCount,
    //                     'yesterday_count' => $yesterdayOnlineTotalCount,
    //                     'overall_count'   => $overallOnlineTotalCount,

    //                     'today_amount'     => $todayOnlineTotalAmount,
    //                     'yesterday_amount' => $yesterdayOnlineTotalAmount,
    //                     'overall_amount'   => $overallOnlineTotalAmount,
    //                 ],

    //                 // Offline breakdown (POS + Agent)
    //                 'offline' => [
    //                     'today_count'     => $todayOfflineCount,
    //                     'yesterday_count' => $yesterdayOfflineCount,
    //                     'overall_count'   => $overallOfflineCount,

    //                     'today_amount'     => $todayOfflineAmount,
    //                     'yesterday_amount' => $yesterdayOfflineAmount,
    //                     'overall_amount'   => $overallOfflineAmount,
    //                 ],

    //                 // Sponsor breakdown
    //                 'sponsor' => [
    //                     'today_count'     => $todaySponsorCount,
    //                     'yesterday_count' => $yesterdaySponsorCount,
    //                     'overall_count'   => $overallSponsorCount,

    //                     'today_amount'     => $todaySponsorAmount,
    //                     'yesterday_amount' => $yesterdaySponsorAmount,
    //                     'overall_amount'   => $overallSponsorAmount,
    //                 ],
    //             ];
    //         }

    //         $response[] = [
    //             'name' => $event->name,
    //             'date_range' => $event->date_range,

    //             // EVENT LEVEL TOTAL COUNTS
    //             'today_count'     => $eventTodayCount,
    //             'yesterday_count' => $eventYesterdayCount,
    //             'overall_count'   => $eventOverallCount,

    //             // EVENT LEVEL TOTAL AMOUNTS
    //             'today_amount'     => $eventTodayAmount,
    //             'yesterday_amount' => $eventYesterdayAmount,
    //             'overall_amount'   => $eventOverallAmount,

    //             // EVENT LEVEL ONLINE/OFFLINE SUMMARY
    //             'online' => [
    //                 'today_count'     => $eventOnlineTodayCount,
    //                 'yesterday_count' => $eventOnlineYesterdayCount,
    //                 'overall_count'   => $eventOnlineOverallCount,

    //                 'today_amount'     => $eventOnlineTodayAmount,
    //                 'yesterday_amount' => $eventOnlineYesterdayAmount,
    //                 'overall_amount'   => $eventOnlineOverallAmount,
    //             ],
    //             'offline' => [
    //                 'today_count'     => $eventOfflineTodayCount,
    //                 'yesterday_count' => $eventOfflineYesterdayCount,
    //                 'overall_count'   => $eventOfflineOverallCount,

    //                 'today_amount'     => $eventOfflineTodayAmount,
    //                 'yesterday_amount' => $eventOfflineYesterdayAmount,
    //                 'overall_amount'   => $eventOfflineOverallAmount,
    //             ],

    //             // EVENT LEVEL SPONSOR SUMMARY
    //             'sponsor' => [
    //                 'today_count'     => $eventSponsorTodayCount,
    //                 'yesterday_count' => $eventSponsorYesterdayCount,
    //                 'overall_count'   => $eventSponsorOverallCount,

    //                 'today_amount'     => $eventSponsorTodayAmount,
    //                 'yesterday_amount' => $eventSponsorYesterdayAmount,
    //                 'overall_amount'   => $eventSponsorOverallAmount,
    //             ],

    //             'tickets' => $ticketData,
    //         ];
    //     }

    //     return response()->json($response);
    // }

    public function getDashboardOrgTicket()
{
    $loggedInUser = Auth::user();
    $isAdmin = $loggedInUser->hasRole('Admin');
    $isOrganizer = $loggedInUser->hasRole('Organizer');

    if (!$isAdmin && !$isOrganizer) {
        return response()->json([
            'status'  => false,
            'message' => 'Unauthorized access',
        ], 403);
    }

    $todayStart     = Carbon::today()->startOfDay();
    $todayEnd       = Carbon::today()->endOfDay();
    $yesterdayStart = Carbon::yesterday()->startOfDay();
    $yesterdayEnd   = Carbon::yesterday()->endOfDay();

    $query = Event::where('status', 1);

    if ($isOrganizer) {
        $query->where('user_id', $loggedInUser->id);
    }

    $events = $query->with([
        'tickets.bookings:id,ticket_id,amount,created_at',
        'tickets.posBookings:id,ticket_id,quantity,amount,created_at',
        'tickets.agentBooking:id,ticket_id,amount,created_at',
        'tickets.sponsorBookings:id,ticket_id,amount,created_at',
    ])->get(['id', 'name', 'date_range']);

    $response = [];

    foreach ($events as $event) {

        $ticketData = [];

        // ================= EVENT TOTALS =================
        $eventTodayCount = $eventYesterdayCount = $eventOverallCount = 0;
        $eventTodayAmount = $eventYesterdayAmount = $eventOverallAmount = 0;

        // Online
        $eventOnlineTodayCount = $eventOnlineYesterdayCount = $eventOnlineOverallCount = 0;
        $eventOnlineTodayAmount = $eventOnlineYesterdayAmount = $eventOnlineOverallAmount = 0;

        // POS
        $eventPOSTodayCount = $eventPOSYesterdayCount = $eventPOSOverallCount = 0;
        $eventPOSTodayAmount = $eventPOSYesterdayAmount = $eventPOSOverallAmount = 0;

        // Agent
        $eventAgentTodayCount = $eventAgentYesterdayCount = $eventAgentOverallCount = 0;
        $eventAgentTodayAmount = $eventAgentYesterdayAmount = $eventAgentOverallAmount = 0;

        // Offline (POS + Agent)
        $eventOfflineTodayCount = $eventOfflineYesterdayCount = $eventOfflineOverallCount = 0;
        $eventOfflineTodayAmount = $eventOfflineYesterdayAmount = $eventOfflineOverallAmount = 0;

        // Sponsor
        $eventSponsorTodayCount = $eventSponsorYesterdayCount = $eventSponsorOverallCount = 0;
        $eventSponsorTodayAmount = $eventSponsorYesterdayAmount = $eventSponsorOverallAmount = 0;

        foreach ($event->tickets as $ticket) {

            // ================= ONLINE =================
            $todayOnlineCount = $ticket->bookings->whereBetween('created_at', [$todayStart, $todayEnd])->count();
            $yesterdayOnlineCount = $ticket->bookings->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
            $overallOnlineCount = $ticket->bookings->count();

            $todayOnlineAmount = $ticket->bookings->whereBetween('created_at', [$todayStart, $todayEnd])->sum('amount');
            $yesterdayOnlineAmount = $ticket->bookings->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->sum('amount');
            $overallOnlineAmount = $ticket->bookings->sum('amount');

            // ================= POS =================
            $todayPOSCount = $ticket->posBookings->whereBetween('created_at', [$todayStart, $todayEnd])->sum('quantity');
            $yesterdayPOSCount = $ticket->posBookings->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->sum('quantity');
            $overallPOSCount = $ticket->posBookings->sum('quantity');

            $todayPOSAmount = $ticket->posBookings->whereBetween('created_at', [$todayStart, $todayEnd])->sum('amount');
            $yesterdayPOSAmount = $ticket->posBookings->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->sum('amount');
            $overallPOSAmount = $ticket->posBookings->sum('amount');

            // ================= AGENT =================
            $todayAgentCount = $ticket->agentBooking->whereBetween('created_at', [$todayStart, $todayEnd])->count();
            $yesterdayAgentCount = $ticket->agentBooking->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
            $overallAgentCount = $ticket->agentBooking->count();

            $todayAgentAmount = $ticket->agentBooking->whereBetween('created_at', [$todayStart, $todayEnd])->sum('amount');
            $yesterdayAgentAmount = $ticket->agentBooking->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->sum('amount');
            $overallAgentAmount = $ticket->agentBooking->sum('amount');

            // ================= SPONSOR =================
            $todaySponsorCount = $ticket->sponsorBookings->whereBetween('created_at', [$todayStart, $todayEnd])->count();
            $yesterdaySponsorCount = $ticket->sponsorBookings->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
            $overallSponsorCount = $ticket->sponsorBookings->count();

            $todaySponsorAmount = $ticket->sponsorBookings->whereBetween('created_at', [$todayStart, $todayEnd])->sum('amount');
            $yesterdaySponsorAmount = $ticket->sponsorBookings->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->sum('amount');
            $overallSponsorAmount = $ticket->sponsorBookings->sum('amount');

            // ================= TOTALS =================
            $todayOfflineCount = $todayPOSCount + $todayAgentCount;
            $yesterdayOfflineCount = $yesterdayPOSCount + $yesterdayAgentCount;
            $overallOfflineCount = $overallPOSCount + $overallAgentCount;

            $todayOfflineAmount = $todayPOSAmount + $todayAgentAmount;
            $yesterdayOfflineAmount = $yesterdayPOSAmount + $yesterdayAgentAmount;
            $overallOfflineAmount = $overallPOSAmount + $overallAgentAmount;

            $todayTotalCount = $todayOnlineCount + $todayOfflineCount;
            $yesterdayTotalCount = $yesterdayOnlineCount + $yesterdayOfflineCount;
            $overallTotalCount = $overallOnlineCount + $overallOfflineCount;

            $todayTotalAmount = $todayOnlineAmount + $todayOfflineAmount;
            $yesterdayTotalAmount = $yesterdayOnlineAmount + $yesterdayOfflineAmount;
            $overallTotalAmount = $overallOnlineAmount + $overallOfflineAmount;

            // ================= EVENT AGGREGATES =================
            $eventTodayCount += $todayTotalCount;
            $eventYesterdayCount += $yesterdayTotalCount;
            $eventOverallCount += $overallTotalCount;

            $eventTodayAmount += $todayTotalAmount;
            $eventYesterdayAmount += $yesterdayTotalAmount;
            $eventOverallAmount += $overallTotalAmount;

            $eventOnlineTodayCount += $todayOnlineCount;
            $eventOnlineYesterdayCount += $yesterdayOnlineCount;
            $eventOnlineOverallCount += $overallOnlineCount;

            $eventOnlineTodayAmount += $todayOnlineAmount;
            $eventOnlineYesterdayAmount += $yesterdayOnlineAmount;
            $eventOnlineOverallAmount += $overallOnlineAmount;

            $eventPOSTodayCount += $todayPOSCount;
            $eventPOSYesterdayCount += $yesterdayPOSCount;
            $eventPOSOverallCount += $overallPOSCount;

            $eventPOSTodayAmount += $todayPOSAmount;
            $eventPOSYesterdayAmount += $yesterdayPOSAmount;
            $eventPOSOverallAmount += $overallPOSAmount;

            $eventAgentTodayCount += $todayAgentCount;
            $eventAgentYesterdayCount += $yesterdayAgentCount;
            $eventAgentOverallCount += $overallAgentCount;

            $eventAgentTodayAmount += $todayAgentAmount;
            $eventAgentYesterdayAmount += $yesterdayAgentAmount;
            $eventAgentOverallAmount += $overallAgentAmount;

            $eventOfflineTodayCount += $todayOfflineCount;
            $eventOfflineYesterdayCount += $yesterdayOfflineCount;
            $eventOfflineOverallCount += $overallOfflineCount;

            $eventOfflineTodayAmount += $todayOfflineAmount;
            $eventOfflineYesterdayAmount += $yesterdayOfflineAmount;
            $eventOfflineOverallAmount += $overallOfflineAmount;

            $eventSponsorTodayCount += $todaySponsorCount;
            $eventSponsorYesterdayCount += $yesterdaySponsorCount;
            $eventSponsorOverallCount += $overallSponsorCount;

            $eventSponsorTodayAmount += $todaySponsorAmount;
            $eventSponsorYesterdayAmount += $yesterdaySponsorAmount;
            $eventSponsorOverallAmount += $overallSponsorAmount;

            // ================= TICKET RESPONSE =================
            $ticketData[] = [
                'name' => $ticket->name,

                'today_count' => $todayTotalCount,
                'yesterday_count' => $yesterdayTotalCount,
                'overall_count' => $overallTotalCount,

                'today_amount' => $todayTotalAmount,
                'yesterday_amount' => $yesterdayTotalAmount,
                'overall_amount' => $overallTotalAmount,

                'online' => [
                    'today_count' => $todayOnlineCount,
                    'yesterday_count' => $yesterdayOnlineCount,
                    'overall_count' => $overallOnlineCount,
                    'today_amount' => $todayOnlineAmount,
                    'yesterday_amount' => $yesterdayOnlineAmount,
                    'overall_amount' => $overallOnlineAmount,
                ],

                'pos' => [
                    'today_count' => $todayPOSCount,
                    'yesterday_count' => $yesterdayPOSCount,
                    'overall_count' => $overallPOSCount,
                    'today_amount' => $todayPOSAmount,
                    'yesterday_amount' => $yesterdayPOSAmount,
                    'overall_amount' => $overallPOSAmount,
                ],

                'agent' => [
                    'today_count' => $todayAgentCount,
                    'yesterday_count' => $yesterdayAgentCount,
                    'overall_count' => $overallAgentCount,
                    'today_amount' => $todayAgentAmount,
                    'yesterday_amount' => $yesterdayAgentAmount,
                    'overall_amount' => $overallAgentAmount,
                ],

                'offline' => [
                    'today_count' => $todayOfflineCount,
                    'yesterday_count' => $yesterdayOfflineCount,
                    'overall_count' => $overallOfflineCount,
                    'today_amount' => $todayOfflineAmount,
                    'yesterday_amount' => $yesterdayOfflineAmount,
                    'overall_amount' => $overallOfflineAmount,
                ],

                'sponsor' => [
                    'today_count' => $todaySponsorCount,
                    'yesterday_count' => $yesterdaySponsorCount,
                    'overall_count' => $overallSponsorCount,
                    'today_amount' => $todaySponsorAmount,
                    'yesterday_amount' => $yesterdaySponsorAmount,
                    'overall_amount' => $overallSponsorAmount,
                ],
            ];
        }

        // ================= EVENT RESPONSE =================
        $response[] = [
            'name' => $event->name,
            'date_range' => $event->date_range,

            'today_count' => $eventTodayCount,
            'yesterday_count' => $eventYesterdayCount,
            'overall_count' => $eventOverallCount,

            'today_amount' => $eventTodayAmount,
            'yesterday_amount' => $eventYesterdayAmount,
            'overall_amount' => $eventOverallAmount,

            'online' => [
                'today_count' => $eventOnlineTodayCount,
                'yesterday_count' => $eventOnlineYesterdayCount,
                'overall_count' => $eventOnlineOverallCount,
                'today_amount' => $eventOnlineTodayAmount,
                'yesterday_amount' => $eventOnlineYesterdayAmount,
                'overall_amount' => $eventOnlineOverallAmount,
            ],

            'pos' => [
                'today_count' => $eventPOSTodayCount,
                'yesterday_count' => $eventPOSYesterdayCount,
                'overall_count' => $eventPOSOverallCount,
                'today_amount' => $eventPOSTodayAmount,
                'yesterday_amount' => $eventPOSYesterdayAmount,
                'overall_amount' => $eventPOSOverallAmount,
            ],

            'agent' => [
                'today_count' => $eventAgentTodayCount,
                'yesterday_count' => $eventAgentYesterdayCount,
                'overall_count' => $eventAgentOverallCount,
                'today_amount' => $eventAgentTodayAmount,
                'yesterday_amount' => $eventAgentYesterdayAmount,
                'overall_amount' => $eventAgentOverallAmount,
            ],

            'offline' => [
                'today_count' => $eventOfflineTodayCount,
                'yesterday_count' => $eventOfflineYesterdayCount,
                'overall_count' => $eventOfflineOverallCount,
                'today_amount' => $eventOfflineTodayAmount,
                'yesterday_amount' => $eventOfflineYesterdayAmount,
                'overall_amount' => $eventOfflineOverallAmount,
            ],

            'sponsor' => [
                'today_count' => $eventSponsorTodayCount,
                'yesterday_count' => $eventSponsorYesterdayCount,
                'overall_count' => $eventSponsorOverallCount,
                'today_amount' => $eventSponsorTodayAmount,
                'yesterday_amount' => $eventSponsorYesterdayAmount,
                'overall_amount' => $eventSponsorOverallAmount,
            ],

            'tickets' => $ticketData,
        ];
    }

    return response()->json($response);
}
}
