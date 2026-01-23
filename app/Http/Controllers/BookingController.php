<?php

namespace App\Http\Controllers;

use App\Exports\BookingExport;
use App\Jobs\BookingMailJob;
use App\Jobs\SendRefundWhatsappJob;
use App\Mail\RefundBookingMail;
use App\Models\AccreditationBooking;
use App\Models\AccreditationMasterBooking;
use App\Models\Agent;
use App\Models\AgentMaster;
use App\Models\AmusementBooking;
use App\Models\AmusementMasterBooking;
use App\Models\Booking;
use App\Models\ComplimentaryBookings;
use App\Models\Event;
use App\Models\PromoCode;
use App\Models\MasterBooking;
use App\Models\PenddingBooking;
use App\Models\PenddingBookingsMaster;
use App\Models\SponsorBooking;
use App\Models\SponsorMasterBooking;
use App\Models\Ticket;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\PermissionService;
use App\Services\SmsService;
use App\Services\WhatsappService;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\WhatsappApi;
use Illuminate\Support\Facades\Mail;


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

    public function AdminBookings(Request $request, $id, PermissionService $permissionService)
    {
        try {
            $loggedInUser = Auth::user();
            $isAdmin = $loggedInUser->hasRole('Admin');

            $permissions = $permissionService->check(['View Username', 'View Contact']);
            $canViewUsername = $permissions['View Username'];
            $canViewContact  = $permissions['View Contact'];

            // Date Handling
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

            // Master bookings query
            $Masterbookings = MasterBooking::withTrashed()
                ->with([
                    'user:id,name,number,email',
                ])
                ->where('agent_id', null)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest()
                ->get();

            // Get all booking IDs from Masterbookings
            $allBookingIds = $Masterbookings->pluck('booking_id')->flatten()->filter()->unique()->toArray();

            // Get relevant bookings in bulk
            $bookingQuery = Booking::withTrashed()
                ->with([
                    'user:id,name,number,email',
                    'ticket:id,event_id,name,background_image',
                    'ticket.event:id,name,category,date_range,address,start_time,entry_time,user_id',
                    'ticket.event.user:id,name,organisation',
                    'ticket.event.Category:id,title',
                    'influencer:id,name',
                    'attendee:id,name'
                ])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('agent_id', null);

            if (!$isAdmin) {
                $eventIds = Event::where('user_id', $id)->pluck('id');
                $ticketIds = Ticket::whereIn('event_id', $eventIds)->pluck('id');
                $bookingQuery->whereHas('ticket', function ($q) use ($ticketIds) {
                    $q->whereIn('id', $ticketIds);
                });
            }

            $bookings = $bookingQuery->get();

            // Prepare a booking map for fast lookup
            $bookingsMap = $bookings->keyBy('id');

            // Attach bookings to master bookings
            $Masterbookings->each(function ($masterBooking) use ($bookingsMap) {
                $bookingIds = $masterBooking->booking_id ?? [];
                $masterBooking->bookings = collect();

                if (!empty($bookingIds)) {
                    $bookingsForMaster = collect($bookingIds)
                        ->map(fn($id) => $bookingsMap->get($id))
                        ->filter(); // remove nulls

                    $bookingsForMaster->each(function ($booking) {
                        $booking->event_name = $booking->ticket->event->name ?? '';
                        $booking->organizer = $booking->ticket->event->user->name ?? '';
                    });

                    $masterBooking->bookings = $bookingsForMaster;
                    $masterBooking->payment_method = $bookingsForMaster->first()->payment_method ?? '';
                    $masterBooking->quantity = $bookingsForMaster->count();
                }

                $masterBooking->is_deleted = $masterBooking->trashed();
            });

            // First, add is_deleted to all bookings while they're still models
            $bookings->each(function ($booking) {
                $booking->is_deleted = $booking->trashed();
            });

            $normalBookings = $bookings->reject(function ($booking) use ($allBookingIds) {
                return in_array($booking->id, $allBookingIds);
            })->map(function ($booking) {
                $booking->event_name = $booking->ticket->event->name ?? '';
                $booking->organizer = $booking->ticket->event->user->name ?? '';
                $booking->quantity = 1;
                return $booking;
            });

            // Combine and sort
            $combinedBookings = $Masterbookings->concat($normalBookings);
            $sortedCombinedBookings = $combinedBookings->sortByDesc('created_at')->values();

            $finalBookings = $sortedCombinedBookings->map(function ($booking) use ($canViewUsername, $canViewContact) {
                // main number hide
                if (!$canViewContact) {
                    $booking->number = null;
                }

                // user details hide
                if ($booking->user) {
                    $booking->user->name   = $canViewUsername ? $booking->user->name : null;
                    $booking->name   = $canViewUsername ? $booking->name : null;
                    $booking->number = $canViewContact  ? $booking->number : null;
                    $booking->user->number = $canViewContact  ? $booking->user->number : null;
                }

                return $booking;
            });

            return response()->json([
                'status' => true,
                'bookings' => $finalBookings,
                // 'bookings' => $sortedCombinedBookings,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBookings Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'error' => $e->getMessage() . ' on line ' . $e->getLine(),
            ], 500);
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

    public function penddingBookingList(Request $request, $id)
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
     if (!auth()->check() || !auth()->user()->hasRole('Admin')) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthorized access. Only admin can restore booking.'
        ], 403);
    }
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

   
  
     public function export(Request $request)
    {
        $Attendee = $request->input('user_id');
        $eventName = $request->input('ticket_id');
        $status = $request->input('status');
        $dates = $request->input('date') ? explode(',', $request->input('date')) : [Carbon::today()->format('Y-m-d')];

       // $query = Booking::query();
      $query = Booking::withTrashed();


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

        $bookings = $query->with(['userData', 'ticket.event.user'])->get();

        // ✅ Group by session_id and count qty
        $groupedBookings = $bookings->groupBy('session_id')->map(function ($group) {
            $first = $group->first();

            return [
                'event_name'     => $first->ticket->event->name ?? 'N/A',
                'org_name'       => $first->ticket->event->user->name ?? 'N/A',
                'attendee'       => $first->userData->name ?? 'No User',
                'number'         => $first->number ?? '',
                'ticket_name'    => $first->ticket->name ?? '',
                'quantity'       => $group->count(), // ✅ Qty = records with same session_id
                'discount'       => $first->discount ?? 0,
                'base_amount'    => $first->base_amount ?? 0,
                'amount'         => $first->amount ?? 0,
                'status'         => $first->status,
                'disabled'       => $first->disabled,
                'created_at'     => $first->created_at,
              'gateway'     => $first->gateway ?? 'N/A',
                'payment_id'     => $first->payment_id ?? 'N/A',
              'deleted_at'     => $first->deleted_at,
              'is_refunded'     => $first->is_refunded,
                'refunded_at'     => $first->refunded_at,
             
            ];
        })->values();

        return Excel::download(new BookingExport($groupedBookings), 'Booking_export.xlsx');
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
    public function penddingBookingConform($id)
    {
        $status = 'success';
        $decryptedSessionId = $id;
        $bookings = PenddingBooking::where('session_id', $decryptedSessionId)->with('paymentLog')->get();
        $bookingMaster = PenddingBookingsMaster::where('session_id', $decryptedSessionId)->with('paymentLog')->get();
        $masterBookingIDs = [];

        if ($bookings->isNotEmpty()) {
            foreach ($bookings as $individualBooking) {
                if ($status) {
                    $data = $individualBooking;
                    $booking = $this->bookingData($data);

                    if ($booking) {
                        $masterBookingIDs[] = $booking->id;
                        $individualBooking->delete();
                    }
                }
            }

            // ✅ Send SMS/WhatsApp for single booking (no master)
            if ($bookingMaster->isEmpty()) {
                $firstBooking = Booking::where('session_id', $decryptedSessionId)->latest()->first();
                if ($firstBooking) {
                    $this->sendBookingNotification($firstBooking, false, 1, $firstBooking->token);
                }
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
        // Removed individual SMS send from here
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

            $master = MasterBooking::create($data);

            if ($master) {
                // ✅ Get all related bookings
                $relatedBookings = Booking::whereIn('id', $ids)->with('ticket.event')->get();
                $totalQty = $relatedBookings->count();
                $sampleBooking = $relatedBookings->first();

                if ($sampleBooking) {
                    // ✅ Only one message for all master bookings
                    $this->sendBookingNotification($sampleBooking, true, $totalQty, $entry->order_id);
                }
            } else {
                return false;
            }
        }

        return true;
    }

    private function sendBookingNotification($booking, $isMaster = false, $qty = 1, $orderId = null)
    {
        $smsService = new \App\Services\SmsService();
        $whatsappService = new \App\Services\WhatsappService();
        $whatsappTemplate = \App\Models\WhatsappApi::where('title', 'Online Booking')->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $event = $booking->ticket->event ?? null;
        if (!$event) return;

        // ✅ Fix: Use order_id for master, token for single
        $finalOrderId = $isMaster ? $orderId : $booking->token;

        $shortLinksms = "getyourticket.in/t/{$finalOrderId}";

        // Format event date & time
        $dates = explode(',', $event->date_range ?? '');
        $formattedDates = [];
        foreach ($dates as $date) {
            $formattedDates[] = \Carbon\Carbon::parse($date)->format('d-m-Y');
        }
        $dateRangeFormatted = implode(' | ', $formattedDates);
        $eventDateTime = $dateRangeFormatted . ' | ' . $event->start_time . ' - ' . $event->end_time;

        $mediaurl = $event->thumbnail ?? '';

        $data = (object) [
            'name' => $booking->name ?? 'Guest',
            'number' => $booking->number ?? '0000000000',
            'templateName' => 'Online Booking Template',
            'whatsappTemplateData' => $whatsappTemplateName,
            'shortLink' => $finalOrderId,
            'insta_whts_url' => $event->insta_whts_url ?? 'helloinsta',
            'mediaurl' => $mediaurl,
            'values' => [
                (string) ($booking->name ?? 'Guest'),
                (string) ($booking->number ?? '0000000000'),
                (string) ($event->name ?? 'Event'),
                (string) ($qty),
                (string) ($booking->ticket->name ?? 'Ticket'),
                (string) ($event->address ?? 'Venue'),
                (string) ($eventDateTime ?? 'DateTime'),
                (string) ($event->whts_note ?? 'hello'),
            ],
            'replacements' => [
                ':C_Name' => $booking->name,
                ':T_QTY' => $qty,
                ':Ticket_Name' => $booking->ticket->name ?? 'Ticket',
                ':Event_Name' => $event->name,
                ':Event_Date' => $eventDateTime,
                ':S_Link' => $shortLinksms,
            ]
        ];

        $smsService->send($data);
        $whatsappService->send($data);
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
  
    public function refunded(Request $request, $id, $token, WhatsappService $whatsappService)
    {
        $isMaster = filter_var(
            $request->query('isMaster', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($isMaster) {

            $Masterbookings = MasterBooking::withTrashed()
                ->where('order_id', $token)
                ->latest()
                ->first();

            if (!$Masterbookings) {
                return response()->json([
                    'status' => false,
                    'message' => 'Master booking not found'
                ], 404);
            }

            $bookingIds = is_array($Masterbookings->booking_id)
                ? $Masterbookings->booking_id
                : json_decode($Masterbookings->booking_id, true);

            if (!empty($bookingIds) && is_array($bookingIds)) {
                $bookings = Booking::withTrashed()
                    ->whereIn('id', $bookingIds)
                    ->get();

                foreach ($bookings as $booking) {
                    $booking->is_refunded = 1;
                    $booking->refunded_at = now();
                    $booking->deleted_at = now();
                    $booking->save();

                    // $this->sendRefundNotification($booking, $whatsappService);
                    SendRefundWhatsappJob::dispatch($booking->id);
                }
            }

            $Masterbookings->is_refunded = 1;
            $Masterbookings->refunded_at = now();
            $Masterbookings->deleted_at = now();
            $Masterbookings->save();

            return response()->json([
                'status' => true,
                'message' => 'Master Booking and related bookings refunded successfully'
            ], 200);
        }

        // NORMAL BOOKING FLOW
        $normalBooking = Booking::withTrashed()
            ->where('token', $token)
            ->first();

        if (!$normalBooking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        $normalBooking->is_refunded = 1;
        $normalBooking->refunded_at = now();
        $normalBooking->deleted_at = now();
        $normalBooking->save();

        // $this->sendRefundNotification($normalBooking, $whatsappService);
        SendRefundWhatsappJob::dispatch($normalBooking->id);

        // Send Email
        //if ($booking->email) {
        //    Mail::to($booking->email)->send(new RefundBookingMail($booking));
        //}

        return response()->json([
            'status' => true,
            'message' => 'Booking refunded successfully'
        ], 200);
    }
}
