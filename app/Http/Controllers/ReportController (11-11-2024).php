<?php

namespace App\Http\Controllers;

use App\Exports\AgentReportExport;
use App\Exports\EventReportExport;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
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

        $eventsQuery = Event::with([
            'tickets.bookings' => function ($query) use ($startDate, $endDate) {
                $query->select('id', 'ticket_id', 'user_id', 'gateway','status', 'base_amount', 'amount', 'convenience_fee', 'discount', 'created_at', 'agent_id');

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

            foreach ($event->tickets as $ticket) {
                $totalTicketBookings = $ticket->bookings->count();

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
                $agentBookings = $ticket->bookings->whereNotNull('agent_id');

                $agentBookingsCount = $agentBookings->count();
                $nonAgentBookingsCount = $onlineBookings->count() - $agentBookingsCount;

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
    
    // public function EventReport(Request $request)
    // {
    //     if ($request->has('date')) {
    //         $dates = $request->date ? explode(',', $request->date) : null;

    //         if (count($dates) === 1) {
    //             $startDate = Carbon::parse($dates[0])->startOfDay();
    //             $endDate = Carbon::parse($dates[0])->endOfDay();
    //         } elseif (count($dates) === 2) {
    //             $startDate = Carbon::parse($dates[0])->startOfDay();
    //             $endDate = Carbon::parse($dates[1])->endOfDay();
    //         } else {
    //             return response()->json(['status' => 'false', 'message' => 'Invalid date format'], 400);
    //         }
    //     } else {
    //         $startDate = Carbon::today()->startOfDay();
    //         $endDate = Carbon::today()->endOfDay();
    //     }

    //     $eventType = $request->type;

    //     $eventsQuery = Event::with([
    //         'tickets.bookings' => function ($query) use ($startDate, $endDate) {
    //             $query->select('id', 'ticket_id', 'user_id', 'status', 'base_amount', 'amount', 'convenience_fee', 'discount', 'created_at', 'agent_id');

    //             if ($startDate && $endDate) {
    //                 $query->whereBetween('created_at', [$startDate, $endDate]);
    //             }
    //         },
    //         'tickets.posBookings' => function ($query) use ($startDate, $endDate) {
    //             $query->select('id', 'ticket_id', 'quantity', 'status', 'base_amount', 'amount', 'convenience_fee', 'discount', 'created_at');

    //             if ($startDate && $endDate) {
    //                 $query->whereBetween('created_at', [$startDate, $endDate]);
    //             }
    //         },
    //         'tickets.agentBooking' => function ($query) use ($startDate, $endDate) {
    //             $query->select('id', 'ticket_id', 'user_id', 'status', 'base_amount', 'amount', 'convenience_fee', 'discount', 'created_at');

    //             if ($startDate && $endDate) {
    //                 $query->whereBetween('created_at', [$startDate, $endDate]);
    //             }
    //         },
    //         'user'
    //     ]);

    //     $today = Carbon::today()->toDateString();
    //     if ($eventType == 'all') {
    //         $events = $eventsQuery->get(['id', 'name as event_name', 'user_id']);

    //     } else {
    //         $events = $eventsQuery->where(function ($query) use ($today) {
    //             $query->where('date_range', 'LIKE', "%$today%")
    //                 ->orWhereRaw("? BETWEEN SUBSTRING_INDEX(date_range, ',', 1) AND SUBSTRING_INDEX(date_range, ',', -1)", [$today]);
    //         })->get(['id', 'name as event_name', 'user_id']);
    //     }

    //     $eventReport = [];

    //     foreach ($events as $event) {
    //         $totalTickets = (int)$event->tickets->sum('ticket_quantity');
    //         $totalEventBookings = 0;
    //         $totalAgentBookings = 0;
    //         $totalNonAgentBookings = 0;
    //         $nonAgentBookingsCount = 0;
    //         $totalPosQuantity = 0;
    //         $totalIns = 0;

    //         $totalOnlineBaseAmount = 0;
    //         $totalOnlineConvenienceFee = 0;
    //         $totalOnlineDiscount = 0;

    //         $totalAgentBaseAmount = 0;
    //         $totalAgentConvenienceFee = 0;
    //         $totalAgentDiscount = 0;

    //         $totalPosBaseAmount = 0;
    //         $totalPosConvenienceFee = 0;
    //         $totalPosDiscount = 0;

    //         $eventSummaryIndex = count($eventReport);
    //         $eventReport[] = [
    //             'event_name' => $event->event_name,
    //             'ticket_quantity' => $totalTickets,
    //             'total_bookings' => 0,
    //             'agent_bookings' => 0,
    //             'non_agent_bookings' => 0,
    //             'pos_bookings_quantity' => 0,
    //             'total_ins' => 0,
    //             'online_base_amount' => 0,
    //             'online_convenience_fee' => 0,
    //             'online_discount' => 0,
    //             'agent_base_amount' => 0,
    //             'agent_convenience_fee' => 0,
    //             'agent_discount' => 0,
    //             'pos_base_amount' => 0,
    //             'pos_convenience_fee' => 0,
    //             'pos_discount' => 0,
    //             'organizer' => $event->user->name ?? 'N/A',
    //             'parent' => true
    //         ];

    //         foreach ($event->tickets as $ticket) {
    //             $totalTicketBookings = $ticket->bookings->count();
    //             $totalPosQuantityForTicket = (int)$ticket->posBookings->sum('quantity');
    //             $totalTicketCheckIns = $ticket->bookings->where('status', 1)->count();

    //             $totalPosCheckInsForTicket = (int)$ticket->posBookings->where('status', 1)->sum('quantity');
    //             $onlineBookings = $ticket->bookings;
    //             $agentBookings = $ticket->bookings->whereNotNull('agent_id');

    //             $agentBookingsCount = $agentBookings->count();
    //             $nonAgentBookingsCount = $onlineBookings->count() - $agentBookingsCount;

    //            // Compute sums with proper casting
    //             $onlineBaseAmount = (float)$onlineBookings->sum(function($item) {
    //                 return is_numeric($item->amount) ? $item->amount : 0;
    //             });

    //             $onlineConvenienceFee = (float)$onlineBookings->sum(function($item) {
    //                 return is_numeric($item->convenience_fee) ? $item->convenience_fee : 0;
    //             });

    //             $onlineDiscount = (float)$onlineBookings->sum(function($item) {
    //                 return is_numeric($item->discount) ? $item->discount : 0;
    //             });

    //             $agentBaseAmount = (float)$agentBookings->sum(function($item) {
    //                 return is_numeric($item->amount) ? $item->amount : 0;
    //             });

    //             $agentConvenienceFee = (float)$agentBookings->sum(function($item) {
    //                 return is_numeric($item->convenience_fee) ? $item->convenience_fee : 0;
    //             });

    //             $agentDiscount = (float)$agentBookings->sum(function($item) {
    //                 return is_numeric($item->discount) ? $item->discount : 0;
    //             });

    //             $posBaseAmount = (float)$ticket->posBookings->sum(function($item) {
    //                 return is_numeric($item->amount) ? $item->amount : 0;
    //             });

    //             $posConvenienceFee = (float)$ticket->posBookings->sum(function($item) {
    //                 return is_numeric($item->convenience_fee) ? $item->convenience_fee : 0;
    //             });

    //             $posDiscount = (float)$ticket->posBookings->sum(function($item) {
    //                 return is_numeric($item->discount) ? $item->discount : 0;
    //             });


    //             // return response()->json([$onlineBaseAmount,$onlineConvenienceFee,$onlineDiscount,$agentBaseAmount,$agentConvenienceFee,$agentDiscount,$posBaseAmount,$posConvenienceFee,$posDiscount]);         

    //             // Update running totals
    //             $totalEventBookings += $totalTicketBookings;
    //             $totalAgentBookings += $agentBookingsCount;
    //             $totalNonAgentBookings += $nonAgentBookingsCount;
    //             $totalPosQuantity += $totalPosQuantityForTicket;
    //             $totalIns += $totalTicketCheckIns + $totalPosCheckInsForTicket;

    //             $totalOnlineBaseAmount += $onlineBaseAmount;
    //             $totalOnlineConvenienceFee += $onlineConvenienceFee;
    //             $totalOnlineDiscount += $onlineDiscount;

    //             $totalAgentBaseAmount += $agentBaseAmount;
    //             $totalAgentConvenienceFee += $agentConvenienceFee;
    //             $totalAgentDiscount += $agentDiscount;

    //             $totalPosBaseAmount += $posBaseAmount;
    //             $totalPosConvenienceFee += $posConvenienceFee;
    //             $totalPosDiscount += $posDiscount;

    //             $eventReport[] = [
    //                 'event_name' => $event->event_name . ' (' . $ticket->name . ')',
    //                 'organizer' => '-',
    //                 'ticket_quantity' => (int)$ticket->ticket_quantity,
    //                 'total_bookings' => $totalTicketBookings,
    //                 'agent_bookings' => $agentBookingsCount,
    //                 'non_agent_bookings' => $nonAgentBookingsCount,
    //                 'pos_bookings_quantity' => $totalPosQuantityForTicket,
    //                 'total_ins' => $totalTicketCheckIns + $totalPosCheckInsForTicket,
    //                 'online_base_amount' => $onlineBaseAmount,
    //                 'online_convenience_fee' => $onlineConvenienceFee,
    //                 'online_discount' => $onlineDiscount,
    //                 'agent_base_amount' => $agentBaseAmount,
    //                 'agent_convenience_fee' => $agentConvenienceFee,
    //                 'agent_discount' => $agentDiscount,
    //                 'pos_base_amount' => $posBaseAmount,
    //                 'pos_convenience_fee' => $posConvenienceFee,
    //                 'pos_discount' => $posDiscount,
    //                 'parent' => false
    //             ];
    //         }

    //         $eventReport[$eventSummaryIndex]['total_bookings'] = $totalEventBookings;
    //         $eventReport[$eventSummaryIndex]['agent_bookings'] = $totalAgentBookings;
    //         $eventReport[$eventSummaryIndex]['non_agent_bookings'] = $totalNonAgentBookings;
    //         $eventReport[$eventSummaryIndex]['pos_bookings_quantity'] = $totalPosQuantity;
    //         $eventReport[$eventSummaryIndex]['total_ins'] = $totalIns;
    //         $eventReport[$eventSummaryIndex]['online_base_amount'] = $totalOnlineBaseAmount;
    //         $eventReport[$eventSummaryIndex]['online_convenience_fee'] = $totalOnlineConvenienceFee;
    //         $eventReport[$eventSummaryIndex]['online_discount'] = $totalOnlineDiscount;
    //         $eventReport[$eventSummaryIndex]['agent_base_amount'] = $totalAgentBaseAmount;
    //         $eventReport[$eventSummaryIndex]['agent_convenience_fee'] = $totalAgentConvenienceFee;
    //         $eventReport[$eventSummaryIndex]['agent_discount'] = $totalAgentDiscount;
    //         $eventReport[$eventSummaryIndex]['pos_base_amount'] = $totalPosBaseAmount;
    //         $eventReport[$eventSummaryIndex]['pos_convenience_fee'] = $totalPosConvenienceFee;
    //         $eventReport[$eventSummaryIndex]['pos_discount'] = $totalPosDiscount;
    //     }

    //     return response()->json(['data' => $eventReport], 200);
    // }
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
                'booking_count' => $user->pos_booking_count,
                'total_amount' => $user->PosBooking->sum('amount'),
                'total_discount' => $user->PosBooking->sum('discount'),
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
}
