<?php

namespace App\Http\Controllers;

use App\Exports\AgentReportExport;
use App\Exports\EventReportExport;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\CorporateBooking;
use App\Models\Event;
use App\Models\PosBooking;
use App\Models\SponsorBooking;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Collection;

class ReportController extends Controller
{

    public function EventReport(Request $request)
    {
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


        $eventType = $request->type;
      $user = auth()->user();

        $eventsQuery = Event::with([
            'tickets.bookings' => function ($query) use ($startDate, $endDate) {
                $query->select('id', 'ticket_id', 'user_id', 'gateway', 'status', 'base_amount', 'amount', 'convenience_fee', 'discount', 'created_at', 'agent_id');

                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }
            },
            'tickets.posBookings' => function ($query) use ($startDate, $endDate) {
                $query->select('id', 'ticket_id', 'quantity', 'status', 'base_amount', 'amount', 'convenience_fee', 'discount', 'created_at');

                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }
            },
            'tickets.agentBooking' => function ($query) use ($startDate, $endDate) {
                $query->select('id', 'ticket_id', 'user_id', 'status', 'base_amount', 'amount', 'convenience_fee', 'discount', 'created_at');

                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }
            },
            'user'
        ]);

      if ($user->hasRole('Organizer')) {
        $eventsQuery->where('user_id', $user->id);
    }
        $today = Carbon::today()->toDateString();
        if ($eventType == 'all') {
            $events = $eventsQuery->get(['id', 'name as event_name', 'user_id']);
        } else {
            $events = $eventsQuery->where(function ($query) use ($today) {
                $query->where('date_range', 'LIKE', "%$today%")
                    ->orWhereRaw("? BETWEEN SUBSTRING_INDEX(date_range, ',', 1) AND SUBSTRING_INDEX(date_range, ',', -1)", [$today]);
            })->get(['id', 'name as event_name', 'user_id']);
        }

        $eventReport = [];

        foreach ($events as $event) {
            // Cast to integer explicitly for ticket_quantity sum
            $totalTickets = $event->tickets->sum(function ($ticket) {
                return (int)$ticket->ticket_quantity;
            });

            $totalEventBookings = 0;
            $totalAgentBookings = 0;
            $totalNonAgentBookings = 0;
            $nonAgentBookingsCount = 0;
            $totalPosQuantity = 0;
            $totalIns = 0;

            $totalEasebuzzTotalAmount = 0;
            $totalInstamojoTotalAmount = 0;

            $totalOnlineBaseAmount = 0;
            $totalOnlineConvenienceFee = 0;
            $totalOnlineDiscount = 0;

            $totalAgentBaseAmount = 0;
            $totalAgentConvenienceFee = 0;
            $totalAgentDiscount = 0;

            $totalPosBaseAmount = 0;
            $totalPosConvenienceFee = 0;
            $totalPosDiscount = 0;

            $eventSummaryIndex = count($eventReport);
            $eventReport[] = [
                'event_name' => $event->event_name,
                'ticket_quantity' => $totalTickets,
                'total_bookings' => 0,
                'agent_bookings' => 0,
                'non_agent_bookings' => 0,
                'pos_bookings_quantity' => 0,
                'total_ins' => 0,
                'easebuzz_total_amount' => 0,
                'instamojo_total_amount' => 0,
                'online_base_amount' => 0,
                'online_convenience_fee' => 0,
                'online_discount' => 0,
                'agent_base_amount' => 0,
                'agent_convenience_fee' => 0,
                'agent_discount' => 0,
                'pos_base_amount' => 0,
                'pos_convenience_fee' => 0,
                'pos_discount' => 0,
                'organizer' => $event->user->name ?? 'N/A',
                'parent' => true
            ];
            //return response()->json($event->tickets, 200);

            foreach ($event->tickets as $ticket) {
                $totalTicketBookings = $ticket->bookings->count();
                //return response()->json(['data' => $ticket], 200);
                // Ensure proper casting for pos bookings quantity
                $totalPosQuantityForTicket = $ticket->posBookings->sum(function ($booking) {
                    return (int)$booking->quantity;
                });

                $totalTicketCheckIns = $ticket->bookings->where('status', 1)->count();

                // Ensure proper casting for pos check-ins
                $totalPosCheckInsForTicket = $ticket->posBookings->where('status', 1)->sum(function ($booking) {
                    return (int)$booking->quantity;
                });

                // $onlineBookings = $ticket->bookings;

                $onlineBookings = $ticket->bookings()->whereNull('deleted_at')->get();
                $agentBookings = $ticket->agentBooking()->whereNull('deleted_at')->get();
                $agentBookingsCount = $agentBookings->count();
                $nonAgentBookingsCount = $onlineBookings->count();

                // Compute sums with proper casting
                $onlineEasebuzzTotalAmount = (float)$onlineBookings->where('gateway', 'easebuzz')->sum(function ($item) {
                    return is_numeric($item->amount) ? (float)$item->amount : 0;
                });


                $onlineInstamojoTotalAmount = (float)$onlineBookings->where('gateway', 'instamojo')->sum(function ($item) {
                    return is_numeric($item->amount) ? (float)$item->amount : 0;
                });

                $onlineBaseAmount = (float)$onlineBookings->sum(function ($item) {
                    return is_numeric($item->amount) ? (float)$item->amount : 0;
                });

                $onlineConvenienceFee = (float)$onlineBookings->sum(function ($item) {
                    return is_numeric($item->convenience_fee) ? (float)$item->convenience_fee : 0;
                });

                $onlineDiscount = (float)$onlineBookings->sum(function ($item) {
                    return is_numeric($item->discount) ? (float)$item->discount : 0;
                });

                $agentBaseAmount = (float)$agentBookings->sum(function ($item) {
                    return is_numeric($item->amount) ? (float)$item->amount : 0;
                });

                $agentConvenienceFee = (float)$agentBookings->sum(function ($item) {
                    return is_numeric($item->convenience_fee) ? (float)$item->convenience_fee : 0;
                });

                $agentDiscount = (float)$agentBookings->sum(function ($item) {
                    return is_numeric($item->discount) ? (float)$item->discount : 0;
                });

                $posBaseAmount = (float)$ticket->posBookings->sum(function ($item) {
                    return is_numeric($item->amount) ? (float)$item->amount : 0;
                });

                $posConvenienceFee = (float)$ticket->posBookings->sum(function ($item) {
                    return is_numeric($item->convenience_fee) ? (float)$item->convenience_fee : 0;
                });

                $posDiscount = (float)$ticket->posBookings->sum(function ($item) {
                    return is_numeric($item->discount) ? (float)$item->discount : 0;
                });

                // Update running totals
                $totalEventBookings += $totalTicketBookings;
                $totalAgentBookings += $agentBookingsCount;
                $totalNonAgentBookings += $nonAgentBookingsCount;
                $totalPosQuantity += $totalPosQuantityForTicket;
                $totalIns += $totalTicketCheckIns + $totalPosCheckInsForTicket;

                $totalEasebuzzTotalAmount += $onlineEasebuzzTotalAmount;
                $totalInstamojoTotalAmount += $onlineInstamojoTotalAmount;

                $totalOnlineBaseAmount += $onlineBaseAmount;
                $totalOnlineConvenienceFee += $onlineConvenienceFee;
                $totalOnlineDiscount += $onlineDiscount;

                $totalAgentBaseAmount += $agentBaseAmount;
                $totalAgentConvenienceFee += $agentConvenienceFee;
                $totalAgentDiscount += $agentDiscount;

                $totalPosBaseAmount += $posBaseAmount;
                $totalPosConvenienceFee += $posConvenienceFee;
                $totalPosDiscount += $posDiscount;

                $eventReport[] = [
                    'event_name' => $event->event_name . ' (' . $ticket->name . ')',
                    'organizer' => '-',
                    'ticket_quantity' => (int)$ticket->ticket_quantity,
                    'total_bookings' => $totalTicketBookings,
                    'agent_bookings' => $agentBookingsCount,
                    'non_agent_bookings' => $nonAgentBookingsCount,
                    'pos_bookings_quantity' => $totalPosQuantityForTicket,
                    'total_ins' => $totalTicketCheckIns + $totalPosCheckInsForTicket,
                    'easebuzz_total_amount' => $onlineEasebuzzTotalAmount,
                    'instamojo_total_amount' => $onlineInstamojoTotalAmount,
                    'online_base_amount' => $onlineBaseAmount,
                    'online_convenience_fee' => $onlineConvenienceFee,
                    'online_discount' => $onlineDiscount,
                    'agent_base_amount' => $agentBaseAmount,
                    'agent_convenience_fee' => $agentConvenienceFee,
                    'agent_discount' => $agentDiscount,
                    'pos_base_amount' => $posBaseAmount,
                    'pos_convenience_fee' => $posConvenienceFee,
                    'pos_discount' => $posDiscount,
                    'parent' => false
                ];
            }

            $eventReport[$eventSummaryIndex]['total_bookings'] = $totalEventBookings;
            $eventReport[$eventSummaryIndex]['agent_bookings'] = $totalAgentBookings;
            $eventReport[$eventSummaryIndex]['non_agent_bookings'] = $totalNonAgentBookings;
            $eventReport[$eventSummaryIndex]['pos_bookings_quantity'] = $totalPosQuantity;
            $eventReport[$eventSummaryIndex]['total_ins'] = $totalIns;
            $eventReport[$eventSummaryIndex]['easebuzz_total_amount'] = $totalEasebuzzTotalAmount;
            $eventReport[$eventSummaryIndex]['instamojo_total_amount'] = $totalInstamojoTotalAmount;
            $eventReport[$eventSummaryIndex]['online_base_amount'] = $totalOnlineBaseAmount;
            $eventReport[$eventSummaryIndex]['online_convenience_fee'] = $totalOnlineConvenienceFee;
            $eventReport[$eventSummaryIndex]['online_discount'] = $totalOnlineDiscount;
            $eventReport[$eventSummaryIndex]['agent_base_amount'] = $totalAgentBaseAmount;
            $eventReport[$eventSummaryIndex]['agent_convenience_fee'] = $totalAgentConvenienceFee;
            $eventReport[$eventSummaryIndex]['agent_discount'] = $totalAgentDiscount;
            $eventReport[$eventSummaryIndex]['pos_base_amount'] = $totalPosBaseAmount;
            $eventReport[$eventSummaryIndex]['pos_convenience_fee'] = $totalPosConvenienceFee;
            $eventReport[$eventSummaryIndex]['pos_discount'] = $totalPosDiscount;
        }

        return response()->json(['data' => $eventReport], 200);
    }

    public function AgentReport(Request $request)
    {
        $loggedInUser = Auth::user();
        $agents = [];

        if ($loggedInUser->hasRole('Admin')) {
            $agents = User::whereHas('roles', function ($query) {
                $query->where('name', 'Agent');
            })
                ->with(['agentBookingNew', 'reportingUser'])
                ->get();
        } else {
            $agents = $loggedInUser->usersUnder()->whereHas('roles', function ($query) {
                $query->where('name', 'Agent');
            })
                ->with('agentBookingNew')
                ->get();
        }


        if ($request->has('date')) {
            $dates = $request->date ? explode(',', $request->date) : null;

            if (count($dates) === 1) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[0])->endOfDay();
            } elseif (count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            } else {
                return response()->json(['status' => 'false', 'message' => 'Invalid date format'], 400);
            }
        } else {
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        }

        $report = $agents->map(function ($agent) use ($startDate, $endDate) {
            $totalUPI = 0;
            $totalCash = 0;
            $totalNetBanking = 0;
            $totalUPIAmount = 0.0;
            $totalCashAmount = 0.0;
            $totalNetBankingAmount = 0.0;
            $totalDiscount = 0.0;
            $totalFilteredDatesAmount = 0.0;
            $filteredDatesBookingCount = 0;
            $totalTodayAmount = 0.0;
            $todayBookingCount = 0;
            $today = Carbon::today();

            foreach ($agent->agentBookingNew as $booking) {

                if ($booking->created_at >= $startDate && $booking->created_at <= $endDate) {
                    switch ($booking->payment_method) {
                        case 'UPI':
                            $totalUPI++;
                            $totalUPIAmount += $booking->amount;
                            break;
                        case 'Cash':
                            $totalCash++;
                            $totalCashAmount += $booking->amount;
                            break;
                        case 'Net Banking':
                            $totalNetBanking++;
                            $totalNetBankingAmount += $booking->amount;
                            break;
                    }

                    // $totalFilteredDatesAmount += $booking->amount;
                    // $filteredDatesBookingCount++;
                    if ($booking->created_at->isSameDay($today)) {
                        $totalTodayAmount += $booking->amount;
                        $todayBookingCount++;
                    }
                    $totalDiscount += $booking->discount;
                }
            }

            return [
                'agent_name' => $agent->name,
                'booking_count' => $filteredDatesBookingCount,
                'organizer_name' => $agent->reportingUser ? $agent->reportingUser->name : 'N/A',
                'total_UPI_bookings' => $totalUPI,
                'total_Cash_bookings' => $totalCash,
                'total_Net_Banking_bookings' => $totalNetBanking,
                'total_bookings' => $totalNetBanking + $totalCash + $totalUPI,
                'total_UPI_amount' => $totalUPIAmount,
                'total_Cash_amount' => $totalCashAmount,
                'total_Net_Banking_amount' => $totalNetBankingAmount,
                'total_amount' => $totalNetBankingAmount + $totalCashAmount + $totalUPIAmount,
                'total_discount' => $totalDiscount,
                'filtered_dates_total_amount' => $totalFilteredDatesAmount,
                'filtered_dates_booking_count' => $filteredDatesBookingCount,
                'today_total_amount' => $totalTodayAmount,
                'today_booking_count' => $todayBookingCount
            ];
        });

        return response()->json([
            'data' => $report,
        ]);
    }

    public function SponsorReport(Request $request)
    {
        $loggedInUser = Auth::user();
        $sponsors = [];

        if ($loggedInUser->hasRole('Admin')) {
            $sponsors = User::whereHas('roles', function ($query) {
                $query->where('name', 'Sponsor');
            })
                ->with(['sponsorBookingNew', 'reportingUser'])
                ->get();
        } else {
            $sponsors = $loggedInUser->usersUnder()->whereHas('roles', function ($query) {
                $query->where('name', 'Sponsor');
            })
                ->with('sponsorBookingNew')
                ->get();
        }


        if ($request->has('date')) {
            $dates = $request->date ? explode(',', $request->date) : null;

            if (count($dates) === 1) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[0])->endOfDay();
            } elseif (count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            } else {
                return response()->json(['status' => 'false', 'message' => 'Invalid date format'], 400);
            }
        } else {
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        }

        $report = $sponsors->map(function ($sponsor) use ($startDate, $endDate) {
            $totalUPI = 0;
            $totalCash = 0;
            $totalNetBanking = 0;
            $totalUPIAmount = 0.0;
            $totalCashAmount = 0.0;
            $totalNetBankingAmount = 0.0;
            $totalDiscount = 0.0;
            $totalFilteredDatesAmount = 0.0;
            $filteredDatesBookingCount = 0;
            $totalTodayAmount = 0.0;
            $todayBookingCount = 0;
            $today = Carbon::today();

            foreach ($sponsor->sponsorBookingNew as $booking) {

                if ($booking->created_at >= $startDate && $booking->created_at <= $endDate) {
                    switch ($booking->payment_method) {
                        case 'UPI':
                            $totalUPI++;
                            $totalUPIAmount += $booking->amount;
                            break;
                        case 'Cash':
                            $totalCash++;
                            $totalCashAmount += $booking->amount;
                            break;
                        case 'Net Banking':
                            $totalNetBanking++;
                            $totalNetBankingAmount += $booking->amount;
                            break;
                    }

                    // $totalFilteredDatesAmount += $booking->amount;
                    // $filteredDatesBookingCount++;
                    if ($booking->created_at->isSameDay($today)) {
                        $totalTodayAmount += $booking->amount;
                        $todayBookingCount++;
                    }
                    $totalDiscount += $booking->discount;
                }
            }

            return [
                'sponsor_name' => $sponsor->name,
                'booking_count' => $filteredDatesBookingCount,
                'organizer_name' => $sponsor->reportingUser ? $sponsor->reportingUser->name : 'N/A',
                'total_UPI_bookings' => $totalUPI,
                'total_Cash_bookings' => $totalCash,
                'total_Net_Banking_bookings' => $totalNetBanking,
                'total_bookings' => $totalNetBanking + $totalCash + $totalUPI,
                'total_UPI_amount' => $totalUPIAmount,
                'total_Cash_amount' => $totalCashAmount,
                'total_Net_Banking_amount' => $totalNetBankingAmount,
                'total_amount' => $totalNetBankingAmount + $totalCashAmount + $totalUPIAmount,
                'total_discount' => $totalDiscount,
                'filtered_dates_total_amount' => $totalFilteredDatesAmount,
                'filtered_dates_booking_count' => $filteredDatesBookingCount,
                'today_total_amount' => $totalTodayAmount,
                'today_booking_count' => $todayBookingCount
            ];
        });

        return response()->json([
            'data' => $report,
        ]);
    }

    public function AccreditationReport(Request $request)
    {
        $loggedInUser = Auth::user();
        $sponsors = [];

        if ($loggedInUser->hasRole('Admin')) {
            $sponsors = User::whereHas('roles', function ($query) {
                $query->where('name', 'Sponsor');
            })
                ->with(['AccreditationBookingNew', 'reportingUser'])
                ->get();
        } else {
            $sponsors = $loggedInUser->usersUnder()->whereHas('roles', function ($query) {
                $query->where('name', 'Sponsor');
            })
                ->with('AccreditationBookingNew')
                ->get();
        }


        if ($request->has('date')) {
            $dates = $request->date ? explode(',', $request->date) : null;

            if (count($dates) === 1) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[0])->endOfDay();
            } elseif (count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            } else {
                return response()->json(['status' => 'false', 'message' => 'Invalid date format'], 400);
            }
        } else {
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        }

        $report = $sponsors->map(function ($sponsor) use ($startDate, $endDate) {
            $totalUPI = 0;
            $totalCash = 0;
            $totalNetBanking = 0;
            $totalUPIAmount = 0.0;
            $totalCashAmount = 0.0;
            $totalNetBankingAmount = 0.0;
            $totalDiscount = 0.0;
            $totalFilteredDatesAmount = 0.0;
            $filteredDatesBookingCount = 0;
            $totalTodayAmount = 0.0;
            $todayBookingCount = 0;
            $today = Carbon::today();

            foreach ($sponsor->AccreditationBookingNew as $booking) {

                if ($booking->created_at >= $startDate && $booking->created_at <= $endDate) {
                    switch ($booking->payment_method) {
                        case 'UPI':
                            $totalUPI++;
                            $totalUPIAmount += $booking->amount;
                            break;
                        case 'Cash':
                            $totalCash++;
                            $totalCashAmount += $booking->amount;
                            break;
                        case 'Net Banking':
                            $totalNetBanking++;
                            $totalNetBankingAmount += $booking->amount;
                            break;
                    }

                    // $totalFilteredDatesAmount += $booking->amount;
                    // $filteredDatesBookingCount++;
                    if ($booking->created_at->isSameDay($today)) {
                        $totalTodayAmount += $booking->amount;
                        $todayBookingCount++;
                    }
                    $totalDiscount += $booking->discount;
                }
            }

            return [
                'sponsor_name' => $sponsor->name,
                'booking_count' => $filteredDatesBookingCount,
                'organizer_name' => $sponsor->reportingUser ? $sponsor->reportingUser->name : 'N/A',
                'total_UPI_bookings' => $totalUPI,
                'total_Cash_bookings' => $totalCash,
                'total_Net_Banking_bookings' => $totalNetBanking,
                'total_bookings' => $totalNetBanking + $totalCash + $totalUPI,
                'total_UPI_amount' => $totalUPIAmount,
                'total_Cash_amount' => $totalCashAmount,
                'total_Net_Banking_amount' => $totalNetBankingAmount,
                'total_amount' => $totalNetBankingAmount + $totalCashAmount + $totalUPIAmount,
                'total_discount' => $totalDiscount,
                'filtered_dates_total_amount' => $totalFilteredDatesAmount,
                'filtered_dates_booking_count' => $filteredDatesBookingCount,
                'today_total_amount' => $totalTodayAmount,
                'today_booking_count' => $todayBookingCount
            ];
        });

        return response()->json([
            'data' => $report,
        ]);
    }

    public function PosReport(Request $request)
    {
        $loggedInUser = Auth::user();

        if ($request->has('date')) {
            $dates = $request->date ? explode(',', $request->date) : null;

            if (count($dates) === 1) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[0])->endOfDay();
            } elseif (count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            } else {
                return response()->json(['status' => 'false', 'message' => 'Invalid date format'], 400);
            }
        } else {
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        }

        // Fetch users based on roles and relationships
        if ($loggedInUser->hasRole('Admin')) {
            $underUsers = User::whereHas('roles', function ($query) {
                $query->where('name', 'POS');
            })
                ->withCount(['PosBooking' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }])
                ->with(['PosBooking' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }, 'reportingUser'])
                ->get();
        } else {
            $underUsers = $loggedInUser->usersUnder()->whereHas('roles', function ($query) {
                $query->where('name', 'POS');
            })
                ->withCount(['PosBooking' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }])
                ->with(['PosBooking' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }, 'reportingUser'])
                ->get();
        }

        // Map the results to a report format
        $report = $underUsers->map(function ($user) {
            return [
                'pos_user_name' => $user->name,
                'organizer_name' => $user->reportingUser->name ?? 'N/A',
                'booking_count' => $user->PosBooking->sum('quantity'),
                // 'booking_count' => $user->pos_booking_count,
                'total_amount' => $user->PosBooking->sum('amount'),
                'total_discount' => $user->PosBooking->sum('discount'),
                'total_amount' => $user->PosBooking->sum('amount'),
                'total_upi_amount' => $user->PosBooking->where('payment_method', 'UPI')->sum('amount'),
                // 'total_cash_amount' => $user->PosBooking->where('payment_method', 'UPI')->sum('amount'),
                'total_net_banking_amount' => $user->PosBooking->where('payment_method', 'Net Banking')->sum('amount'),
                'mode' => $user->PosBooking->where('payment_method', 'NULL')->sum('amount'),
            ];
        });

        return response()->json(['data' => $report]);
    }


    public function exportEventReport(Request $request)
    {
        $eventName = $request->input('id');
        $organizer = $request->input('user_id');
        $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

        $query = Event::with([
            'tickets.bookings' => function ($query) {
                $query->select('id', 'ticket_id', 'user_id', 'status', 'base_amount', 'amount', 'convenience_fee', 'discount', 'created_at');
            },
            'tickets.posBookings' => function ($query) {
                $query->select('id', 'ticket_id', 'quantity', 'status', 'base_amount', 'amount', 'convenience_fee', 'discount', 'created_at');
            },
            'tickets.agentBooking' => function ($query) {
                $query->select('id', 'ticket_id', 'user_id', 'status', 'base_amount', 'amount', 'convenience_fee', 'discount', 'created_at');
            },
            'user'
        ])->select(['id', 'name as event_name', 'user_id']);

        if ($eventName) {
            $query->where('id', $eventName);
        }

        if ($organizer) {
            $query->where('user_id', $organizer);
        }

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

        $events = $query->get();

        $eventReport = [];

        foreach ($events as $event) {
            $totalTickets = $event->tickets->sum('ticket_quantity');
            $totalEventBookings = 0;
            $totalAgentBookings = 0;
            $totalNonAgentBookings = 0;
            $totalPosQuantity = 0;
            $totalIns = 0;

            $totalOnlineBaseAmount = 0;
            $totalOnlineConvenienceFee = 0;
            $totalOnlineDiscount = 0;

            $totalAgentBaseAmount = 0;
            $totalAgentConvenienceFee = 0;
            $totalAgentDiscount = 0;

            $totalPosBaseAmount = 0;
            $totalPosConvenienceFee = 0;
            $totalPosDiscount = 0;

            $eventSummaryIndex = count($eventReport);
            $eventReport[] = [
                'event_name' => $event->event_name,
                'ticket_quantity' => $totalTickets,
                'total_bookings' => 0,
                'agent_bookings' => 0,
                'non_agent_bookings' => 0,
                'pos_bookings_quantity' => 0,
                'total_ins' => 0,
                'online_base_amount' => 0,
                'online_convenience_fee' => 0,
                'online_discount' => 0,
                'agent_base_amount' => 0,
                'agent_convenience_fee' => 0,
                'agent_discount' => 0,
                'pos_base_amount' => 0,
                'pos_convenience_fee' => 0,
                'pos_discount' => 0,
                'organizer' => $event->user->name ?? 'N/A',
                'parent' => true
            ];

            foreach ($event->tickets as $ticket) {
                $totalTicketBookings = $ticket->bookings->count();
                $totalPosQuantityForTicket = $ticket->posBookings->sum('quantity');
                $totalTicketCheckIns = $ticket->bookings->where('status', 1)->count();

                $totalPosCheckInsForTicket = $ticket->posBookings->where('status', 1)->sum('quantity');

                $totalEventBookings += (float)$totalTicketBookings;
                $totalPosQuantity += (float)$totalPosQuantityForTicket;
                $totalIns += (float) ($totalTicketCheckIns + $totalPosCheckInsForTicket);

                $onlineBookings = $ticket->bookings;

                $totalOnlineBaseAmount += (float)$onlineBookings->sum('amount');
                $totalOnlineConvenienceFee += (float)$onlineBookings->sum('convenience_fee');
                $totalOnlineDiscount += (float)$onlineBookings->sum('discount');

                $agentBookings = $ticket->agentBooking;
                $totalAgentBaseAmount += (float)$agentBookings->sum('amount');
                $totalAgentConvenienceFee += (float)$agentBookings->sum('convenience_fee');
                $totalAgentDiscount += (float)$agentBookings->sum('discount');

                $totalPosBaseAmount += (float)$ticket->posBookings->sum('amount');
                $totalPosConvenienceFee += (float)$ticket->posBookings->sum('convenience_fee');
                $totalPosDiscount += (float)$ticket->posBookings->sum('discount');

                $agentBookingsCount = $agentBookings->count();
                $nonAgentBookingsCount = $onlineBookings->count();

                $eventReport[] = [
                    'event_name' => $event->event_name . ' (' . $ticket->name . ')',
                    'organizer' => '-',
                    'ticket_quantity' => $ticket->ticket_quantity,
                    'total_bookings' => $totalTicketBookings,
                    'agent_bookings' => $agentBookingsCount,
                    'non_agent_bookings' => $nonAgentBookingsCount,
                    'pos_bookings_quantity' => $totalPosQuantityForTicket,
                    'total_ins' => $totalTicketCheckIns + $totalPosCheckInsForTicket,
                    'online_base_amount' => $onlineBookings->sum('amount'),
                    'online_convenience_fee' => $onlineBookings->sum('convenience_fee'),
                    'online_discount' => $onlineBookings->sum('discount'),
                    'agent_base_amount' => $agentBookings->sum('amount'),
                    'agent_convenience_fee' => $agentBookings->sum('convenience_fee'),
                    'agent_discount' => $agentBookings->sum('discount'),
                    'pos_base_amount' => $ticket->posBookings->sum('amount'),
                    'pos_convenience_fee' => $ticket->posBookings->sum('convenience_fee'),
                    'pos_discount' => $ticket->posBookings->sum('discount'),
                    'parent' => false
                ];
            }

            // Update totals in the main event entry
            $eventReport[$eventSummaryIndex]['total_bookings'] = $totalEventBookings;
            $eventReport[$eventSummaryIndex]['agent_bookings'] = $totalAgentBookings;
            $eventReport[$eventSummaryIndex]['non_agent_bookings'] = $totalNonAgentBookings;
            $eventReport[$eventSummaryIndex]['pos_bookings_quantity'] = $totalPosQuantity;
            $eventReport[$eventSummaryIndex]['total_ins'] = $totalIns;
            $eventReport[$eventSummaryIndex]['online_base_amount'] = $totalOnlineBaseAmount;
            $eventReport[$eventSummaryIndex]['online_convenience_fee'] = $totalOnlineConvenienceFee;
            $eventReport[$eventSummaryIndex]['online_discount'] = $totalOnlineDiscount;
            $eventReport[$eventSummaryIndex]['agent_base_amount'] = $totalAgentBaseAmount;
            $eventReport[$eventSummaryIndex]['agent_convenience_fee'] = $totalAgentConvenienceFee;
            $eventReport[$eventSummaryIndex]['agent_discount'] = $totalAgentDiscount;
            $eventReport[$eventSummaryIndex]['pos_base_amount'] = $totalPosBaseAmount;
            $eventReport[$eventSummaryIndex]['pos_convenience_fee'] = $totalPosConvenienceFee;
            $eventReport[$eventSummaryIndex]['pos_discount'] = $totalPosDiscount;
        }
        // return response()->json(['exportEvent' => $eventReport]);
        return Excel::download(new EventReportExport($eventReport), 'Event_Report.xlsx');
    }

    
    public function orgListReport(Request $request)
    {
        // Get request parameters
        $search = $request->input('search');
        $sort = $request->input('sort', 'created_at'); // default sort by created_at
        $order = $request->input('order', 'desc'); // default order desc
        $perPage = $request->input('per_page', 10); // default 10 items per page

        $query = User::whereHas('roles', function ($query) {
            $query->where('name', 'Organizer');
        })->withCount('eventsOrg');

        // Apply search filter if provided
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $validSortColumns = ['id', 'name', 'email', 'number', 'created_at', 'events_org_count'];
        $sort = in_array($sort, $validSortColumns) ? $sort : 'created_at';
        $order = in_array(strtolower($order), ['asc', 'desc']) ? $order : 'desc';

        $query->orderBy($sort, $order);

        // Paginate the results
        $organizers = $query->paginate($perPage);

        //total organizer count
        $totalOrganizerCount = User::whereHas('roles', function ($q) {
            $q->where('name', 'Organizer');
        })->count();

        //total event count
        $totalEventCount = Event::whereIn('user_id', $organizers->pluck('id'))->count();

        //total ticket count
        $eventIds = Event::whereIn('user_id', $organizers->pluck('id'))->pluck('id');
        $totalTicketCount = Ticket::whereIn('event_id', $eventIds)->count();

        //total bookings count
        $eventIds = $organizers->pluck('eventsOrg')->flatten()->pluck('id');
        $ticketIds = Ticket::whereIn('event_id', $eventIds)->pluck('id');

        $bookingTickets = Booking::whereIn('ticket_id', $ticketIds)->pluck('ticket_id')->count();
        $agentTickets = Agent::whereIn('ticket_id', $ticketIds)->pluck('ticket_id')->count();
        $posTickets = PosBooking::whereIn('ticket_id', $ticketIds)->pluck('ticket_id')->count();
        $sponsorTickets = SponsorBooking::whereIn('ticket_id', $ticketIds)->pluck('ticket_id')->count();
        $corporateTickets = CorporateBooking::whereIn('ticket_id', $ticketIds)->pluck('ticket_id')->count();


        // Step 5: Count total distinct tickets used
        $totalBookedbookings = $bookingTickets + $agentTickets + $posTickets + $sponsorTickets + $corporateTickets;
        // Transform the collection
        $transformedOrganizers = $organizers->getCollection()->map(function ($organizer) {
            return [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'number' => $organizer->number,
                'email' => $organizer->email,
                'created_at' => $organizer->created_at->format('Y-m-d'),
                'event_count' => $organizer->events_org_count
            ];
        });

        // Return paginated response
        return response()->json([
            'status' => true,
            'message' => 'Organizer list fetched successfully.',
            'data' => $transformedOrganizers,
            'totalOrganizerCount' => $totalOrganizerCount,
            'totalEventCount' => $totalEventCount,
            'totalTicketCount' => $totalTicketCount,
            'totalBookedbookings' => $totalBookedbookings,
            'pagination' => [
                'total' => $organizers->total(),
                'per_page' => $organizers->perPage(),
                'current_page' => $organizers->currentPage(),
                'last_page' => $organizers->lastPage(),
                'from' => $organizers->firstItem(),
                'to' => $organizers->lastItem()
            ]
        ], 200);
    }


   
    public function organizerEventsReport(Request $request)
    {
        // Validate organizer_id is provided
        $request->validate([
            'organizer_id' => 'required|exists:users,id'
        ]);
    
        // Get request parameters
        $search = $request->input('search');
        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $perPage = $request->input('per_page', 5);
        $dateRange = $request->input('date');
    
        // Parse date range
        $startDate = null;
        $endDate = null;
    
        if ($dateRange) {
            $dates = explode(',', $dateRange);
            try {
                if (count($dates) === 1) {
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[0])->endOfDay();
                } elseif (count($dates) === 2) {
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[1])->endOfDay();
                } else {
                    return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
                }
            } catch (\Exception $e) {
                return response()->json(['status' => false, 'message' => 'Invalid date value'], 400);
            }
        }
    
        // Get the organizer
        $organizer = User::with('roles')->findOrFail($request->organizer_id);
    
        // Verify user has permission
        $loggedInUser = Auth::user();
        if (!$loggedInUser->hasRole('Admin')) {
            $allowedOrganizerIds = $loggedInUser->usersUnder()->pluck('id');
            if (!$allowedOrganizerIds->contains($organizer->id)) {
                return response()->json(['status' => false, 'message' => 'Unauthorized'], 403);
            }
        }
    
        // Build events query
        $eventsQuery = $organizer->events();
    
        if ($search) {
            $eventsQuery->where('name', 'like', "%{$search}%");
        }
    
        if ($startDate && $endDate) {
            $eventsQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
    
        // Apply sorting
        $validSortColumns = ['name', 'created_at', 'start_date', 'end_date'];
        $sort = in_array($sort, $validSortColumns) ? $sort : 'created_at';
        $order = in_array(strtolower($order), ['asc', 'desc']) ? $order : 'desc';
    
        $eventsQuery->orderBy($sort, $order);
    
        // Paginate the results
        $events = $eventsQuery->paginate($perPage);
    
        // Transform the events with detailed reporting data
        $transformedEvents = $events->getCollection()->map(function ($event) use ($startDate, $endDate, $organizer) {
            $ticketIds = $event->tickets->pluck('id')->toArray();
    
            $filterBetween = function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    return $query->whereBetween('created_at', [$startDate, $endDate]);
                }
                return $query;
            };
    
            // Get all bookings by type
            $onlineBookings = $filterBetween(Booking::whereIn('ticket_id', $ticketIds))->get();
            $agentBookings = $filterBetween(Agent::whereIn('ticket_id', $ticketIds))->get();
            $posBookings = $filterBetween(PosBooking::whereIn('ticket_id', $ticketIds))->get();
            $sponsorBookings = $filterBetween(SponsorBooking::whereIn('ticket_id', $ticketIds))->get();
            $corporateBookings = $filterBetween(CorporateBooking::whereIn('ticket_id', $ticketIds))->get();
    
            // Process agents
            $agents = $agentBookings->groupBy('user_id')->map(function ($bookings, $userId) {
                $user = $bookings->first()->user;
                $paymentMethods = $bookings->groupBy('payment_method')->map->count();
                
                return [
                    'type' => 'agent',
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->number,
                    'booking_count' => $bookings->count(),
                    'ticket_types' => $bookings->groupBy('ticket_id')->map(function ($ticketBookings) {
                        return [
                            'ticket_name' => $ticketBookings->first()->ticket->name,
                            'count' => $ticketBookings->count()
                        ];
                    })->values(),
                    'payment_methods' => $paymentMethods
                ];
            })->values();
    
            // Process POS
            $pos = $posBookings->groupBy('user_id')->map(function ($bookings, $userId) {
                $user = $bookings->first()->user;
                $paymentMethods = $bookings->groupBy('payment_method')->map->count();
                
                return [
                    'type' => 'pos',
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->number,
                    'booking_count' => $bookings->count(),
                    'ticket_types' => $bookings->groupBy('ticket_id')->map(function ($ticketBookings) {
                        return [
                            'ticket_name' => $ticketBookings->first()->ticket->name,
                            'count' => $ticketBookings->count()
                        ];
                    })->values(),
                    'payment_methods' => $paymentMethods
                ];
            })->values();
    
            // Process sponsors
            $sponsors = $sponsorBookings->groupBy('user_id')->map(function ($bookings, $userId) {
                $user = $bookings->first()->user;
                $paymentMethods = $bookings->groupBy('payment_method')->map->count();
                
                return [
                    'type' => 'sponsor',
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->number,
                    'booking_count' => $bookings->count(),
                    'ticket_types' => $bookings->groupBy('ticket_id')->map(function ($ticketBookings) {
                        return [
                            'ticket_name' => $ticketBookings->first()->ticket->name,
                            'count' => $ticketBookings->count()
                        ];
                    })->values(),
                    'payment_methods' => $paymentMethods
                ];
            })->values();
    
            // Process corporate
            $corporate = $corporateBookings->groupBy('user_id')->map(function ($bookings, $userId) {
                $user = $bookings->first()->user;
                $paymentMethods = $bookings->groupBy('payment_method')->map->count();
                
                return [
                    'type' => 'corporate',
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->number,
                    'booking_count' => $bookings->count(),
                    'ticket_types' => $bookings->groupBy('ticket_id')->map(function ($ticketBookings) {
                        return [
                            'ticket_name' => $ticketBookings->first()->ticket->name,
                            'count' => $ticketBookings->count()
                        ];
                    })->values(),
                    'payment_methods' => $paymentMethods
                ];
            })->values();
    
            // Combine all booking sources
            $bookingSources = collect()
                ->merge($agents)
                ->merge($pos)
                ->merge($sponsors)
                ->merge($corporate);
    
            // Payment stats calculation
            $paymentMethods = ['Cash', 'UPI', 'Card', 'Net Banking', 'Other'];
            $sources = [
                'online' => $onlineBookings,
                'agent' => $agentBookings,
                'pos' => $posBookings,
                'sponsor' => $sponsorBookings,
                'corporate' => $corporateBookings
            ];
    
            $paymentAmounts = [];
            $paymentCounts = [];
            $totalDiscount = 0;
            $totalBooked = 0;
    
            foreach ($sources as $source => $bookings) {
                $paymentAmounts[$source] = array_fill_keys($paymentMethods, 0);
                $paymentCounts[$source] = array_fill_keys($paymentMethods, 0);
    
                foreach ($bookings as $booking) {
                    $method = $booking->payment_method ?? 'Other';
                    $method = in_array($method, $paymentMethods) ? $method : 'Other';
                    $amount = $booking->amount ?? 0;
                    $discount = $booking->discount ?? 0;
    
                    $paymentAmounts[$source][$method] += $amount;
                    $paymentCounts[$source][$method]++;
                    $totalDiscount += $discount;
                    $totalBooked++;
                }
            }
    
            return [
                'id' => $event->id,
                'name' => $event->name,
                'start_date' => $event->start_date,
                'end_date' => $event->end_date,
                'created_at' => $event->created_at->format('Y-m-d H:i:s'),
                'total_tickets' => $event->tickets->sum('ticket_quantity') ?? 0,
                'booked_tickets' => $totalBooked,
                'booking_sources' => $bookingSources,
                'payment_stats' => [
                    'amounts' => $paymentAmounts,
                    'counts' => $paymentCounts,
                    'total_discount' => $totalDiscount,
                ],
                'booking_counts' => [
                    'online' => $onlineBookings->count(),
                    'agent' => $agentBookings->count(),
                    'pos' => $posBookings->count(),
                    'sponsor' => $sponsorBookings->count(),
                    'corporate' => $corporateBookings->count()
                ],
                'team_counts' => [
                    'agents' => $agents->count(),
                    'pos_users' => $pos->count(),
                    'sponsors' => $sponsors->count(),
                    'corporate' => $corporate->count()
                ]
            ];
        });
    
        return response()->json([
            'status' => true,
            'message' => 'Organizer events fetched successfully',
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'email' => $organizer->email,
                'phone' => $organizer->number,
            ],
            'data' => $transformedEvents,
            'pagination' => [
                'total' => $events->total(),
                'per_page' => $events->perPage(),
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'from' => $events->firstItem(),
                'to' => $events->lastItem()
            ]
        ], 200);
    }
  
    /**
     * Export Agent Report as Excel (.xlsx)
     * Exports agent booking data with date filtering in proper Excel format
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse Excel file download
     */
        public function exportAgentReport(Request $request)
    {
        $loggedInUser = Auth::user();

        // Parse date range from request
        $startDate = Carbon::today()->startOfDay();
        $endDate = Carbon::today()->endOfDay();

        if ($request->has('date')) {
            $dates = $request->date ? explode(',', $request->date) : null;

            if ($dates && count($dates) === 1) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[0])->endOfDay();
            } elseif ($dates && count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            }
        }

        // Fetch agents based on user role
        if ($loggedInUser->hasRole('Admin')) {
            $agents = User::whereHas('roles', function ($query) {
                $query->where('name', 'Agent');
            })
                ->with(['agentBookingNew', 'reportingUser'])
                ->get();
        } else {
            $agents = $loggedInUser->usersUnder()->whereHas('roles', function ($query) {
                $query->where('name', 'Agent');
            })
                ->with('agentBookingNew')
                ->get();
        }

        // Process agent data for export
        $reportData = [];
        
        foreach ($agents as $agent) {
            $totalUPI = 0;
            $totalCash = 0;
            $totalNetBanking = 0;
            $totalUPIAmount = 0.0;
            $totalCashAmount = 0.0;
            $totalNetBankingAmount = 0.0;
            $totalDiscount = 0.0;
            $totalAmount = 0.0;
            $totalBookings = 0;

            foreach ($agent->agentBookingNew as $booking) {
                if ($booking->created_at >= $startDate && $booking->created_at <= $endDate) {
                    $totalAmount += $booking->amount ?? 0;
                    $totalBookings++;
                    $totalDiscount += $booking->discount ?? 0;

                    switch ($booking->payment_method) {
                        case 'UPI':
                            $totalUPI++;
                            $totalUPIAmount += $booking->amount ?? 0;
                            break;
                        case 'Cash':
                            $totalCash++;
                            $totalCashAmount += $booking->amount ?? 0;
                            break;
                        case 'Net Banking':
                            $totalNetBanking++;
                            $totalNetBankingAmount += $booking->amount ?? 0;
                            break;
                    }
                }
            }

            $reportData[] = [
                $agent->name,
                $agent->email,
                $agent->number,
                $agent->reportingUser ? $agent->reportingUser->name : 'N/A',
                $totalBookings,
                $totalUPI,
                $totalCash,
                $totalNetBanking,
                round($totalUPIAmount, 2),
                round($totalCashAmount, 2),
                round($totalNetBankingAmount, 2),
                round($totalAmount, 2),
                round($totalDiscount, 2),
            ];
        }

        // Create Excel file using Maatwebsite\Excel with proper structure
        return Excel::download(
            new class($reportData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function array(): array
                {
                    return $this->data;
                }

                public function headings(): array
                {
                    return [
                        'Agent Name',
                        'Email',
                        'Phone',
                        'Organizer',
                        'Total Bookings',
                        'UPI Bookings',
                        'Cash Bookings',
                        'Net Banking Bookings',
                        'UPI Amount',
                        'Cash Amount',
                        'Net Banking Amount',
                        'Total Amount',
                        'Total Discount',
                    ];
                }
            },
            'Agent_Report_' . now()->format('Y-m-d_H-i-s') . '.xlsx'
        );
    }

}
