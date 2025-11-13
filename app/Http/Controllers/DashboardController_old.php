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
        $loggedInUser = Auth::user();  // This fetches the authenticated user via Laravel's Auth system
        $isAdmin = $loggedInUser->hasRole('Admin');
        $agentRole = Role::where('name', 'Agent')->first();
        $sponsorRole = Role::where('name', 'Sponsor')->first();
        $posRole = Role::where('name', 'POS')->first();
        $scannerRole = Role::where('name', 'Scanner')->first();
        $organizerRole = Role::where('name', 'Organizer')->first();
        if ($isAdmin) {
            $onlineBookingsTicketCount = Booking::count();
            $onlineBookingsCount = Booking::distinct('session_id')->count('session_id');

            $agentBookingsTicketCount = Agent::count();
            $agentBookingsCount = Agent::distinct('session_id')->count('session_id');

            $sponsorBookingsTicketCount = SponsorBooking::count();
            $sponsorBookingsCount = SponsorBooking::distinct('session_id')->count('session_id');

            $posBookingsTicketCount = PosBooking::sum('quantity');
            $posBookingsCount = PosBooking::count();

            $userCount = User::count();
            // Count users with 'Agent' role
            $agentCount = $agentRole ? $agentRole->users()->count() : 0;
            $sponsorCount = $sponsorRole ? $sponsorRole->users()->count() : 0;
            // Count users with 'POS' role
            $posCount = $posRole ? $posRole->users()->count() : 0;
            // Count users with 'Scanner' role
            $scannerCount = $scannerRole ? $scannerRole->users()->count() : 0;
            $organizerCount = $organizerRole ? $organizerRole->users()->count() : 0;

            $offlineBookingsTicket = $posBookingsTicketCount + $agentBookingsTicketCount + $sponsorBookingsTicketCount;
            $offlineBookings = $agentBookingsCount + $sponsorBookingsCount + $posBookingsCount;

            return response()->json([
                'onlineBookingsTicket' => $onlineBookingsTicketCount,
                'onlineBookings' => $onlineBookingsCount,
                'offlineBookingsTicket' => $offlineBookingsTicket,
                'offlineBookings' => $offlineBookings,
                'userCount' => $userCount,
                'agentCount' => $agentCount,
                'sponsorCount' => $sponsorCount,
                'posCount' => $posCount,
                'organizerCount' => $organizerCount,
                'scannerCount' => $scannerCount,
                'status' => true,
            ]);
        } else {
            // Fetch bookings for the logged-in user
            $bookingsCount = Booking::where('user_id', $loggedInUser->id)->count();
            return response()->json([
                'status' => true,
                'bookingsCount' => $bookingsCount,
            ]);
        }
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
                // $easebuzzTotalAmount = Booking::where('gateway', 'easebuzz')->sum('amount');
                // $instamojoTotalAmount = Booking::where('gateway', 'instamojo')->sum('amount');
                // $phonepeTotalAmount = Booking::where('gateway', 'phonepe')->sum('amount');
                // $cashfreeTotalAmount = Booking::where('gateway', 'cashfree')->sum('amount');
                // $razorpayTotalAmount = Booking::where('gateway', 'razorpay')->sum('amount');
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
                    $this->calculation(0, 0,0,0,0, $onlineBookings, $offlineBookings, 0, $posTotals, $isCorporate, 'POS'),
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
                    ->where('amount', '>', 0)
                    ->count();
                $todayTotalBookings = Agent::where('agent_id', $request->user()->id)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNotNull('amount')
                    ->where('amount', '>', 0)
                    ->count();

                $totalTickets = Agent::where('agent_id', $request->user()->id)->count('token');
                $todayTotalTickets = Agent::where('agent_id', $request->user()->id)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->sum('token');

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
                    $this->calculation(0, 0,0,0,0, $onlineBookings, $offlineBookings, 0, $posTotals, $isCorporate, 'Agent'),
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
                    ->count();

                $totalTickets = SponsorBooking::where('sponsor_id', $request->user()->id)->count('token');
                $todayTotalTickets = SponsorBooking::where('sponsor_id', $request->user()->id)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->sum('token');

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
                    $this->calculation(0, 0,0,0,0, $onlineBookings, $offlineBookings, $sponsorBookings,0, $posTotals, $isCorporate, 'Sponsor'),
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
                    $this->calculation(0, 0,0,0,0, $onlineBookings, $offlineBookings, 0, $posTotals, $isCorporate, $isCorporate, 'POS'),
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



    // public function getDashboardSummary($type, Request $request)
    // {
    //     $user = auth()->user();

    //     $totalAmount = 0;
    //     $totalDiscount = 0;
    //     $totalBookings = 0;
    //     $totalTickets = 0;
    //     $easebuzzTotalAmount = 0;
    //     $instamojoTotalAmount = 0;
    //     $cashAmount = 0;
    //     $upiAmount = 0;
    //     $cardAmount = 0;

    //     $startDate = null;
    //     $endDate = null;

    //     if ($request->has('date')) {
    //         $dates = explode(',', $request->date);
    //         if (count($dates) === 1 || ($dates[0] === $dates[1])) {
    //             // Single date
    //             $startDate = Carbon::parse($dates[0])->startOfDay();
    //             $endDate = Carbon::parse($dates[0])->endOfDay();
    //         } elseif (count($dates) === 2) {
    //             // Date range
    //             $startDate = Carbon::parse($dates[0])->startOfDay();
    //             $endDate = Carbon::parse($dates[1])->endOfDay();
    //         } else {
    //             return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
    //         }
    //     } else {
    //         // Default: Today's bookings
    //         $startDate = Carbon::today()->startOfDay();
    //         $endDate = Carbon::today()->endOfDay();
    //     }


    //     // Define base query based on type
    //     switch ($type) {
    //         case 'online':
    //             $query = Booking::whereBetween('created_at', [$startDate, $endDate]);
    //             $totalTickets = Booking::whereBetween('created_at', [$startDate, $endDate])->count('token');
    //             $easebuzzTotalAmount = Booking::whereBetween('created_at', [$startDate, $endDate])
    //                 ->where('gateway', 'easebuzz')
    //                 ->sum('amount');

    //             $instamojoTotalAmount = Booking::whereBetween('created_at', [$startDate, $endDate])
    //                 ->where('gateway', 'instamojo')
    //                 ->sum('amount');
    //             break;

    //         case 'amusement-online':
    //             $query = AmusementBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             $totalTickets = AmusementBooking::whereBetween('created_at', [$startDate, $endDate])->count('token');
    //             break;

    //         // case 'agent':
    //         //     $query = Agent::whereBetween('created_at', [$startDate, $endDate]);
    //         //     $totalTickets = Agent::whereBetween('created_at', [$startDate, $endDate])->count('token');
    //         //     break;
    //         case 'agent':
    //             $query = Agent::whereBetween('created_at', [$startDate, $endDate]);

    //             if ($user->hasRole('Agent')) {
    //                 // Filter only for logged-in agent's data
    //                 $query->where('agent_id', $user->id);
    //             }

    //             $totalTickets = $query->count('token');

    //             $agentBookings = $query->get();

    //             $cashAmount = $agentBookings->filter(fn($b) => strtolower($b->payment_method ?? '') === 'cash')->sum('amount');
    //             $upiAmount = $agentBookings->filter(fn($b) => strtolower($b->payment_method ?? '') === 'upi')->sum('amount');
    //             $cardAmount = $agentBookings->filter(fn($b) => strtolower($b->payment_method ?? '') === 'net banking')->sum('amount');

    //             break;


    //         case 'sponsor':
    //             $query = SponsorBooking::whereBetween('created_at', [$startDate, $endDate]);

    //             if ($user->hasRole('Sponsor')) {
    //                 // Filter only for logged-in agent's data
    //                 $query->where('sponsor_id', $user->id);
    //             }

    //             $totalTickets = $query->count('token');
    //             break;
    //         case 'accreditation':
    //             $query = AccreditationBooking::whereBetween('created_at', [$startDate, $endDate]);

    //             if ($user->hasRole('Accreditation')) {
    //                 // Filter only for logged-in agent's data
    //                 $query->where('accreditation_id', $user->id);
    //             }

    //             $totalTickets = $query->count('token');
    //             break;
    //         case 'amusement-agent':
    //             $query = AmusementAgentBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             $totalTickets = AmusementAgentBooking::whereBetween('created_at', [$startDate, $endDate])->count('token');
    //             break;

    //         case 'pos':
    //             $query = PosBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             if ($user->hasRole('POS')) {
    //                 $query->where('user_id', $user->id);
    //             }
    //             $totalTickets = $query->sum('quantity');

    //             // Add payment method breakdown
    //             $posBookings = $query->with('user')->get();
    //             $cashAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'cash')->sum('amount');
    //             $upiAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'upi')->sum('amount');
    //             $cardAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'card')->sum('amount');
    //             break;

    //         case 'corporate':
    //             $query = CorporateBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             if ($user->hasRole('Corporate')) {
    //                 $query->where('user_id', $user->id);
    //             }
    //             $totalTickets = $query->sum('quantity');

    //             // Add payment method breakdown
    //             $posBookings = $query->with('user')->get();
    //             $cashAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'cash')->sum('amount');
    //             $upiAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'upi')->sum('amount');
    //             $cardAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'card')->sum('amount');
    //             break;

    //         case 'amusement-pos':
    //             $query = AmusementPosBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             $totalTickets = AmusementPosBooking::whereBetween('created_at', [$startDate, $endDate])->sum('quantity');
    //             break;

    //         case 'pending bookings':
    //             $query = PenddingBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             $totalTickets = PenddingBooking::whereBetween('created_at', [$startDate, $endDate])->count('token');
    //             break;

    //         case 'exhibition':
    //             $query = ExhibitionBooking::whereBetween('created_at', [$startDate, $endDate]);
    //             $totalTickets = ExhibitionBooking::whereBetween('created_at', [$startDate, $endDate])->sum('quantity');
    //             break;

    //         default:
    //             return response()->json([
    //                 'error' => 'Invalid type provided. Use online, agent, pos, or pending.',
    //             ], 400);
    //     }

    //     // If user is NOT an Admin, filter by user_id

    //     //  if (!$user->hasRole('Organizer' )) {
    //     //      $query->where('user_id', $user->id);
    //     //  }
    //     if ($user->hasRole('Admin')) {
    //         $eventIds = Event::pluck('id');
    //         $ticketIds = Ticket::pluck('id');
    //         $query->whereIn('ticket_id', $ticketIds);
    //     } else if ($user->hasRole('Organizer')) {
    //         $eventIds = Event::where('user_id', $user->id)->pluck('id');
    //         $ticketIds = Ticket::whereIn('event_id', $eventIds)->pluck('id');
    //         $query->whereIn('ticket_id', $ticketIds);
    //     } elseif ($user->hasRole('Agent')) {
    //         $query->where('agent_id', $user->id);
    //     } elseif ($user->hasRole('Sponsor')) {
    //         $query->where('sponsor_id', $user->id);
    //     } elseif ($user->hasRole('Accreditation')) {
    //         $query->where('accreditation_id', $user->id);
    //     }



    //     // Fetch totals based on filtered query
    //     $totalAmount = $query->sum('amount');
    //     $totalDiscount = $query->sum('discount');
    //     // $totalBookings = $query->whereNotNull('amount')->where('amount', '>', 0)->count();

    //     if ($type == 'accreditation') {
    //         $totalBookings = $query->whereNotNull('amount')->count();
    //     } else {
    //         $totalBookings = $query->whereNotNull('amount')->where('amount', '>', 0)->count();
    //     }

    //     // $totalBookings = $query->whereNotNull('amount')->count();

    //     // $totalTickets = $query->count();

    //     return response()->json([
    //         'totalAmount' => $totalAmount,
    //         'totalDiscount' => $totalDiscount,
    //         'totalBookings' => $totalBookings,
    //         'totalTickets' => $totalTickets,
    //         'easebuzzTotalAmount' => $easebuzzTotalAmount,
    //         'instamojoTotalAmount' => $instamojoTotalAmount,
    //         'cashAmount' => $cashAmount,
    //         'upiAmount' => $upiAmount,
    //         'cardAmount' => $cardAmount,
    //     ]);
    // }
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

        // Define base query based on type
        switch ($type) {
            case 'online':
                $query = Booking::whereBetween('created_at', [$startDate, $endDate]);
                $totalTickets = Booking::whereBetween('created_at', [$startDate, $endDate])->count('token');
                $easebuzzTotalAmount = Booking::whereBetween('created_at', [$startDate, $endDate])
                    ->where('gateway', 'easebuzz')
                    ->sum('amount');

                $instamojoTotalAmount = Booking::whereBetween('created_at', [$startDate, $endDate])
                    ->where('gateway', 'instamojo')
                    ->sum('amount');
                $phonepeTotalAmount = Booking::whereBetween('created_at', [$startDate, $endDate])
                    ->where('gateway', 'phonepe')
                    ->sum('amount');
                $cashfreeTotalAmount = Booking::whereBetween('created_at', [$startDate, $endDate])
                    ->where('gateway', 'cashfree')
                    ->sum('amount');
                $razorpayTotalAmount = Booking::whereBetween('created_at', [$startDate, $endDate])
                    ->where('gateway', 'razorpay')
                    ->sum('amount');
                break;

            case 'amusement-online':
                $query = AmusementBooking::whereBetween('created_at', [$startDate, $endDate]);
                $totalTickets = AmusementBooking::whereBetween('created_at', [$startDate, $endDate])->count('token');
                break;

            case 'agent':
                $query = Agent::whereBetween('created_at', [$startDate, $endDate]);

                // Apply role-based filtering for agent bookings
                if ($user->hasRole('Agent')) {
                    $query->where('agent_id', $user->id);
                } elseif ($user->hasRole('Organizer')) {
                    $eventIds = Event::where('user_id', $user->id)->pluck('id');
                    $ticketIds = Ticket::whereIn('event_id', $eventIds)->pluck('id');
                    $query->whereIn('ticket_id', $ticketIds);
                }
                // Admin sees all - no additional filtering

                $totalTickets = $query->count('token');

                $agentBookings = $query->get();

                $cashAmount = $agentBookings->filter(fn($b) => strtolower($b->payment_method ?? '') === 'cash')->sum('amount');
                $upiAmount = $agentBookings->filter(fn($b) => strtolower($b->payment_method ?? '') === 'upi')->sum('amount');
                $cardAmount = $agentBookings->filter(fn($b) => strtolower($b->payment_method ?? '') === 'net banking')->sum('amount');
                break;

            case 'sponsor':
                $query = SponsorBooking::whereBetween('created_at', [$startDate, $endDate]);

                if ($user->hasRole('Sponsor')) {
                    $query->where('sponsor_id', $user->id);
                }

                $totalTickets = $query->count('token');
                break;

            case 'accreditation':
                $query = AccreditationBooking::whereBetween('created_at', [$startDate, $endDate]);

                if ($user->hasRole('Accreditation')) {
                    $query->where('accreditation_id', $user->id);
                }

                $totalTickets = $query->count('token');
                break;

            case 'amusement-agent':
                $query = AmusementAgentBooking::whereBetween('created_at', [$startDate, $endDate]);
                $totalTickets = AmusementAgentBooking::whereBetween('created_at', [$startDate, $endDate])->count('token');
                break;

            case 'pos':
                $query = PosBooking::whereBetween('created_at', [$startDate, $endDate]);
                if ($user->hasRole('POS')) {
                    $query->where('user_id', $user->id);
                }
                $totalTickets = $query->sum('quantity');

                // Add payment method breakdown
                $posBookings = $query->with('user')->get();
                $cashAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'cash')->sum('amount');
                $upiAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'upi')->sum('amount');
                $cardAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'card')->sum('amount');
                break;

            case 'corporate':
                $query = CorporateBooking::whereBetween('created_at', [$startDate, $endDate]);
                if ($user->hasRole('Corporate')) {
                    $query->where('user_id', $user->id);
                }
                $totalTickets = $query->sum('quantity');

                // Add payment method breakdown
                $posBookings = $query->with('user')->get();
                $cashAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'cash')->sum('amount');
                $upiAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'upi')->sum('amount');
                $cardAmount = $posBookings->filter(fn($b) => strtolower($b->user->payment_method ?? '') === 'card')->sum('amount');
                break;

            case 'amusement-pos':
                $query = AmusementPosBooking::whereBetween('created_at', [$startDate, $endDate]);
                $totalTickets = AmusementPosBooking::whereBetween('created_at', [$startDate, $endDate])->sum('quantity');
                break;

            case 'pending bookings':
                $query = PenddingBooking::whereBetween('created_at', [$startDate, $endDate]);
                $totalTickets = PenddingBooking::whereBetween('created_at', [$startDate, $endDate])->count('token');
                break;

            case 'exhibition':
                $query = ExhibitionBooking::whereBetween('created_at', [$startDate, $endDate]);
                $totalTickets = ExhibitionBooking::whereBetween('created_at', [$startDate, $endDate])->sum('quantity');
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

        // Apply general role-based filtering (except for agent case which is handled above)
        if ($type !== 'agent' && $query !== null) {
            if ($user->hasRole('Admin')) {
                $eventIds = Event::pluck('id');
                $ticketIds = Ticket::pluck('id');
                $query->whereIn('ticket_id', $ticketIds);
            } else if ($user->hasRole('Organizer')) {
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

        // Fetch totals based on filtered query
        if ($query !== null) {
            $totalAmount = $query->sum('amount');
            $totalDiscount = $query->sum('discount');

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
        ]);
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
}
