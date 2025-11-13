<?php

namespace App\Http\Controllers;

use App\Exports\BookingExport;
use App\Jobs\BookingMailJob;
use App\Jobs\SendEmailJob;
use App\Mail\BookingMail;
use App\Mail\SendEmail;
use App\Models\AccessArea;
use App\Models\AccreditationBooking;
use App\Models\AccreditationMasterBooking;
use App\Models\Agent;
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
use App\Models\Event;
use App\Models\ExhibitionBooking;
use App\Models\PromoCode;
use App\Models\MasterBooking;
use App\Models\PenddingBooking;
use App\Models\PenddingBookingsMaster;
use App\Models\PosBooking;
use App\Models\SponsorBooking;
use App\Models\SponsorMasterBooking;
use App\Models\Ticket;
use App\Models\User;
use Auth;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Mail;
use Storage;

class BookingController extends Controller
{

    public function index($id)
    {
        $loggedInUser = Auth::user();
        $isAdmin = $loggedInUser->hasRole('Admin');
        $isOrganizer = $loggedInUser->hasRole('Organizer');
        $isAgent = $loggedInUser->hasRole('Agent');

        if ($isAdmin) {
            $Masterbookings = MasterBooking::withTrashed()
                ->whereNotNull('agent_id')
                ->with('agent')
                ->latest()
                ->get();
        } elseif ($isOrganizer) {
            $underUserIds = $loggedInUser->usersUnder()->pluck('id');
            $Masterbookings = MasterBooking::withTrashed()
                ->whereIn('agent_id', $underUserIds)
                ->with('agent')
                ->latest()
                ->get();
        } elseif ($isAgent) {
            $Masterbookings = MasterBooking::withTrashed()
                ->where('agent_id', $id)
                ->with('agent')
                ->latest()
                ->get();
        } else {
            $Masterbookings = MasterBooking::withTrashed()
                ->where('user_id', $id)
                ->latest()
                ->get();
        }

        $allBookingIds = [];
        $Masterbookings->each(function ($masterBooking) use (&$allBookingIds) {
            $bookingIds = $masterBooking->booking_id;
            if (is_array($bookingIds)) {
                $allBookingIds = array_merge($allBookingIds, $bookingIds);
                $masterBooking->bookings = Booking::whereIn('id', $bookingIds)
                    ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation'])
                    ->latest()
                    ->get();
            } else {
                $masterBooking->bookings = collect();
            }
        })->map(function ($booking) {
            $booking->is_deleted = $booking->trashed();
            return $booking;
        });

        // Normal bookings retrieval logic
        if ($isAdmin) {
            $normalBookings = Booking::withTrashed()
                ->whereNotNull('agent_id')
                ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation', 'agent'])
                ->latest()
                ->get()
                ->map(function ($booking) {
                    $booking->is_deleted = $booking->trashed();
                    return $booking;
                });
        } elseif ($isOrganizer) {
            $underUserIds = $loggedInUser->usersUnder()->pluck('id');
            $normalBookings = Booking::withTrashed()
                ->whereIn('agent_id', $underUserIds)
                ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation', 'agent'])
                ->latest()
                ->get()
                ->map(function ($booking) {
                    $booking->is_deleted = $booking->trashed();
                    return $booking;
                });
        } elseif ($isAgent) {
            $normalBookings = Booking::withTrashed()
                ->where('agent_id', $id)
                ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation', 'agent'])
                ->latest()
                ->get()
                ->map(function ($booking) {
                    $booking->is_deleted = $booking->trashed();
                    return $booking;
                });
        } else {
            $normalBookings = Booking::withTrashed()
                ->where('user_id', $id)
                ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation', 'agent'])
                ->latest()
                ->get()
                ->map(function ($booking) {
                    $booking->is_deleted = $booking->trashed();
                    return $booking;
                });
        }

        $filteredNormalBookings = $normalBookings->filter(function ($booking) use ($allBookingIds) {
            return !in_array($booking->id, $allBookingIds);
        })->values();

        $combinedBookings = $Masterbookings->concat($filteredNormalBookings);
        $sortedCombinedBookings = $combinedBookings->sortByDesc('created_at')->values();

        return response()->json([
            'status' => true,
            'bookings' => $sortedCombinedBookings
        ], 200);
    }

    public function getUserBookings($userId)
    {
        $Masterbookings = MasterBooking::where('user_id', $userId)
            ->latest()
            ->get();
        $allBookingIds = [];
        $Masterbookings->each(function ($masterBooking) use (&$allBookingIds) {
            $allAttendees = [];
            $bookingIds = $masterBooking->booking_id;
            if (is_array($bookingIds)) {
                $allBookingIds = array_merge($allBookingIds, $bookingIds);
                $bookings = Booking::whereIn('id', $bookingIds)
                    ->whereNull('deleted_at')
                    ->with(['ticket.event.user', 'ticket.event.Category', 'user', 'attendee'])
                    ->latest()
                    ->get();
                $masterBooking->bookings = $bookings;
                $masterBooking->bookings->each(function ($booking) use (&$allAttendees) {
                    if ($booking->attendee) {
                        $allAttendees[] = $booking->attendee;
                    }
                    $booking->is_deleted = $booking->trashed();
                });
            } else {
                $masterBooking->bookings = collect();
            }
            $masterBooking->attendees = $allAttendees;
        })->map(function ($booking) {
            $booking->is_deleted = $booking->trashed();
            $booking->type = 'MasterBooking';
            return $booking;
        });

        //amusementMaster
        $AmusementMasterBooking = AmusementMasterBooking::where('user_id', $userId)
            ->latest()
            ->get();

        $allAmusementBookingIds = [];
        $AmusementMasterBooking->each(function ($masterBooking) use (&$allAmusementBookingIds) {
            $allAttendees = [];

            // Convert booking_id to an array
            $bookingIds = json_decode($masterBooking->booking_id, true);

            if (is_array($bookingIds)) {
                $allAmusementBookingIds = array_merge($allAmusementBookingIds, $bookingIds);
                $bookings = AmusementBooking::whereIn('id', $bookingIds)
                    ->whereNull('deleted_at')
                    ->with(['ticket.event.user', 'ticket.event.Category', 'user', 'attendee'])
                    ->latest()
                    ->get();

                $masterBooking->bookings = $bookings;
                $masterBooking->bookings->each(function ($booking) use (&$allAttendees) {
                    if ($booking->attendee) {
                        $allAttendees[] = $booking->attendee;
                    }
                    $booking->is_deleted = $booking->trashed();
                });
            } else {
                $masterBooking->bookings = collect();
            }

            $masterBooking->attendees = $allAttendees;
        })->map(function ($booking) {
            $booking->is_deleted = $booking->trashed();
            $booking->type = 'AmusementMasterBooking';
            return $booking;
        });


        //agent
        $AgentMasterbookings = AgentMaster::where('user_id', $userId)
            ->latest()
            ->get();

        $allAgentBookingIds = [];
        $AgentMasterbookings->each(function ($masterBooking) use (&$allAgentBookingIds) {
            $bookingIds = $masterBooking->booking_id;
            if (is_array($bookingIds)) {
                $allAgentBookingIds = array_merge($allAgentBookingIds, $bookingIds);
                $masterBooking->bookings = Agent::whereIn('id', $bookingIds)
                    ->whereNull('deleted_at')
                    ->with(['ticket.event.user', 'ticket.event.Category', 'user'])
                    ->latest()
                    ->get();
            } else {
                $masterBooking->bookings = collect();
            }
        })->map(function ($booking) {
            $booking->is_deleted = $booking->trashed();
            $booking->type = 'AgentMasterBooking';
            return $booking;
        });

        $normalAgentBookings = Agent::where('user_id', $userId)
            ->with(['ticket.event.user', 'ticket.event.Category', 'user'])
            ->latest()
            ->get()
            ->map(function ($booking) {
                $booking->is_deleted = $booking->trashed();
                $booking->type = 'Agent';
                return $booking;
            });

        //AccreditationBooking
        $AccreditationMasterBooking = AccreditationMasterBooking::where('user_id', $userId)
            ->latest()
            ->get();

        $allAccreditationBookingIds = [];
        $AccreditationMasterBooking->each(function ($masterBooking) use (&$allAccreditationBookingIds) {
            $bookingIds = $masterBooking->booking_id;
            if (is_array($bookingIds)) {
                $allAccreditationBookingIds = array_merge($allAccreditationBookingIds, $bookingIds);
                $masterBooking->bookings = AccreditationBooking::whereIn('id', $bookingIds)
                    ->whereNull('deleted_at')
                    ->with(['ticket.event.user', 'ticket.event.Category', 'user'])
                    ->latest()
                    ->get();
            } else {
                $masterBooking->bookings = collect();
            }
        })->map(function ($booking) {
            $booking->is_deleted = $booking->trashed();
            $booking->type = 'AccreditationBooking';
            return $booking;
        });

        $normalAccreditationBooking = AccreditationBooking::where('user_id', $userId)
            ->with(['ticket.event.user', 'ticket.event.Category', 'user'])
            ->latest()
            ->get()
            ->map(function ($booking) {
                $booking->is_deleted = $booking->trashed();
                $booking->type = 'AccreditationBooking';
                return $booking;
            });

        //SponsorBooking
        $SponsorMasterBooking = SponsorMasterBooking::where('user_id', $userId)
            ->latest()
            ->get();

        $allSponsorBookingIds = [];
        $SponsorMasterBooking->each(function ($masterBooking) use (&$allSponsorBookingIds) {
            $bookingIds = $masterBooking->booking_id;
            if (is_array($bookingIds)) {
                $allSponsorBookingIds = array_merge($allSponsorBookingIds, $bookingIds);
                $masterBooking->bookings = SponsorBooking::whereIn('id', $bookingIds)
                    ->whereNull('deleted_at')
                    ->with(['ticket.event.user', 'ticket.event.Category', 'user'])
                    ->latest()
                    ->get();
            } else {
                $masterBooking->bookings = collect();
            }
        })->map(function ($booking) {
            $booking->is_deleted = $booking->trashed();
            $booking->type = 'SponsorMasterBooking';
            return $booking;
        });

        $normalSponsorBooking = SponsorBooking::where('user_id', $userId)
            ->with(['ticket.event.user', 'ticket.event.Category', 'user'])
            ->latest()
            ->get()
            ->map(function ($booking) {
                $booking->is_deleted = $booking->trashed();
                $booking->type = 'SponsorBooking';
                return $booking;
            });

        //BOOKING
        $normalBookings = Booking::where('user_id', $userId)
            ->with(['ticket.event.user', 'ticket.event.Category', 'user', 'attendee'])
            // ->with(['ticket.event.user', 'user', 'agent'])
            ->latest()
            ->get()
            ->map(function ($booking) {
                $booking->is_deleted = $booking->trashed();
                $booking->type = 'Booking';
                return $booking;
            });
        $normalAmusementBooking = AmusementBooking::where('user_id', $userId)
            ->with(['ticket.event.user', 'ticket.event.Category', 'user', 'attendee'])
            // ->with(['ticket.event.user', 'user', 'agent'])
            ->latest()
            ->get()
            ->map(function ($booking) {
                $booking->is_deleted = $booking->trashed();
                $booking->type = 'AmusementBooking';
                return $booking;
            });

        //complimenrtry booking
        $normalComplimentaryBookings = ComplimentaryBookings::where('user_id', $userId)
            ->with(['ticket.event.user', 'ticket.event.Category', 'user'])
            // ->with(['ticket.event.user', 'user', 'agent'])
            ->latest()
            ->get()
            ->map(function ($booking) {
                $booking->is_deleted = $booking->trashed();
                $booking->type = 'ComplimentaryBookings';
                return $booking;
            });

        $combinedBookings = $Masterbookings
            ->concat($normalBookings->filter(function ($booking) use ($allBookingIds) {
                return !in_array($booking->id, $allBookingIds);
            }))
            ->concat($AmusementMasterBooking)
            ->concat($normalAmusementBooking->filter(function ($booking) use ($allAmusementBookingIds) {
                return !in_array($booking->id, $allAmusementBookingIds);
            }))
            ->concat($AgentMasterbookings)
            ->concat($normalAgentBookings->filter(function ($booking) use ($allAgentBookingIds) {
                return !in_array($booking->id, $allAgentBookingIds);
            }))
            ->concat($AccreditationMasterBooking)
            ->concat($normalAccreditationBooking->filter(function ($booking) use ($allAccreditationBookingIds) {
                return !in_array($booking->id, $allAccreditationBookingIds);
            }))
            ->concat($SponsorMasterBooking)
            ->concat($normalSponsorBooking->filter(function ($booking) use ($allSponsorBookingIds) {
                return !in_array($booking->id, $allSponsorBookingIds);
            }))
            ->concat($normalComplimentaryBookings)
            ->sortByDesc('created_at')
            ->values();


        return response()->json([
            'status' => true,
            'bookings' => $combinedBookings
        ], 200);
    }

    public function AdminBookings(Request $request, $id)
    {
        try {
            $loggedInUser = Auth::user();
            $isAdmin = $loggedInUser->hasRole('Admin');

            // Handle date filtering
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

            if ($isAdmin) {
                $Masterbookings = MasterBooking::withTrashed()
                    ->where('agent_id', null)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get();

                $allBookingIds = [];

                $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $startDate, $endDate) {
                    $bookingIds = $masterBooking->booking_id;

                    if (!empty($bookingIds)) {
                        $allBookingIds = array_merge($allBookingIds, $bookingIds);
                        $masterBooking->bookings = Booking::withTrashed()
                            ->whereIn('id', $bookingIds)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation'])
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
                    $masterBooking->payment_method = $masterBooking->bookings[0]->payment_method ?? '';
                    $masterBooking->quantity = count($masterBooking->bookings);
                    return $masterBooking;
                });

                // Fetch normal bookings that are NOT in Masterbookings
                $normalBookings = Booking::withTrashed()
                    ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation', 'attendee'])
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

                // Remove duplicate bookings
                $filteredNormalBookings = $normalBookings->reject(function ($booking) use ($allBookingIds) {
                    return in_array($booking->id, $allBookingIds);
                });

                // Combine all bookings
                $combinedBookings = $Masterbookings->concat($filteredNormalBookings);
                $sortedCombinedBookings = $combinedBookings->sortByDesc('created_at')->values();

                return response()->json([
                    'status' => true,
                    'bookings' => $sortedCombinedBookings,
                ], 200);
            } else {
                // Fetch event IDs for non-admin users
                $eventIds = Event::where('user_id', $id)->pluck('id');
                $tickets = Ticket::whereIn('event_id', $eventIds)->pluck('id');

                $Masterbookings = MasterBooking::withTrashed()
                    ->where('agent_id', null)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get();

                $allBookingIds = [];

                $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $tickets, $startDate, $endDate) {
                    $bookingIds = $masterBooking->booking_id;
                    // $bookingIds = is_string($bookingIds) && is_array(json_decode($bookingIds, true)) ? json_decode($bookingIds, true) : $bookingIds;
                    if (!empty($bookingIds)) {
                        $allBookingIds = array_merge($allBookingIds, $bookingIds);
                        $masterBooking->bookings = Booking::whereIn('id', $bookingIds)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->whereHas('ticket', function ($query) use ($tickets) {
                                $query->whereIn('id', $tickets);
                            })
                            ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation'])
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
                    $masterBooking->payment_method = $masterBooking->bookings[0]->payment_method ?? '';
                    $masterBooking->quantity = count($masterBooking->bookings);
                    return $masterBooking;
                });

                $normalBookings = Booking::withTrashed()
                    ->with(['ticket.event.user', 'user:id,name,number,email,photo,reporting_user,company_name,designation', 'attendee'])
                    ->where('agent_id', null)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereHas('ticket', function ($query) use ($tickets) {
                        $query->whereIn('id', $tickets);
                    })
                    ->latest()
                    ->get()
                    ->map(function ($booking) {
                        $booking->event_name = $booking->ticket->event->name ?? '';
                        $booking->organizer = $booking->ticket->event->user->name ?? '';
                        $booking->is_deleted = $booking->trashed();
                        $booking->quantity = 1;
                        return $booking;
                    });

                // Remove duplicate bookings
                $filteredNormalBookings = $normalBookings->reject(function ($booking) use ($allBookingIds) {
                    return in_array($booking->id, $allBookingIds);
                });

                // Combine all bookings
                $combinedBookings = $Masterbookings->concat($filteredNormalBookings);
                $sortedCombinedBookings = $combinedBookings->sortByDesc('created_at')->values();

                return response()->json([
                    'status' => true,
                    'bookings' => $sortedCombinedBookings,
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage() . " on line " . $e->getLine(),
            ], 500);
        }
    }


    public function bkpagentBooking($id)
    {
        // Define a cache key based on the agent ID
        $cacheKey = "agent_bookings_{$id}";

        // Attempt to retrieve cached data
        $bookings = Cache::remember($cacheKey, 60, function () use ($id) {
            $bookings = Booking::withTrashed()
                ->latest()
                ->where('agent_id', $id)
                ->with('ticket')
                ->get()
                ->map(function ($booking) {
                    $booking->is_deleted = $booking->trashed();
                    return $booking;
                });

            return $bookings;
        });
        $bookings = Booking::withTrashed()->latest()->where('agent_id', $id)->with('ticket')->get();
        $ActicveBookings = Booking::where('agent_id', $id)->get();
        $amount = $ActicveBookings->sum('amount');
        $discount = $ActicveBookings->sum('discount');
        $bookings = $bookings->map(function ($booking) {
            $booking->is_deleted = $booking->trashed();
            return $booking;
        });
        if ($bookings) {
            return response()->json(['status' => true, 'bookings' => $bookings, 'amount' => $amount, 'discount' => $discount], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'No Bookings Found'], 404);
        }
    }

    public function agentBooking($id)
    {
        // Define a cache key based on the agent ID
        $cacheKey = "agent_bookings_{$id}";
        $user = Auth::user();
        $isOrganizer = $user->hasRole('Organizer');
        $isAdmin = $user->hasRole('Admin');
        if ($isAdmin) {
            $allBookings = Agent::withTrashed()
                ->latest()
                ->with('ticket')
                ->get();
            $Masterbookings = AgentMaster::withTrashed()
                ->latest()->get();
        } elseif ($isOrganizer) {
            $agentIds = $user->usersUnder()->pluck('id');
            $allBookings = Agent::withTrashed()
                ->latest()
                ->whereIn('agent_id', $agentIds)
                ->with('ticket')
                ->get();
            $Masterbookings = AgentMaster::withTrashed()->whereIn('agent_id', $agentIds)->latest()->get();
        } else {
            $allBookings = Agent::withTrashed()
                ->latest()
                ->where('agent_id', $id)
                ->with('ticket')
                ->get();
            $Masterbookings = AgentMaster::withTrashed()->orWhere('agent_id', $id)->latest()->get();
        }

        // Attempt to retrieve cached data
        $data = Cache::remember($cacheKey, 60, function () use ($id, $allBookings, $Masterbookings) {
            $MasterIds = $Masterbookings->pluck('booking_id');
            $idsGroup = $MasterIds->map(function ($item) {
                return $item;
            });
            $firstIds = $idsGroup->map(function ($ids) {
                return $ids[0] ?? null;
            })->filter();
            $firstIdsArray = $firstIds->toArray();

            $decodedMasterIds = $MasterIds->flatMap(function ($item) {
                if (is_string($item)) {
                    return json_decode($item, true) ?: [];
                }
                return $item;
            })->toArray();

            $filteredMainBookings = $allBookings->filter(function ($booking) use ($decodedMasterIds) {
                return !in_array($booking->id, $decodedMasterIds);
            });
            $filteredMasterBookings = $allBookings->filter(function ($booking) use ($firstIdsArray) {
                return in_array($booking->id, $firstIdsArray);
            });
            $combinedBookings = $filteredMainBookings->merge($filteredMasterBookings)->unique('id');
            $amount = $combinedBookings->sum('amount');
            $discount = $combinedBookings->sum('discount');

            return [
                'allBookings' => $allBookings,
                'bookings' => $combinedBookings,
                'amount' => $amount,
                'discount' => $discount
            ];
        });
        $allbookings = $data['allBookings'];
        $bookings = $data['bookings'];
        $amount = $data['amount'];
        $discount = $data['discount'];
        if ($bookings->isNotEmpty()) {
            return response()->json(['status' => true, 'bookings' => $bookings, 'amount' => $amount, 'discount' => $discount, 'allbookings' => $allbookings], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'No Bookings Found'], 200);
        }
    }
    public function sponsorBooking($id)
    {
        // Define a cache key based on the agent ID
        $cacheKey = "sponsor_bookings_{$id}";
        $user = Auth::user();
        $isOrganizer = $user->hasRole('Organizer');
        $isAdmin = $user->hasRole('Admin');
        if ($isAdmin) {
            $allBookings = SponsorBooking::withTrashed()
                ->latest()
                ->with('ticket')
                ->get();
            $Masterbookings = SponsorMasterBooking::withTrashed()
                ->latest()->get();
        } elseif ($isOrganizer) {
            $agentIds = $user->usersUnder()->pluck('id');
            $allBookings = SponsorBooking::withTrashed()
                ->latest()
                ->whereIn('sponsor_id', $agentIds)
                ->with('ticket')
                ->get();
            $Masterbookings = SponsorMasterBooking::withTrashed()->whereIn('sponsor_id', $agentIds)->latest()->get();
        } else {
            $allBookings = SponsorBooking::withTrashed()
                ->latest()
                ->where('sponsor_id', $id)
                ->with('ticket')
                ->get();
            $Masterbookings = SponsorMasterBooking::withTrashed()->orWhere('sponsor_id', $id)->latest()->get();
        }

        // Attempt to retrieve cached data
        $data = Cache::remember($cacheKey, 60, function () use ($id, $allBookings, $Masterbookings) {
            $MasterIds = $Masterbookings->pluck('booking_id');
            $idsGroup = $MasterIds->map(function ($item) {
                return $item;
            });
            $firstIds = $idsGroup->map(function ($ids) {
                return $ids[0] ?? null;
            })->filter();
            $firstIdsArray = $firstIds->toArray();

            $decodedMasterIds = $MasterIds->flatMap(function ($item) {
                if (is_string($item)) {
                    return json_decode($item, true) ?: [];
                }
                return $item;
            })->toArray();

            $filteredMainBookings = $allBookings->filter(function ($booking) use ($decodedMasterIds) {
                return !in_array($booking->id, $decodedMasterIds);
            });
            $filteredMasterBookings = $allBookings->filter(function ($booking) use ($firstIdsArray) {
                return in_array($booking->id, $firstIdsArray);
            });
            $combinedBookings = $filteredMainBookings->merge($filteredMasterBookings)->unique('id');
            $amount = $combinedBookings->sum('amount');
            $discount = $combinedBookings->sum('discount');

            return [
                'allBookings' => $allBookings,
                'bookings' => $combinedBookings,
                'amount' => $amount,
                'discount' => $discount
            ];
        });
        $allbookings = $data['allBookings'];
        $bookings = $data['bookings'];
        $amount = $data['amount'];
        $discount = $data['discount'];
        if ($bookings->isNotEmpty()) {
            return response()->json(['status' => true, 'bookings' => $bookings, 'amount' => $amount, 'discount' => $discount, 'allbookings' => $allbookings], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'No Bookings Found'], 200);
        }
    }

    public function accreditationBooking($id)
    {
        // Define a cache key based on the agent ID
        $cacheKey = "accreditation_bookings_{$id}";
        $user = Auth::user();
        $isOrganizer = $user->hasRole('Organizer');
        $isAdmin = $user->hasRole('Admin');
        if ($isAdmin) {
            $allBookings = AccreditationBooking::withTrashed()
                ->latest()
                ->with('ticket')
                ->get();
            $Masterbookings = AccreditationMasterBooking::withTrashed()
                ->latest()->get();
        } elseif ($isOrganizer) {
            $agentIds = $user->usersUnder()->pluck('id');
            $allBookings = AccreditationBooking::withTrashed()
                ->latest()
                ->whereIn('accreditation_id', $agentIds)
                ->with('ticket')
                ->get();
            $Masterbookings = AccreditationMasterBooking::withTrashed()->whereIn('accreditation_id', $agentIds)->latest()->get();
        } else {
            $allBookings = AccreditationBooking::withTrashed()
                ->latest()
                ->where('accreditation_id', $id)
                ->with('ticket')
                ->get();
            $Masterbookings = AccreditationMasterBooking::withTrashed()->orWhere('accreditation_id', $id)->latest()->get();
        }

        // Attempt to retrieve cached data
        $data = Cache::remember($cacheKey, 60, function () use ($id, $allBookings, $Masterbookings) {
            $MasterIds = $Masterbookings->pluck('booking_id');
            $idsGroup = $MasterIds->map(function ($item) {
                return $item;
            });
            $firstIds = $idsGroup->map(function ($ids) {
                return $ids[0] ?? null;
            })->filter();
            $firstIdsArray = $firstIds->toArray();

            $decodedMasterIds = $MasterIds->flatMap(function ($item) {
                if (is_string($item)) {
                    return json_decode($item, true) ?: [];
                }
                return $item;
            })->toArray();

            $filteredMainBookings = $allBookings->filter(function ($booking) use ($decodedMasterIds) {
                return !in_array($booking->id, $decodedMasterIds);
            });
            $filteredMasterBookings = $allBookings->filter(function ($booking) use ($firstIdsArray) {
                return in_array($booking->id, $firstIdsArray);
            });
            $combinedBookings = $filteredMainBookings->merge($filteredMasterBookings)->unique('id');
            $amount = $combinedBookings->sum('amount');
            $discount = $combinedBookings->sum('discount');

            return [
                'allBookings' => $allBookings,
                'bookings' => $combinedBookings,
                'amount' => $amount,
                'discount' => $discount
            ];
        });
        $allbookings = $data['allBookings'];
        $bookings = $data['bookings'];
        $amount = $data['amount'];
        $discount = $data['discount'];
        if ($bookings->isNotEmpty()) {
            return response()->json(['status' => true, 'bookings' => $bookings, 'amount' => $amount, 'discount' => $discount, 'allbookings' => $allbookings], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'No Bookings Found'], 200);
        }
    }

    //kinjal
    public function store(Request $request, $id)
    {
        try {
            // Retrieve the promocode details if a promocode_code is provided
            if (isset($request->promocode_code)) {
                $promocode = Promocode::where('code', $request->promocode_code)->first();

                if (!$promocode) {
                    return response()->json(['status' => 'false', 'message' => 'Invalid promocode'], 400);
                }

                // Initialize remaining_count based on usage_limit if it hasn't been set yet
                if ($promocode->remaining_count === null) {
                    // First time use: set remaining_count to usage_limit - 1
                    $promocode->remaining_count = $promocode->usage_limit - 1;
                } elseif ($promocode->remaining_count > null) {
                    // Decrease remaining_count on subsequent uses
                    $promocode->remaining_count--;
                } else {
                    return response()->json(['status' => 'false', 'message' => 'Promocode usage limit reached'], 400);
                }

                // Save updated promocode details
                $promocode->save();
            }

            // Initialize bookings array
            $bookings = [];
            $firstIteration = true; // Flag to check if it's the first iteration

            if ($request->tickets['quantity'] > 0) {
                for ($i = 0; $i < $request->tickets['quantity']; $i++) {
                    $booking = new Booking();
                    $booking->ticket_id = $request->tickets['id'];
                    $booking->agent_id = $request->agent_id;
                    $booking->user_id = $request->user_id;
                    $booking->token = $this->generateRandomCode();
                    $booking->email = $request->email;
                    $booking->name = $request->name;
                    $booking->number = $request->number;
                    $booking->type = $request->type;
                    $booking->payment_method = $request->payment_method;
                    $booking->status = 0;

                    // Assign promocode_id to booking
                    if (isset($request->promocode_code)) {
                        $booking->promocode_id = $request->promocode_code;
                    }

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
                }
            }

            return response()->json(['status' => 'true', 'message' => 'Tickets Booked Successfully', 'bookings' => $bookings], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => 'Failed to book tickets', 'error' => $e->getMessage()], 500);
        }
    }

    public function penddingBookingList(Request $request, $id)
    {
        try {
            $loggedInUser = Auth::user();
            $isAdmin = $loggedInUser->hasRole('Admin');

            // Handle date filtering
            if ($request->has('date')) {

                $dates = $request->date ? explode(',', $request->date) : null;

                if (count($dates) === 1) {
                    // Single date filtering
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[0])->endOfDay();
                } elseif (count($dates) === 2) {
                    // Date range filtering
                    $startDate = Carbon::parse($dates[0])->startOfDay()->addDay(1);
                    $endDate = Carbon::parse($dates[1])->endOfDay();
                } else {
                    return response()->json(['status' => 'false', 'message' => 'Invalid date format'], 400);
                }
            } else {
                // Default to today's bookings
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
            }

            if ($isAdmin) {
                $Masterbookings = PenddingBookingsMaster::withTrashed()
                    ->with('paymentLog')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereNull('deleted_at')
                    ->latest()
                    ->get();
                $allBookingIds = [];
                $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $startDate, $endDate) {
                    $bookingIds = $masterBooking->booking_id;

                    if (is_array($bookingIds)) {
                        $allBookingIds = array_merge($allBookingIds, $bookingIds);
                        $masterBooking->bookings = PenddingBooking::whereIn('id', $bookingIds)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->with(['ticket.event.user', 'user', 'paymentLog'])
                            ->with('paymentLog')
                            ->whereNull('deleted_at')
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

                $normalBookings = PenddingBooking::withTrashed()
                    ->with('paymentLog')
                    ->with(['ticket.event.user', 'user', 'attendee', 'paymentLog'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereNull('deleted_at')
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
                    'status' => 'true',
                    'bookings' => $sortedCombinedBookings,
                ], 200);
            } else {
                $eventIds = Event::where('user_id', $id)->pluck('id');
                $tickets = Ticket::whereIn('event_id', $eventIds)->pluck('id');

                $Masterbookings = PenddingBookingsMaster::withTrashed()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereNull('deleted_at')
                    ->latest()
                    ->get();

                $allBookingIds = [];
                $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $tickets, $startDate, $endDate) {
                    $bookingIds = $masterBooking->booking_id;

                    if (is_array($bookingIds)) {
                        $allBookingIds = array_merge($allBookingIds, $bookingIds);
                        $masterBooking->bookings = PenddingBooking::whereIn('id', $bookingIds)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->whereHas('ticket', function ($query) use ($tickets) {
                                $query->whereIn('id', $tickets);
                            })
                            ->with('paymentLog')
                            ->with(['ticket.event.user', 'user', 'paymentLog'])
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

                $normalBookings = PenddingBooking::withTrashed()
                    ->with('paymentLog')
                    ->with(['ticket.event.user', 'user', 'attendee', 'paymentLog'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereHas('ticket', function ($query) use ($tickets) {
                        $query->whereIn('id', $tickets);
                    })
                    ->whereNull('deleted_at')
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
                    'status' => 'true',
                    'bookings' => $sortedCombinedBookings,
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                // 'message' => 'Failed to retrieve bookings',
                'error' => $e->getMessage() . "on line" . $e->getLine(),
            ], 500);
        }
    }


    public function master(Request $request, $id)
    {
        try {
            $booking = new MasterBooking();
            $bookingIds = $request->input('bookingIds');
            if (is_string($bookingIds)) {
                $bookingIds = json_decode($bookingIds, true);
                if (is_null($bookingIds)) {
                    $bookingIds = explode(',', trim($bookingIds, '[]'));
                }
            }

            $booking->booking_id = $request->bookingIds;
            $booking->user_id = $id;
            $booking->order_id = $this->generateRandomCode();
            $booking->amount = $request->amount;
            $booking->discount = $request->discount;
            $booking->payment_method = $request->payment_method;
            $booking->save();
            $Masterbooking = MasterBooking::where('order_id', $booking->order_id)->with('user')->first();

            if ($Masterbooking) {
                $bookingIds = $Masterbooking->booking_id;
                if (is_array($bookingIds)) {
                    $Masterbooking->bookings = Booking::whereIn('id', $bookingIds)->with('ticket.event.user.smsConfig')->get();
                } else {
                    $Masterbooking->bookings = collect();
                }
            }
            return response()->json([
                'status' => 'true',
                'message' => 'Master Ticket Created Successfully',
                'booking' => $Masterbooking
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => 'Failed to create user', 'error' => $e->getMessage()], 500);
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


    public function sendBookingMail(Request $request)
    {
        $booking = $request->data;
        try {

            dispatch(new BookingMailJob($booking));

            return response()->json([
                'message' => 'Booking Email has been queued successfully.',
                'status' => true
            ], 200);
        } catch (\Exception $e) {
            Log::error('Email sending failed', ['error' => $e->getMessage(), 'data' => $booking]);
            return response()->json([
                'message' => 'Failed to send email.',
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function destroy($id, $token)
    {
        $Masterbookings = MasterBooking::where('id', $id)
            ->where('order_id', $token)
            ->latest()
            ->first();

        if ($Masterbookings) {
            $bookingIds = is_array($Masterbookings->booking_id)
                ? $Masterbookings->booking_id
                : json_decode($Masterbookings->booking_id, true);

            if (!empty($bookingIds) && is_array($bookingIds)) {
                Booking::whereIn('id', $bookingIds)->delete();
            }

            $Masterbookings->delete();

            return response()->json([
                'status' => true,
                'message' => 'Master Booking and related bookings deleted successfully'
            ], 200);
        } else {
            $normalBooking = Booking::where('id', $id)->where('token', $token)->first();

            if ($normalBooking) {
                $normalBooking->delete();

                return response()->json([
                    'status' => true,
                    'message' => 'Booking deleted successfully'
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found'
                ], 404);
            }
        }
    }


    public function restoreBooking($id, $token)
    {
        $Masterbookings = MasterBooking::withTrashed()
            ->where('id', $id)
            ->where('order_id', $token)
            ->first();

        if ($Masterbookings) {
            $bookingIds = is_array($Masterbookings->booking_id)
                ? $Masterbookings->booking_id
                : json_decode($Masterbookings->booking_id, true);

            if (!empty($bookingIds) && is_array($bookingIds)) {
                Booking::withTrashed()
                    ->whereIn('id', $bookingIds)
                    ->restore();
            }

            $Masterbookings->restore();

            return response()->json([
                'status' => true,
                'message' => 'Master Booking and related bookings restored successfully'
            ], 200);
        } else {
            $normalBooking = Booking::withTrashed()
                ->where('id', $id)
                ->where('token', $token)
                ->latest()
                ->first();

            if ($normalBooking) {
                $normalBooking->restore();

                return response()->json([
                    'status' => true,
                    'message' => 'Booking restored successfully'
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Booking not found'
                ], 404);
            }
        }
    }



    ///advance
    public function verifyTicket(Request $request, $orderId)
    {
        try {
            // Try to find each type of booking
            $loggedInUser = Auth::user();  // This fetches the authenticated user via Laravel's Auth system
            $isAdmin = $loggedInUser->hasRole('Admin');
            $booking = Booking::where('token', $orderId)->with(['ticket.event.user', 'attendee'])->first();
            $agentBooking = Agent::where('token', $orderId)->with('ticket.event.user', 'attendee')->first();
            $AccreditationBooking = AccreditationBooking::where('token', $orderId)->with('ticket.event.user', 'attendee')->first();
            $SponsorBooking = SponsorBooking::where('token', $orderId)->with('ticket.event.user', 'attendee')->first();
            $amusementAgentBooking = AmusementAgentBooking::where('token', $orderId)->with('ticket.event.user', 'attendee')->first();
            $ExhibitionBooking = ExhibitionBooking::where('token', $orderId)->with('ticket.event.user', 'attendee')->first();
            $amusementBooking = AmusementBooking::where('token', $orderId)->with(['ticket.event.user', 'attendee'])->first();
            $complimentaryBookings = ComplimentaryBookings::where('token', $orderId)->with('ticket.event.user')->first();
            $posBooking = PosBooking::where('token', $orderId)->with('ticket.event.user', 'ticket.event.Category')->first();
            $amusementPosBooking = AmusementPosBooking::where('token', $orderId)->with('ticket.event.user', 'ticket.event.Category')->first();
            $masterBookings = MasterBooking::where('order_id', $orderId)->first();
            $amusementMasterBookings = AmusementMasterBooking::where('order_id', $orderId)->first();
            $agentMasterBookings = AgentMaster::where('order_id', $orderId)->first();
            $AccreditationMasterBooking = AccreditationMasterBooking::where('order_id', $orderId)->first();
            $SponsorMasterBooking = SponsorMasterBooking::where('order_id', $orderId)->first();
            $amusementAgentMasterBookings = AmusementAgentMasterBooking::where('order_id', $orderId)->first();

            // return response()->json($amusementAgentBooking);
            $eventData = $this->eventCheck($booking, $agentBooking, $posBooking, $complimentaryBookings, $masterBookings, $agentMasterBookings, $ExhibitionBooking, $amusementBooking, $amusementMasterBookings, $amusementAgentBooking, $amusementAgentMasterBookings, $amusementPosBooking, $AccreditationBooking, $AccreditationMasterBooking, $SponsorBooking, $SponsorMasterBooking);
            $organizer = $eventData['organizer'];
            $relatedBookings = $eventData['relatedBookings'];
            $event = $eventData['event'];
            $category = Category::find($event['category']);

            if ($event) {
                $dateRange = array_map('trim', explode(',', $event->date_range));
                $timezone = new DateTimeZone('Asia/Kolkata');
                if (count($dateRange) === 1) {
                    $startDate = new DateTime($dateRange[0], $timezone);
                    $startDate->setTime(0, 0);
                    $endDate = clone $startDate;
                    $endDate->setTime(23, 59, 59);
                } elseif (count($dateRange) === 2) {
                    $startDate = new DateTime($dateRange[0], $timezone);
                    $startDate->setTime(0, 0);
                    $endDate = new DateTime($dateRange[1], $timezone);
                    $endDate->setTime(23, 59, 59);
                } else {
                    return response()->json([
                        'status' => 'false',
                        'message' => 'Invalid date range',
                    ], 400);
                }
                $currentDate = new DateTime('now', $timezone);

                // Additional validation to ensure dates are valid
                if (!$startDate || !$endDate) {
                    return response()->json([
                        'status' => 'false',
                        'message' => 'Invalid dates provided',
                    ], 400);
                }
                if (!($currentDate >= $startDate && $currentDate <= $endDate)) {
                    return response()->json([
                        'status' => 'false',
                        'message' => 'This event is not currently active',
                    ], 400);
                }

                if ($category->title == 'Amusement') {
                    // Check which booking is available and assign $bookingDate accordingly
                    if (!empty($amusementBooking?->booking_date)) {
                        $bookingDate = new DateTime($amusementBooking->booking_date, $timezone);
                    } elseif (!empty($amusementAgentBooking?->booking_date)) {
                        $bookingDate = new DateTime($amusementAgentBooking->booking_date, $timezone);
                    } elseif (!empty($posBooking?->booking_date)) {
                        $bookingDate = new DateTime($posBooking->booking_date, $timezone);
                    } elseif (!empty($amusementPosBooking?->booking_date)) {
                        $bookingDate = new DateTime($amusementPosBooking->booking_date, $timezone);
                    } else {
                        return response()->json([
                            'status' => false,
                            'message' => 'No valid booking found.',
                        ], 400);
                    }

                    // Compare booking date with the current date
                    if ($bookingDate->format('Y-m-d') !== $currentDate->format('Y-m-d')) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Booking is only valid on ' . $bookingDate->format('Y-m-d'),
                        ], 400);
                    }
                }

                try {
                    if (empty($event->entry_time)) {
                        return response()->json([
                            'status' => 'false',
                            'message' => 'Event Entry Time Not Provided',
                        ], 400);
                    }
                    $startTimeWithDate = clone $currentDate;
                    $startTime = DateTime::createFromFormat('H:i', $event->entry_time, $timezone);
                    // $startTime = DateTime::createFromFormat('H:i', $event->start_time, $timezone);
                    $startTimeWithDate->setTime($startTime->format('H'), $startTime->format('i'));

                    // Check if the event has started
                    if ($currentDate < $startTimeWithDate) {
                        return response()->json([
                            'status' => 'false',
                            'message' => 'This event has not started yet',
                        ], 400);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'false',
                        'message' => 'Invalid start time provided',
                    ], 400);
                }
            }

            // Check if the organizer ID is valid and matches the request ID
            if (
                !$organizer ||
                (!$isAdmin && (
                    intval($organizer) !== intval($loggedInUser->id) &&
                    intval($loggedInUser->id) !== intval($organizer) &&
                    intval($organizer) !== intval($loggedInUser->reporting_user)
                ))
            ) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'This Ticket Is Not Recognized',
                    'organizer' => $isAdmin,
                ], 404);
            }

            // Handle the specific booking type logic
            if ($posBooking) {
                if ($posBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $posBooking,
                        'attendee_required' => false,
                        'event' => $event,
                        'type' => "POS"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $posBooking->updated_at
                    ], 404);
                }
            } elseif ($amusementPosBooking) {
                if ($amusementPosBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $amusementPosBooking,
                        'attendee_required' => false,
                        'event' => $event,
                        'type' => "POS"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $amusementPosBooking->updated_at
                    ], 404);
                }
            } elseif ($booking) {
                if ($booking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $booking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Online"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $booking->updated_at
                    ], 404);
                }
            } elseif ($amusementBooking) {

                if ($amusementBooking->status == '0') {

                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $amusementBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Online"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $amusementBooking->updated_at
                    ], 404);
                }
            } elseif ($agentBooking) {
                if ($agentBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $agentBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Agent"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $agentBooking->updated_at
                    ], 404);
                }
            } elseif ($AccreditationBooking) {
                if ($AccreditationBooking->status == '0') {
                    //     $accessAreaIds = is_array($AccreditationBooking->access_area)
                    //     ? $AccreditationBooking->access_area
                    //     : explode(',', $AccreditationBooking->access_area);

                    // // Fetch names from AccessArea model
                    // $accessAreaNames = AccessArea::whereIn('id', $accessAreaIds)->pluck('title');
                    // $AccreditationBooking->access_area = $accessAreaNames;
                 
                    $bookingAccess = is_array($AccreditationBooking->access_area)
                        ? $AccreditationBooking->access_area
                        : explode(',', $AccreditationBooking->access_area);

                    $ticketAccess = is_array($AccreditationBooking->ticket->access_area ?? null)
                        ? $AccreditationBooking->ticket->access_area
                        : explode(',', $AccreditationBooking->ticket->access_area ?? '');

                    // Merge and get unique access area IDs
                    $mergedAccessIds = collect($bookingAccess)
                        ->merge($ticketAccess)
                        ->map(fn($id) => (int) trim($id))
                        ->unique()
                        ->values()
                        ->all();
                    $accessAreaNames = AccessArea::whereIn('id', $mergedAccessIds)->pluck('title');
                    $AccreditationBooking->access_area = $accessAreaNames; 

                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $AccreditationBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        // 'access_area' => $accessAreaNames ?? [],
                        'event' => $event,
                        'type' => "Accreditation"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $AccreditationBooking->updated_at
                    ], 404);
                }
            } elseif ($SponsorBooking) {
                if ($SponsorBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $SponsorBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Agent"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $SponsorBooking->updated_at
                    ], 404);
                }
            } elseif ($amusementAgentBooking) {
                if ($amusementAgentBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $amusementAgentBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Agent"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $amusementAgentBooking->updated_at
                    ], 404);
                }
            } elseif ($ExhibitionBooking) {
                if ($ExhibitionBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $ExhibitionBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Agent"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $ExhibitionBooking->updated_at
                    ], 404);
                }
            } elseif ($complimentaryBookings) {
                if ($complimentaryBookings->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $complimentaryBookings,
                        'attendee_required' => false,
                        'event' => $event,
                        'type' => "Complimentary"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $complimentaryBookings->updated_at
                    ], 404);
                }
            } elseif ($masterBookings) {
                $allStatusZero = $relatedBookings->every(function ($relatedBooking) {
                    return $relatedBooking->status == "0";
                });

                if ($allStatusZero) {
                    $masterBookings->bookings = $relatedBookings;
                    return response()->json([
                        'status' => true,
                        'is_master' => true,
                        'bookings' => $masterBookings,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Online"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $masterBookings->updated_at
                    ], 400);
                }
            } elseif ($amusementMasterBookings) {
                $allStatusZero = $relatedBookings->every(function ($relatedBooking) {
                    return $relatedBooking->status == "0";
                });

                if ($allStatusZero) {
                    $amusementMasterBookings->bookings = $relatedBookings;
                    return response()->json([
                        'status' => true,
                        'is_master' => true,
                        'bookings' => $amusementMasterBookings,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Online"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $amusementMasterBookings->updated_at
                    ], 400);
                }
            } elseif ($agentMasterBookings) {
                $allStatusZero = $relatedBookings->every(function ($relatedBooking) {
                    return $relatedBooking->status == "0";
                });

                if ($allStatusZero) {
                    $agentMasterBookings->bookings = $relatedBookings;
                    return response()->json([
                        'status' => true,
                        'is_master' => true,
                        'bookings' => $agentMasterBookings,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Agent"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $agentMasterBookings->updated_at
                    ], 400);
                }
            } elseif ($AccreditationMasterBooking) {
                $allStatusZero = $relatedBookings->every(function ($relatedBooking) {
                    return $relatedBooking->status == "0";
                });

                if ($allStatusZero) {
                    $AccreditationMasterBooking->bookings = $relatedBookings;
                    return response()->json([
                        'status' => true,
                        'is_master' => true,
                        'bookings' => $AccreditationMasterBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Agent"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $AccreditationMasterBooking->updated_at
                    ], 400);
                }
            } elseif ($SponsorMasterBooking) {
                $allStatusZero = $relatedBookings->every(function ($relatedBooking) {
                    return $relatedBooking->status == "0";
                });

                if ($allStatusZero) {
                    $SponsorMasterBooking->bookings = $relatedBookings;
                    return response()->json([
                        'status' => true,
                        'is_master' => true,
                        'bookings' => $SponsorMasterBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Agent"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $SponsorMasterBooking->updated_at
                    ], 400);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No booking found'
                ], 404);
            }
        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
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
        $amusementPosBooking = AmusementPosBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $complimentaryBookings = ComplimentaryBookings::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $masterBookings = MasterBooking::where('order_id', $orderId)->first();
        $amusementMasterBookings = AmusementMasterBooking::where('order_id', $orderId)->first();
        $agentMasterBookings = AgentMaster::where('order_id', $orderId)->first();
        $AccreditationMasterBooking = AccreditationMasterBooking::where('order_id', $orderId)->first();
        $SponsorMasterBooking = SponsorMasterBooking::where('order_id', $orderId)->first();
        $amusementAgentMasterBookings = AmusementAgentMasterBooking::where('order_id', $orderId)->first();
        $today = Carbon::now()->toDateTimeString();

        $eventData = $this->eventCheck($booking, $agentBooking, $posBooking, $complimentaryBookings, $masterBookings, $agentMasterBookings, $ExhibitionBooking, $amusementBooking, $amusementMasterBookings, $amusementAgentBooking, $amusementAgentMasterBookings, $amusementPosBooking, $AccreditationBooking, $AccreditationMasterBooking, $SponsorMasterBooking, $SponsorBooking);
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
            $posBooking->save();
            return response()->json([
                'status' => true,
                'bookings' => $posBooking->status
            ], 200);
        } else if ($amusementPosBooking) {
            if ($event->multi_scan) {
                $amusementPosBooking->status = false;
            } else {
                $amusementPosBooking->status = true;
            }
            $amusementPosBooking->is_scaned = true;
            $amusementPosBooking->save();
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
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        } else if ($AccreditationMasterBooking) {
            $agent = $AccreditationMasterBooking->booking_id;
            $relatedBookings = Agent::with('ticket.event.user')->where('status', 0)->whereIn('id', $agent)->get();

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
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        } else if ($SponsorMasterBooking) {
            $agent = $SponsorMasterBooking->booking_id;
            $relatedBookings = Agent::with('ticket.event.user')->where('status', 0)->whereIn('id', $agent)->get();

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
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        } else if ($amusementAgentMasterBookings) {
            $agent = $amusementAgentMasterBookings->booking_id;
            $relatedBookings = AmusementAgentBooking::with('ticket.event.user')->where('status', 0)->whereIn('id', $agent)->get();
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
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        }
    }

    public function export(Request $request)
    {

        $Attendee = $request->input('user_id');
        $eventName = $request->input('ticket_id');
        $status = $request->input('status');
        $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

        $query = Booking::query();

        if ($request->has('ticket_id')) {
            $query->where('ticket_id', $eventName);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $Attendee);
        }

        if ($request->has('status')) {
            $query->where('status', $status);
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

        $Booking = $query->with([
            'userData',
            'ticket.event.user'
        ])->get();
        return Excel::download(new BookingExport($Booking), 'Booking_export.xlsx');
    }

    public function imagesRetrive(Request $request)
    {
        $fullImagePath = $request->input('path');

        if (!$fullImagePath) {
            return response()->json(['error' => 'No image path provided'], 400);
        }
        $parsedUrl = parse_url($fullImagePath);
        if (isset($parsedUrl['host']) && $parsedUrl['host'] === parse_url(url('/'), PHP_URL_HOST)) {
            $relativePath = $parsedUrl['path'];
        } elseif (str_starts_with($fullImagePath, url('/'))) {

            $relativePath = str_replace(url('/'), '', $fullImagePath);
        } else {
            $relativePath = $fullImagePath;
        }

        $relativePath = urldecode(ltrim($relativePath, '/'));

        $absolutePath = public_path(ltrim($relativePath, '/'));

        if (!file_exists($absolutePath)) {
            return response()->json([
                'error' => 'Image not found',
                'path' => $absolutePath,
                'original_path' => $fullImagePath
            ], 404);
        }

        try {
            $fileContents = file_get_contents($absolutePath);
            $mimeType = mime_content_type($absolutePath);

            return response($fileContents, 200)
                ->header('Content-Type', $mimeType);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve image',
                'message' => $e->getMessage(),
                'path' => $absolutePath
            ], 500);
        }
    }

    public function userImagesRetrive(Request $request)
    {
        $fullImagePath = $request->input('path');

        if (!$fullImagePath) {
            return response()->json(['error' => 'No image path provided'], 400);
        }
        $parsedUrl = parse_url($fullImagePath);
        if (isset($parsedUrl['host']) && $parsedUrl['host'] === parse_url(url('/'), PHP_URL_HOST)) {
            $relativePath = $parsedUrl['path'];
        } elseif (str_starts_with($fullImagePath, url('/'))) {

            $relativePath = str_replace(url('/'), '', $fullImagePath);
        } else {
            $relativePath = $fullImagePath;
        }

        $relativePath = urldecode(ltrim($relativePath, '/'));

        $absolutePath = public_path(ltrim($relativePath, '/'));

        if (!file_exists($absolutePath)) {
            return response()->json([
                'error' => 'Image not found',
                'path' => $absolutePath,
                'original_path' => $fullImagePath
            ], 404);
        }

        try {
            $fileContents = file_get_contents($absolutePath);
            $mimeType = mime_content_type($absolutePath);

            return response($fileContents, 200)
                ->header('Content-Type', $mimeType);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve image',
                'message' => $e->getMessage(),
                'path' => $absolutePath
            ], 500);
        }
    }

    //penddingBookingConform
    public function penddingBookingConform($session)
    {
        $status = 'success';
        $decryptedSessionId = $session;
        $bookings = PenddingBooking::where('session_id', $decryptedSessionId)->with('paymentLog')->get();
        $bookingMaster = PenddingBookingsMaster::where('session_id', $decryptedSessionId)->with('paymentLog')->get();
        $masterBookingIDs = [];
        if ($bookings) {
            foreach ($bookings as $individualBooking) {
                if ($status) {
                    $data = $individualBooking;
                    $booking =   $this->bookingData($data);
                    if ($booking) {
                        $masterBookingIDs[] = $booking->id;
                        $individualBooking->delete();
                    }
                }

                $individualBooking->save();
            }
        }
        if ($bookingMaster->isNotEmpty()) {

            if ($status) {
                $updated = $this->updateMasterBooking($bookingMaster, $masterBookingIDs);

                if ($updated) {
                    $bookingMaster->each->delete();
                }
            }
        }
        return response()->json(['status' => true], 200);
    }

    private function bookingData($data)
    {

        $booking = new Booking();
        $booking->ticket_id = $data->ticket_id;
        $booking->user_id = $data->user_id;
        $booking->session_id = $data->session_id;
        $booking->promocode_id = $data->promocode_id;
        $booking->token = $data->token;
        $booking->amount = $data->amount;
        $booking->email = $data->email;
        $booking->name = $data->name;
        $booking->number = $data->number;
        $booking->type = $data->type;
        $booking->dates = $data->dates;
        $booking->payment_method = $data->payment_method;
        $booking->discount = $data->discount;
        $booking->status = $data->status = 0;
        // $booking->status = $data->status;
        $booking->payment_status = 1;
        $booking->txnid = $data->txnid;
        $booking->device = $data->device;
        $booking->base_amount = $data->base_amount;
        $booking->convenience_fee = $data->convenience_fee;
        $booking->attendee_id = $data->attendee_id;
        $booking->total_tax = $data->total_tax;
        $booking->gateway = $data->gateway;
        $booking->payment_id = optional($data->paymentLog)->payment_id;
        $booking->save();
        if (isset($booking->promocode_id)) {
            $promocode = Promocode::where('code', $booking->promocode_id)->first();

            if (!$promocode) {
                return response()->json(['status' => false, 'message' => 'Invalid promocode'], 400);
            }

            // Initialize remaining_count based on usage_limit if it hasn't been set yet
            if ($promocode->remaining_count === null) {
                // First time use: set remaining_count to usage_limit - 1
                $promocode->remaining_count = $promocode->usage_limit - 1;
            } elseif ($promocode->remaining_count > null) {
                // Decrease remaining_count on subsequent uses
                $promocode->remaining_count--;
            } else {
                return response()->json(['status' => false, 'message' => 'Promocode usage limit reached'], 400);
            }

            // Assign promocode_id to booking
            if (isset($booking->promocode_id)) {
                $booking->promocode_id = $booking->promocode_id;
            }

            // Save updated promocode details
            $promocode->save();
        }
        return $booking;
    }

    private function updateMasterBooking($bookingMaster, $ids)
    {
        foreach ($bookingMaster as $entry) {
            $data = [
                'user_id' => $entry->user_id,
                'session_id' => $entry->session_id,
                'booking_id' => $ids,
                'order_id' => $entry->order_id,
                'amount' => $entry->amount,
                'discount' => $entry->discount,
                'payment_method' => $entry->payment_method,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Create MasterBooking record
            $master = MasterBooking::create($data);

            // If creation fails, return false
            if (!$master) {
                return false;
            }
        }

        // Return true if all records are created successfully
        return true;
    }

    private function eventCheck($booking, $agentBooking, $posBooking, $complimentaryBookings, $masterBookings, $agentMasterBookings, $ExhibitionBooking, $amusementBooking, $amusementMasterBookings, $amusementAgentBooking, $amusementAgentMasterBookings, $amusementPosBooking, $AccreditationBooking, $AccreditationMasterBooking, $SponsorBooking, $SponsorMasterBooking)
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
            $relatedBookings = SponsorMasterBooking::with('ticket.event.user', 'attendee')->whereIn('id', $agentIds)->get();
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

    public function boxOfficeBooking($number)
    {
        $allAttendees = [];

        // 1. ONLINE BOOKINGS
        $bookings = Booking::where('number', $number)
            ->whereNull('deleted_at')
            ->with(['ticket.event.user', 'ticket.event.Category', 'user', 'attendee'])
            ->latest()
            ->get()
            ->map(function ($booking) {
                $booking->is_deleted = $booking->trashed();
                return $booking;
            });

        // 2. MASTER BOOKINGS (linked by session_id)
        $masterBookingIds = $bookings->pluck('session_id')->filter()->unique();

        $Masterbookings = $masterBookingIds->isNotEmpty()
            ? MasterBooking::whereIn('session_id', $masterBookingIds)->get()
            : collect();

        // Attach amusement bookings and attendees to each MasterBooking
        $Masterbookings->each(function ($masterBooking) use (&$allAttendees) {
            $amusementBookings = AmusementBooking::where('session_id', $masterBooking->session_id)
                ->whereNull('deleted_at')
                ->with(['ticket.event.user', 'ticket.event.Category', 'user', 'attendee'])
                ->latest()
                ->get()
                ->map(function ($booking) use (&$allAttendees) {
                    if ($booking->attendee) {
                        $allAttendees[] = $booking->attendee;
                    }
                    $booking->is_deleted = $booking->trashed();
                    return $booking;
                });

            $masterBooking->bookings = $amusementBookings;
            $masterBooking->attendees = $allAttendees;
        });

        // 3. COMPLIMENTARY BOOKINGS
        $complimentaryBookings = ComplimentaryBookings::where('number', $number)
            ->with(['ticket.event.user', 'ticket.event.Category', 'user'])
            ->latest()
            ->get()
            ->map(function ($booking) {
                $booking->is_deleted = $booking->trashed();
                return $booking;
            });

        // 4. AGENT BOOKINGS
        $agentBookings = Agent::where('number', $number)
            ->with(['ticket.event.user', 'ticket.event.Category', 'user', 'attendee'])
            ->latest()
            ->get()
            ->map(function ($booking) {
                $booking->is_deleted = $booking->trashed();
                return $booking;
            });

        // 5. CHECK IF ALL EMPTY
        if (
            $bookings->isEmpty() &&
            $complimentaryBookings->isEmpty() &&
            $Masterbookings->isEmpty() &&
            $agentBookings->isEmpty()
        ) {
            return response()->json([
                'status' => false,
                'message' => 'No bookings found for this mobile number.'
            ], 404);
        }

        // 6. FINAL RESPONSE
        return response()->json([
            'status' => true,
            'bookings' => $bookings,
            'master_bookings' => $Masterbookings,
            'complimentary_bookings' => $complimentaryBookings,
            'agent_bookings' => $agentBookings,
        ], 200);
    }
}
