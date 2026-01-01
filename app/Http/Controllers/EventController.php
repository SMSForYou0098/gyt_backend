<?php

namespace App\Http\Controllers;

use App\Exports\EventExport;
use App\Models\AgentEvent;
use App\Models\AgentMaster;
use App\Models\Banner;
use App\Models\Category;
use App\Models\CatLayout;
use App\Models\Event;
use App\Models\MasterBooking;
use App\Models\PenddingBookingsMaster;
use App\Models\SeatConfig;
use App\Models\User;
use App\Services\EventKeyGeneratorService;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Storage;
use DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class EventController extends Controller
{
    protected $keyGenerator;

    public function __construct(EventKeyGeneratorService $keyGenerator)
    {
        $this->keyGenerator = $keyGenerator;
    }


    public function FeatureEvent()
    {
        $today = Carbon::today();


        $query = Event::where('status', "1")->where('event_feature', 1)->with([
            'tickets' => function ($query) {
                $query->select('id', 'event_id', 'price', 'sale_price', 'sale', 'booking_not_open', 'sold_out', 'fast_filling', 'status');
            },
            'user' => function ($query) {
                $query->select('id', 'name', 'organisation'); // Include fields you want to retrieve
            }
        ]);
        $events = $query->get(['id', 'name', 'thumbnail', 'event_key', 'date_range', 'category', 'city', 'user_id']);
        // Check if any events are fetched
        if ($events->isEmpty()) {
            return response()->json(['status' => true, 'message' => 'No events found with status 1'], 200);
        }

        // Initialize arrays for ongoing and future events
        $ongoingEvents = collect();
        $futureEvents = collect();

        // Categorize events
        foreach ($events as $event) {
            $dates = array_map('trim', explode(',', $event->date_range));
            // Handle single date or date range
            if (count($dates) === 1) {
                $startDate = Carbon::parse($dates[0]);
                $endDate = $startDate; // Set endDate the same as startDate for single date
            } else {
                [$startDate, $endDate] = array_map('trim', $dates);
                $startDate = Carbon::parse($startDate);
                $endDate = Carbon::parse($endDate);
            }

            if ($today->between($startDate, $endDate)) {
                $ongoingEvents->push($event);
            } elseif ($today->lt($startDate)) {
                $futureEvents->push($event);
            }
        }

        // Sort events by start date
        $ongoingEvents = $ongoingEvents->sortBy(fn($event) => Carbon::parse(explode(',', $event->date_range)[0]));
        $futureEvents = $futureEvents->sortBy(fn($event) => Carbon::parse(explode(',', $event->date_range)[0]));

        // Combine ongoing and future events
        $sortedEvents = $ongoingEvents->merge($futureEvents);

        // Process events for additional fields
        $sortedEvents->transform(function ($event) {
            $activeTickets = $event->tickets->where('status', 1);
            $event->lowest_ticket_price = $activeTickets->min('price');
            if ($event->lowest_ticket_price === null) {
                $event->ticket_close = 'Booking Closed';
            }
            // $event->lowest_ticket_price = $event->tickets->min('price');
            $event->lowest_sale_price = $event->tickets->min('sale_price');
            $event->on_sale = $event->tickets->contains('sale', 1);
            $event->fast_filling = $event->tickets->contains('fast_filling', 1);
            $event->booking_close = $event->tickets->every(fn($ticket) => $ticket->sold_out === 1);
            $event->booking_not_start = $event->tickets->every(fn($ticket) => $ticket->booking_not_open === 1);
            unset($event->tickets);
            return $event;
        });

        return response()->json(['status' => 'true', 'events' => $sortedEvents], 200);
    }

    public function indexUsingDB(Request $request)
    {
        $today = Carbon::today();

        // Fetch events with status 1 and calculate necessary fields
        $events = Event::where('status', "1")
            ->leftJoin('tickets', 'events.id', '=', 'tickets.event_id')
            ->select(
                'events.id',
                'events.name',
                'events.thumbnail',
                'events.event_key',
                'events.date_range',
                DB::raw('MIN(tickets.price) as lowest_ticket_price'),
                DB::raw('MIN(tickets.sale_price) as lowest_sale_price'),
                DB::raw('MAX(tickets.sale = "true") as on_sale')
            )
            ->groupBy(
                'events.id',
                'events.name',
                'events.thumbnail',
                'events.event_key',
                'events.date_range'
            )
            ->get();

        // Check if any events are fetched
        if ($events->isEmpty()) {
            return response()->json(['status' => true, 'message' => 'No events found with status 1'], 200);
        }

        // Initialize arrays for ongoing and future events
        $ongoingEvents = collect();
        $futureEvents = collect();

        // Categorize events
        foreach ($events as $event) {
            [$startDate, $endDate] = array_map('trim', explode(',', $event->date_range));
            $startDate = Carbon::parse($startDate);
            $endDate = Carbon::parse($endDate);

            if ($today->between($startDate, $endDate)) {
                $ongoingEvents->push($event);
            } elseif ($today->lt($startDate)) {
                $futureEvents->push($event);
            }
        }

        // Sort events by start date
        $ongoingEvents = $ongoingEvents->sortBy(fn($event) => Carbon::parse(explode(',', $event->date_range)[0]));
        $futureEvents = $futureEvents->sortBy(fn($event) => Carbon::parse(explode(',', $event->date_range)[0]));

        // Combine ongoing and future events
        $sortedEvents = $ongoingEvents->merge($futureEvents);

        return response()->json(['status' => true, 'events' => $sortedEvents], 200);
    }

    public function index(Request $request)
    {
        $today = Carbon::today();
        $categoryTitle = $request->category;
        $bookingType = $request->type;

        $query = Event::where('status', "1")->with([
            'tickets' => function ($query) {
                $query->select('id', 'event_id', 'price', 'sale_price', 'sale', 'booking_not_open', 'sold_out', 'status');
            },
            'user' => function ($query) {
                $query->select('id', 'name', 'organisation'); // Include fields you want to retrieve
            },
            'category' => function ($query) {
                $query->select('id', 'title'); // Fetch category title
            }
        ]);
        $bookingTypeFields = [
            'online' => 'online_booking',
            'agent' => 'agent_booking',
            'sponsor' => 'sponsor_booking',
            'pos' => 'pos_booking',
            'complimentary' => 'complimentary_booking',
            'exhibition' => 'exhibition_booking',
            'amusement' => 'amusement_booking',
        ];

        if ($bookingType && isset($bookingTypeFields[$bookingType])) {
            $query->where($bookingTypeFields[$bookingType], 1);
        }

        if ($categoryTitle) {
            $category = Category::where('title', $categoryTitle)->select('id')->first();
            if ($category) {
                $events = $query->where('category', $category->id)
                    ->get(['id', 'name', 'thumbnail', 'event_key', 'recommendation', 'date_range', 'category', 'city', 'user_id']);
            } else {
                return response()->json(['status' => false, 'message' => 'Category not found'], 404);
            }
        } else {
            $events = $query->get(['id', 'name', 'thumbnail', 'event_key', 'recommendation', 'date_range', 'category', 'city', 'user_id']);
        }
        // Check if any events are fetched
        if ($events->isEmpty()) {
            return response()->json(['status' => true, 'message' => 'No events found with status 1'], 200);
        }

        // Initialize arrays for ongoing and future events
        $ongoingEvents = collect();
        $futureEvents = collect();

        // Categorize events
        foreach ($events as $event) {
            $dates = array_map('trim', explode(',', $event->date_range));
            // Handle single date or date range
            if (count($dates) === 1) {
                $startDate = Carbon::parse($dates[0]);
                $endDate = $startDate; // Set endDate the same as startDate for single date
            } else {
                [$startDate, $endDate] = array_map('trim', $dates);
                $startDate = Carbon::parse($startDate);
                $endDate = Carbon::parse($endDate);
            }

            if ($today->between($startDate, $endDate)) {
                $ongoingEvents->push($event);
            } elseif ($today->lt($startDate)) {
                $futureEvents->push($event);
            }
        }

        // Sort events by start date
        $ongoingEvents = $ongoingEvents->sortBy(fn($event) => Carbon::parse(explode(',', $event->date_range)[0]));
        $futureEvents = $futureEvents->sortBy(fn($event) => Carbon::parse(explode(',', $event->date_range)[0]));

        // Combine ongoing and future events
        $sortedEvents = $ongoingEvents->merge($futureEvents);

        // Process events for additional fields
        $sortedEvents->transform(function ($event) {
            $activeTickets = $event->tickets->where('status', 1);
            $event->lowest_ticket_price = $activeTickets->min('price');
            if ($event->lowest_ticket_price === null) {
                $event->ticket_close = 'Booking Closed';
            }
            // $event->lowest_ticket_price = $event->tickets->min('price');
            $event->lowest_sale_price = $event->tickets->min('sale_price');
            // $event->on_sale = $event->tickets->contains('sale', "true");
            // $event->booking_close = $event->tickets->every(fn($ticket) => $ticket->sold_out === true || $ticket->sold_out === 'true');
            // $event->booking_not_start = $event->tickets->every(fn($ticket) => $ticket->booking_not_open === true || $ticket->booking_not_open === 'true');
            $event->on_sale = $event->tickets->contains('sale', 1);
            $event->booking_close = $event->tickets->every(fn($ticket) => $ticket->sold_out === 1);
            $event->booking_not_start = $event->tickets->every(fn($ticket) => $ticket->booking_not_open === 1);
            unset($event->tickets); // Remove tickets relation if not needed in the response
            return $event;
        });

        return response()->json(['status' => true, 'events' => $sortedEvents], 200);
    }

    public function dayWiseEvents($day)
    {
        $targetDate = ($day === 'tomorrow')
            ? now()->addDay()->format('Y-m-d')
            : now()->format('Y-m-d');

        // Fetch events where targetDate falls between date_range start and end
        $events = Event::whereRaw("
        STR_TO_DATE(SUBSTRING_INDEX(date_range, ',', 1), '%Y-%m-%d') <= ?
        AND STR_TO_DATE(SUBSTRING_INDEX(date_range, ',', -1), '%Y-%m-%d') >= ?
    ", [$targetDate, $targetDate])->get();

        return response()->json([
            'status' => true,
            'date' => $targetDate,
            'data' => $events,
        ], 200);
    }

    public function junk()
    {
        $today = Carbon::today()->toDateString();
        $events = Event::onlyTrashed()->where('status', 1)
            ->where(function ($query) use ($today) {
                // Check for single-day events or multi-day events
                $query->where(function ($subQuery) use ($today) {
                    // Single-day events
                    $subQuery->whereRaw('? = DATE_FORMAT(SUBSTRING_INDEX(date_range, ",", 1), "%Y-%m-%d")', [$today])
                        ->orWhereRaw('? < DATE_FORMAT(SUBSTRING_INDEX(date_range, ",", 1), "%Y-%m-%d")', [$today]);
                })
                    ->orWhere(function ($subQuery) use ($today) {
                        // Multi-day events
                        $subQuery->whereRaw('? <= DATE_FORMAT(SUBSTRING_INDEX(date_range, ",", -1), "%Y-%m-%d")', [$today]);
                    });
            })
            ->get();
        foreach ($events as $event) {
            // Get the minimum ticket price for the event
            $event->lowest_ticket_price = $event->tickets->min('price');
            $event->lowest_sale_price = $event->tickets->min('sale_price');
        }
        return response()->json(['status' => true, 'events' => $events], 200);
    }

    public function eventList($id)
    {
        $loggedInUser = Auth::user()->load('reportingUser');
        $today = Carbon::today()->toDateString();

        if ($loggedInUser->hasRole('Admin')) {
            $eventsQuery = Event::query();
        } else {
            $reporting_user = $loggedInUser->reportingUser;

            if ($reporting_user) {
                // reporting_user છે
                $reporting_userAdmin = $reporting_user->roles->pluck('name')->first();

                if ($reporting_userAdmin == 'Admin' || $reporting_userAdmin == 'Organizer') {
                    $eventsQuery = Event::where('user_id', $loggedInUser->id);
                } else {
                    $eventsQuery = Event::where('user_id', $loggedInUser->id)
                        ->orWhere('user_id', $reporting_user->id);
                }
            } else {
                // Organizer કે જેનો reporting_user નથી
                $eventsQuery = Event::where('user_id', $loggedInUser->id);
            }
        }

        $events = $eventsQuery
            ->select('id', 'user_id', 'category', 'name', 'date_range', 'created_at', 'event_type', 'event_key', 'status')
            ->with([
                'tickets:id,event_id,price,sale_price',
                'user:id,name',
                'Category:id,title'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Process events
        foreach ($events as $event) {
            $dateRange = explode(',', $event->date_range);

            if (count($dateRange) == 1) {
                $eventDate = Carbon::parse(trim($dateRange[0]));

                if ($today == $eventDate->toDateString()) {
                    $event->event_status = 1; // Ongoing
                } elseif ($today < $eventDate->toDateString()) {
                    $event->event_status = 2; // Upcoming
                } else {
                    $event->event_status = 3; // Past
                }
            } else {
                $startDate = Carbon::parse(trim($dateRange[0]));
                $endDate = Carbon::parse(trim($dateRange[1]));

                if ($today >= $startDate->toDateString() && $today <= $endDate->toDateString()) {
                    $event->event_status = 1; // Ongoing
                } elseif ($today < $startDate->toDateString()) {
                    $event->event_status = 2; // Upcoming
                } else {
                    $event->event_status = 3; // Past
                }
            }

            // Get the lowest ticket price
            $event->lowest_ticket_price = $event->tickets->min('price') ?? 0;
            $event->lowest_sale_price = $event->tickets->min('sale_price') ?? 0;
        }

        return response()->json(['status' => true, 'events' => $events], 200);
    }

    // public function eventList($id)
    // {
    //     $loggedInUser = Auth::user()->load('reportingUser');
    //     $today = Carbon::today()->toDateString();

    //     if ($loggedInUser->hasRole('Admin')) {
    //         // Admin gets all events
    //         $eventsQuery = Event::query();
    //     } else {
    //         // POS user logic
    //         $reporting_user = $loggedInUser->reportingUser;
    //         $reporting_userAdmin = $reporting_user->roles->pluck('name')->first();

    //         // Filter only today's events for POS
    //         $eventsQuery = Event::query()->where(function ($query) use ($loggedInUser, $reporting_user, $reporting_userAdmin) {
    //             $query->where('user_id', $loggedInUser->id);

    //             if ($reporting_userAdmin != 'Admin') {
    //                 $query->orWhere('user_id', $reporting_user->id);
    //             }
    //         })->where(function ($query) use ($today) {
    //             $query->whereDate(DB::raw("SUBSTRING_INDEX(date_range, ',', 1)"), '<=', $today)
    //                 ->where(function ($sub) use ($today) {
    //                     $sub->whereDate(DB::raw("SUBSTRING_INDEX(date_range, ',', -1)"), '>=', $today)
    //                         ->orWhereRaw("date_range NOT LIKE '%,%' AND DATE(date_range) = ?", [$today]);
    //                 });
    //         });
    //     }

    //     $events = $eventsQuery
    //         ->select('id', 'user_id', 'category', 'name', 'date_range', 'created_at', 'event_type', 'event_key')
    //         ->with([
    //             'tickets:id,event_id,price,sale_price',
    //             'user:id,name',
    //             'Category:id,title'
    //         ])
    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     // Process tickets for price info
    //     foreach ($events as $event) {
    //         $event->lowest_ticket_price = $event->tickets->min('price') ?? 0;
    //         $event->lowest_sale_price = $event->tickets->min('sale_price') ?? 0;
    //     }

    //     return response()->json(['status' => true, 'events' => $events], 200);
    // }

    // public function eventByUser(Request $request, $id)
    // {
    //     $bookingType = $request->type;
    //     $isPos = $request->isPos;
    //     // return response()->json($bookingType);
    //     $loggedInUser = Auth::user();
    //     $today = Carbon::today()->toDateString();

    //     if ($loggedInUser && $loggedInUser->hasRole('Agent') || $loggedInUser && $loggedInUser->hasRole('Sponsor') || $loggedInUser && $loggedInUser->hasRole('Accreditation')) {

    //         $agentEvent = AgentEvent::where('user_id', $loggedInUser->id)->first();
    //         if ($agentEvent && $agentEvent->event_id) {
    //             $eventIds = json_decode($agentEvent->event_id, true);
    //             $eventsQuery = Event::whereIn('id', $eventIds)->with('tickets', 'user');


    //             if ($bookingType) {
    //                 $bookingField = match ($bookingType) {
    //                     'online' => 'online_booking',
    //                     'agent' => 'agent_booking',
    //                     'sponsor' => 'sponsor_booking',
    //                     'pos' => 'pos_booking',
    //                     'complimentary' => 'complimentary_booking',
    //                     'exhibition' => 'exhibition_booking',
    //                     'amusement' => 'amusement_booking',
    //                     default => null
    //                 };

    //                 if ($bookingField) {
    //                     $eventsQuery->where($bookingField, 1);
    //                 }
    //             }
    //             //$events = $eventsQuery->get();
    //             $events = $eventsQuery->get()->filter(function ($event) use ($today, $loggedInUser) {
    //                 $dateRange = explode(',', $event->date_range);

    //                 if (count($dateRange) == 1) {
    //                     $eventDate = Carbon::parse(trim($dateRange[0]));

    //                     if ($loggedInUser->hasRole('Admin')) {
    //                         return true;
    //                     } else {
    //                         return $eventDate->toDateString() === $today;
    //                     }
    //                 } elseif (count($dateRange) == 2) {
    //                     $startDate = Carbon::parse(trim($dateRange[0]));
    //                     $endDate = Carbon::parse(trim($dateRange[1]));

    //                     if ($loggedInUser->hasRole('Admin')) {
    //                         return true;
    //                     } else {
    //                         return $today >= $startDate->toDateString() && $today <= $endDate->toDateString();
    //                     }
    //                 }

    //                 return false;
    //             });

    //             // Process events
    //             foreach ($events as $event) {
    //                 $dateRange = explode(',', $event->date_range);

    //                 if (count($dateRange) == 1) {
    //                     // Single day event
    //                     $eventDate = Carbon::parse(trim($dateRange[0]));

    //                     if ($today == $eventDate->toDateString()) {
    //                         $event->event_status = 1; // Ongoing
    //                     } elseif ($today < $eventDate->toDateString()) {
    //                         $event->event_status = 2; // Upcoming
    //                     } else {
    //                         $event->event_status = 3; // Past
    //                     }
    //                 } else {
    //                     // Multi-day event
    //                     $startDate = Carbon::parse(trim($dateRange[0]));
    //                     $endDate = Carbon::parse(trim($dateRange[1]));

    //                     if ($today >= $startDate->toDateString() && $today <= $endDate->toDateString()) {
    //                         $event->event_status = 1; // Ongoing
    //                     } elseif ($today < $startDate->toDateString()) {
    //                         $event->event_status = 2; // Upcoming
    //                     } else {
    //                         $event->event_status = 3; // Past
    //                     }
    //                 }

    //                 // Get the lowest ticket price
    //                 $event->lowest_ticket_price = $event->tickets->min('price') ?? 0;
    //                 $event->lowest_sale_price = $event->tickets->min('sale_price') ?? 0;
    //             }
    //             $events = $events->filter(fn($event) => $event->event_status === 1 || $event->event_status === 2);
    //             return response()->json(['status' => true, 'events' => $events->values()], 200);
    //         } else {
    //             return response()->json(['status' => false, 'message' => 'No events found for this agent'], 200);
    //         }
    //     } else {
    //         if ($loggedInUser->hasRole('Admin')) {
    //             $eventsQuery = Event::with('tickets', 'user');
    //         } else {
    //             $reporting_user = $loggedInUser->reporting_user;
    //             $eventsQuery = Event::where('user_id', $id)
    //                 ->orWhere('user_id', $reporting_user)
    //                 ->with('tickets', 'user');
    //         }

    //         if ($bookingType) {
    //             $bookingField = match ($bookingType) {
    //                 'online' => 'online_booking',
    //                 'agent' => 'agent_booking',
    //                 'sponsor' => 'sponsor_booking',
    //                 'pos' => 'pos_booking',
    //                 'complimentary' => 'complimentary_booking',
    //                 'exhibition' => 'exhibition_booking',
    //                 'amusement' => 'amusement_booking',
    //                 default => null
    //             };

    //             if ($bookingField) {
    //                 $eventsQuery->where($bookingField, 1);
    //             }
    //         }
    //         //$events = $eventsQuery->get();
    //         $events = $eventsQuery->get()->filter(function ($event) use ($today, $loggedInUser) {
    //             $dateRange = explode(',', $event->date_range);

    //             if (count($dateRange) == 1) {
    //                 $eventDate = Carbon::parse(trim($dateRange[0]));

    //                 if ($loggedInUser->hasRole('Admin')) {
    //                     return true;
    //                 } else {
    //                     return $eventDate->toDateString() === $today;
    //                 }
    //             } elseif (count($dateRange) == 2) {
    //                 $startDate = Carbon::parse(trim($dateRange[0]));
    //                 $endDate = Carbon::parse(trim($dateRange[1]));

    //                 if ($loggedInUser->hasRole('Admin')) {
    //                     return true;
    //                 } else {
    //                     return $today >= $startDate->toDateString() && $today <= $endDate->toDateString();
    //                 }
    //             }

    //             return false;
    //         });

    //         // Process events
    //         foreach ($events as $event) {
    //             $dateRange = explode(',', $event->date_range);

    //             if (count($dateRange) == 1) {
    //                 // Single day event
    //                 $eventDate = Carbon::parse(trim($dateRange[0]));

    //                 if ($today == $eventDate->toDateString()) {
    //                     $event->event_status = 1; // Ongoing
    //                 } elseif ($today < $eventDate->toDateString()) {
    //                     $event->event_status = 2; // Upcoming
    //                 } else {
    //                     $event->event_status = 3; // Past
    //                 }
    //             } else {
    //                 // Multi-day event
    //                 $startDate = Carbon::parse(trim($dateRange[0]));
    //                 $endDate = Carbon::parse(trim($dateRange[1]));

    //                 if ($today >= $startDate->toDateString() && $today <= $endDate->toDateString()) {
    //                     $event->event_status = 1; // Ongoing
    //                 } elseif ($today < $startDate->toDateString()) {
    //                     $event->event_status = 2; // Upcoming
    //                 } else {
    //                     $event->event_status = 3; // Past
    //                 }
    //             }

    //             Log::info('Event ID: ' . $event->id . ' - Date Range: ' . $event->date_range . ' - Status: ' . $event->event_status);



    //             // Get the lowest ticket price
    //             $event->lowest_ticket_price = $event->tickets->min('price') ?? 0;
    //             $event->lowest_sale_price = $event->tickets->min('sale_price') ?? 0;
    //         }
    //         $events = $events->filter(fn($event) => $event->event_status == 1 || $event->event_status == 2);
    //         return response()->json(['status' => true, 'events' => $events->values()], 200);
    //     }
    // }
    public function eventByUser(Request $request, $id)
    {

        $bookingType = $request->type;
        $loggedInUser = Auth::user();
        $today = Carbon::today()->toDateString();

        $bookingField = match ($bookingType) {
            'online' => 'online_booking',
            'agent' => 'agent_booking',
            'sponsor' => 'sponsor_booking',
            'pos' => 'pos_booking',
            'complimentary' => 'complimentary_booking',
            'exhibition' => 'exhibition_booking',
            'amusement' => 'amusement_booking',
            default => null
        };

        if (
            $loggedInUser && (
                $loggedInUser->hasRole('Agent') ||
                $loggedInUser->hasRole('Sponsor') ||
                $loggedInUser->hasRole('Accreditation')
            )
        ) {
            $agentEvent = AgentEvent::where('user_id', $loggedInUser->id)->first();

            if ($agentEvent && $agentEvent->event_id) {
                $eventIds = json_decode($agentEvent->event_id, true);
                $eventsQuery = Event::whereIn('id', $eventIds)->with('tickets', 'user');

                if ($bookingField) {
                    $eventsQuery->where($bookingField, 1);
                }

                $events = $eventsQuery->get();
            } else {
                return response()->json(['status' => false, 'message' => 'No events found for this agent'], 200);
            }
        } elseif ($loggedInUser->hasRole('Organizer')) {
            // ✅ Organizer condition: fetch only events created by this organizer
            $eventsQuery = Event::where('user_id', $loggedInUser->id)->with('tickets', 'user');

            if ($bookingField) {
                $eventsQuery->where($bookingField, 1);
            }

            $events = $eventsQuery->get();
        } else {
            if ($loggedInUser->hasRole('Admin')) {
                $eventsQuery = Event::with('tickets', 'user');
            } else {
                $reporting_user = $loggedInUser->reporting_user;
                $eventsQuery = Event::where('user_id', $id)
                    ->orWhere('user_id', $reporting_user)
                    ->with('tickets', 'user');
            }

            if ($bookingField) {
                $eventsQuery->where($bookingField, 1);
            }

            $events = $eventsQuery->get();
        }

        // Process each event: status and ticket prices
        foreach ($events as $event) {
            $event->event_status = $this->calculateEventStatus($event->date_range, $today);
            $event->lowest_ticket_price = $event->tickets->min('price') ?? 0;
            $event->lowest_sale_price = $event->tickets->min('sale_price') ?? 0;
        }

        // Filter: only ongoing or upcoming (exclude past)
        $events = $events->filter(fn($event) => $event->event_status === 1 || $event->event_status === 2);

        return response()->json(['status' => true, 'events' => $events->values()], 200);
    }

    private function calculateEventStatus($dateRangeString, $today)
    {
        $dateRange = explode(',', $dateRangeString);

        if (count($dateRange) === 1) {
            $eventDate = Carbon::parse(trim($dateRange[0]));
            if ($today == $eventDate->toDateString()) return 1; // Ongoing
            elseif ($today < $eventDate->toDateString()) return 2; // Upcoming
            else return 3; // Past
        }

        if (count($dateRange) === 2) {
            $startDate = Carbon::parse(trim($dateRange[0]));
            $endDate = Carbon::parse(trim($dateRange[1]));
            if ($today >= $startDate->toDateString() && $today <= $endDate->toDateString()) return 1; // Ongoing
            elseif ($today < $startDate->toDateString()) return 2; // Upcoming
            else return 3; // Past
        }

        return 3; // Default to past if parsing fails
    }


    // public function eventByUser(Request $request, $id)
    // {
    //     $bookingType = $request->type;
    //     // return response()->json($bookingType);
    //     $loggedInUser = Auth::user();
    //     $today = Carbon::today()->toDateString();

    //     if ($loggedInUser && $loggedInUser->hasRole('Agent') || $loggedInUser && $loggedInUser->hasRole('Sponsor') || $loggedInUser && $loggedInUser->hasRole('Accreditation')) {

    //         $agentEvent = AgentEvent::where('user_id', $loggedInUser->id)->first();
    //         if ($agentEvent && $agentEvent->event_id) {
    //             $eventIds = json_decode($agentEvent->event_id, true);
    //             $eventsQuery = Event::whereIn('id', $eventIds)->with('tickets', 'user');


    //             if ($bookingType) {
    //                 $bookingField = match ($bookingType) {
    //                     'online' => 'online_booking',
    //                     'agent' => 'agent_booking',
    //                     'sponsor' => 'sponsor_booking',
    //                     'pos' => 'pos_booking',
    //                     'complimentary' => 'complimentary_booking',
    //                     'exhibition' => 'exhibition_booking',
    //                     'amusement' => 'amusement_booking',
    //                     default => null
    //                 };

    //                 if ($bookingField) {
    //                     $eventsQuery->where($bookingField, 1);
    //                 }
    //             }

    //             $events = $eventsQuery->get()->filter(function ($event) use ($today, $loggedInUser) {
    //                 $dateRange = explode(',', $event->date_range);

    //                 if (count($dateRange) == 1) {
    //                     $eventDate = Carbon::parse(trim($dateRange[0]));

    //                     if ($loggedInUser->hasRole('Admin')) {
    //                         return true;
    //                     } else {
    //                         return $eventDate->toDateString() === $today;
    //                     }
    //                 } elseif (count($dateRange) == 2) {
    //                     $startDate = Carbon::parse(trim($dateRange[0]));
    //                     $endDate = Carbon::parse(trim($dateRange[1]));

    //                     if ($loggedInUser->hasRole('Admin')) {
    //                         return true;
    //                     } else {
    //                         return $today >= $startDate->toDateString() && $today <= $endDate->toDateString();
    //                     }
    //                 }

    //                 return false;
    //             });

    //             // Process events
    //             foreach ($events as $event) {
    //                 $dateRange = explode(',', $event->date_range);

    //                 if (count($dateRange) == 1) {
    //                     // Single day event
    //                     $eventDate = Carbon::parse(trim($dateRange[0]));

    //                     if ($today == $eventDate->toDateString()) {
    //                         $event->event_status = 1; // Ongoing
    //                     } elseif ($today < $eventDate->toDateString()) {
    //                         $event->event_status = 2; // Upcoming
    //                     } else {
    //                         $event->event_status = 3; // Past
    //                     }
    //                 } else {
    //                     // Multi-day event
    //                     $startDate = Carbon::parse(trim($dateRange[0]));
    //                     $endDate = Carbon::parse(trim($dateRange[1]));

    //                     if ($today >= $startDate->toDateString() && $today <= $endDate->toDateString()) {
    //                         $event->event_status = 1; // Ongoing
    //                     } elseif ($today < $startDate->toDateString()) {
    //                         $event->event_status = 2; // Upcoming
    //                     } else {
    //                         $event->event_status = 3; // Past
    //                     }
    //                 }

    //                 // Get the lowest ticket price
    //                 $event->lowest_ticket_price = $event->tickets->min('price') ?? 0;
    //                 $event->lowest_sale_price = $event->tickets->min('sale_price') ?? 0;
    //             }

    //             return response()->json(['status' => true, 'events' => $events->values()], 200);
    //         } else {
    //             return response()->json(['status' => false, 'message' => 'No events found for this agent'], 200);
    //         }
    //     } else {
    //         if ($loggedInUser->hasRole('Admin')) {
    //             $eventsQuery = Event::with('tickets', 'user');
    //         } else {
    //             $reporting_user = $loggedInUser->reporting_user;
    //             $eventsQuery = Event::where('user_id', $id)
    //                 ->orWhere('user_id', $reporting_user)
    //                 ->with('tickets', 'user');
    //         }

    //         if ($bookingType) {
    //             $bookingField = match ($bookingType) {
    //                 'online' => 'online_booking',
    //                 'agent' => 'agent_booking',
    //                 'sponsor' => 'sponsor_booking',
    //                 'pos' => 'pos_booking',
    //                 'complimentary' => 'complimentary_booking',
    //                 'exhibition' => 'exhibition_booking',
    //                 'amusement' => 'amusement_booking',
    //                 default => null
    //             };

    //             if ($bookingField) {
    //                 $eventsQuery->where($bookingField, 1);
    //             }
    //         }

    //         $events = $eventsQuery->get()->filter(function ($event) use ($today, $loggedInUser) {
    //             $dateRange = explode(',', $event->date_range);

    //             if (count($dateRange) == 1) {
    //                 $eventDate = Carbon::parse(trim($dateRange[0]));

    //                 if ($loggedInUser->hasRole('Admin')) {
    //                     return true;
    //                 } else {
    //                     return $eventDate->toDateString() === $today;
    //                 }
    //             } elseif (count($dateRange) == 2) {
    //                 $startDate = Carbon::parse(trim($dateRange[0]));
    //                 $endDate = Carbon::parse(trim($dateRange[1]));

    //                 if ($loggedInUser->hasRole('Admin')) {
    //                     return true;
    //                 } else {
    //                     return $today >= $startDate->toDateString() && $today <= $endDate->toDateString();
    //                 }
    //             }

    //             return false;
    //         });
    //         // Process events
    //         foreach ($events as $event) {
    //             $dateRange = explode(',', $event->date_range);

    //             if (count($dateRange) == 1) {
    //                 // Single day event
    //                 $eventDate = Carbon::parse(trim($dateRange[0]));

    //                 if ($today == $eventDate->toDateString()) {
    //                     $event->event_status = 1; // Ongoing
    //                 } elseif ($today < $eventDate->toDateString()) {
    //                     $event->event_status = 2; // Upcoming
    //                 } else {
    //                     $event->event_status = 3; // Past
    //                 }
    //             } else {
    //                 // Multi-day event
    //                 $startDate = Carbon::parse(trim($dateRange[0]));
    //                 $endDate = Carbon::parse(trim($dateRange[1]));

    //                 if ($today >= $startDate->toDateString() && $today <= $endDate->toDateString()) {
    //                     $event->event_status = 1; // Ongoing
    //                 } elseif ($today < $startDate->toDateString()) {
    //                     $event->event_status = 2; // Upcoming
    //                 } else {
    //                     $event->event_status = 3; // Past
    //                 }
    //             }

    //             // Get the lowest ticket price
    //             $event->lowest_ticket_price = $event->tickets->min('price') ?? 0;
    //             $event->lowest_sale_price = $event->tickets->min('sale_price') ?? 0;
    //         }

    //         return response()->json(['status' => true, 'events' => $events->values()], 200);
    //     }
    // }


    public function info($id)
    {
        try {
            // Assuming the user is authenticated and you have access to the user object
            $user = Auth::user();
            $isAdmin = $user->hasRole('Admin');
            $isScanner = $user->hasRole('Scanner');

            // Get the current date
            $currentDate = Carbon::today()->toDateString();

            // Fetch all active events based on user role and event date
            $events = Event::with(['tickets.bookings', 'tickets.agentBooking', 'tickets.posBookings'])
                ->where(function ($query) use ($user, $currentDate, $isAdmin, $isScanner) {
                    if ($isAdmin) {
                        // Admins see all events that start today
                        $query->whereRaw('SUBSTRING_INDEX(date_range, ",", 1) = ?', [$currentDate]);
                    } else if ($isScanner) {
                        // Scanners see events assigned to their reporting user that start today
                        $query->where('user_id', $user->reporting_user);
                        // ->whereRaw('SUBSTRING_INDEX(date_range, ",", 1) = ?', [$currentDate]);
                        $query->where('date_range', 'LIKE', "%$currentDate%")
                            ->orWhereRaw("? BETWEEN SUBSTRING_INDEX(date_range, ',', 1) AND SUBSTRING_INDEX(date_range, ',', -1)", [$currentDate]);
                    } else {
                        // Non-admin, non-scanner users see their own events that start today
                        $query->where('user_id', $user->id)
                            ->whereRaw('SUBSTRING_INDEX(date_range, ",", 1) = ?', [$currentDate]);
                    }
                })
                ->get();

            // If no active events are found, return a response indicating so
            if ($events->isEmpty()) {
                return response()->json(['status' => false, 'message' => 'No active events found', $user->reportingUser], 404);
            }

            $eventData = [];

            foreach ($events as $event) {
                // Initialize counts
                $totalBookings = 0;
                $remainingBookings = 0;
                $checkedBookings = 0;

                // Loop through each ticket and its bookings to calculate the counts
                foreach ($event->tickets as $ticket) {
                    $totalBookings += $ticket->bookings->count();
                    $totalBookings += $ticket->agentBooking->count();
                    $totalBookings += $ticket->posBookings->sum('quantity');

                    // Calculate remaining bookings (status 0)
                    $remainingBookings += $ticket->bookings->where('status', 0)->count();
                    $remainingBookings += $ticket->agentBooking->where('status', 0)->count();
                    $remainingBookings += $ticket->posBookings->where('status', 0)->sum('quantity');

                    // Calculate checked bookings (status 1)
                    $checkedBookings += $ticket->bookings->where('status', 1)->count();
                    $checkedBookings += $ticket->agentBooking->where('status', 1)->count();
                    $checkedBookings += $ticket->posBookings->where('status', 1)->sum('quantity');
                }

                // Determine the category based on the event_type
                $category = $event->event_type == 'season' ? 'Seasonal' : 'Daily';

                // Prepare the event data
                $eventData[] = [
                    'event' => $event,
                    'total_bookings' => $totalBookings,
                    'remaining_bookings' => $remainingBookings,
                    'checked_bookings' => $checkedBookings,
                    'category' => $category,
                ];
            }

            return response()->json(['status' => true, 'data' => $eventData], 201);
        } catch (\Exception $e) {
            // Return an error response if something goes wrong
            return response()->json(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function create(Request $request)
    {

        try {
            // return response()->json(['event'=>$request->all()], 200);
            $event = new Event();
            $event->user_id = $request->user_id;
            $event->category = $request->category;
            $event->name = $request->name;
            $event->country = $request->country;
            $event->state = $request->state;
            $event->city = $request->city;
            $event->address = $request->address;
            $eventKey = $this->keyGenerator->generateKey();
            $event->event_key = $eventKey;
            $event->short_info = $request->short_info;
            $event->description = $request->description;
            $event->offline_payment_instruction = $request->offline_payment_instruction;
            // $event->customer_care_number = $request->customer_care_number;
            $event->entry_time = $request->entry_time;
            $event->event_feature = $request->event_feature;
            $event->house_full = $request->house_full;
            $event->sms_otp_checkout = $request->sms_otp_checkout;
            $event->rfid_required = $request->rfid_required;
            $event->multi_scan = $request->multi_scan;
            $event->online_att_sug = $request->online_att_sug;
            $event->offline_att_sug = $request->offline_att_sug;
            $event->scan_detail = $request->scan_detail;
            $event->online_booking = $request->online_booking;
            $event->agent_booking = $request->agent_booking;
            $event->pos_booking = $request->pos_booking;
            $event->sponsor_booking = $request->sponsor_booking;
            $event->complimentary_booking = $request->complimentary_booking;
            $event->exhibition_booking = $request->exhibition_booking;
            $event->accreditation_booking = $request->accreditation_booking;
            $event->amusement_booking = $request->amusement_booking;
            $event->ticket_system = $request->ticket_system;
            $event->recommendation = $request->recommendation;
            $event->whatsapp_number = $request->whatsapp_number;
            $event->whts_note = $request->whts_note;
            $event->insta_whts_url = $request->insta_whts_url;
            // $event->access_area = $request->access_area;
            // $event->modify_as = $request->modify_as;
            // $event->pixel_code = $request->pixel_code;
            // $event->analytics_code = $request->analytics_code;

            if ($request->hasFile('layout_image')) {
                $eventName = strtolower(str_replace(' ', '_', $event->name));
                $eventDirectory = "event/" . str_replace(' ', '_', strtolower($request->name));

                $layoutFolder = 'layout_image';
                $file = $request->file('layout_image');
                $fileName = 'get-your-ticket-' . uniqid() . '_' . $file->getClientOriginalName();

                // Ensure the stored path doesn't duplicate the filename
                $storedLayoutPath = $this->storeFile($file, "{$eventDirectory}/{$layoutFolder}", $fileName);

                $event->layout_image = $storedLayoutPath;
            }


            $event->save();
            return response()->json(['status' => true, 'message' => 'Event Created Successfully', 'event' => $event], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create Event', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        //
    }

    public function edit(string $id)
    {
        $event = Event::with([
            'tickets',
            'user',
            'Category:id,title',
            'eventLayout'
        ])->where('event_key', $id)->firstOrFail();
        // $event = Event::with('tickets', 'user','Category')->where('event_key', $id)->firstOrFail();

        $event->lowest_ticket_price = $event->tickets->min('price');
        $event->lowest_sale_price = $event->tickets->min('sale_price');
        $event->on_sale = $event->tickets->contains('sale', "true");
        $event->booking_close = $event->tickets->every(fn($ticket) => $ticket->sold_out === true || $ticket->sold_out === 'true');
        $event->booking_not_start = $event->tickets->every(fn($ticket) => $ticket->donation === true || $ticket->donation === 'true');

        $today = Carbon::today();
        $dateRange = explode(',', $event->date_range);

        if (count($dateRange) === 2) {
            $endDate = Carbon::parse($dateRange[1]);
        } else {
            $endDate = Carbon::parse($dateRange[0]);
        }
        $isExpired = $today->gt($endDate);

        $response = [
            'status' => true,
            'events' => $event,
        ];
        if ($isExpired) {
            $response['event_expired'] = true;
        }

        return response()->json($response, 200);

        // return response()->json(['status' => true, 'events' => $event], 200);
    }

    public function update(Request $request, string $id)
    {
        try {
            $event = Event::where('event_key', $id)->firstOrFail();

            if ($request->has('category')) {
                $request->merge(['category' => strtolower($request->category)]);
            }

            $event->fill($request->only([
                // 'user_id',
                'category',
                'name',
                'country',
                'state',
                'city',
                'address',
                'short_info',
                'description',
                'offline_payment_instruction',
                // 'customer_care_number',
                'event_feature',
                'status',
                'house_full',
                'sms_otp_checkout',
                'date_range',
                'entry_time',
                'start_time',
                'end_time',
                'event_type',
                'map_code',
                'youtube_url',
                'insta_url',
                'multi_qr',
                'status',
                'meta_title',
                'meta_tag',
                'meta_description',
                'meta_keyword',
                'rfid_required',
                'multi_scan',
                'online_att_sug',
                'offline_att_sug',
                'scan_detail',
                'online_booking',
                'agent_booking',
                'pos_booking',
                'complimentary_booking',
                'exhibition_booking',
                'amusement_booking',
                'sponsor_booking',
                'accreditation_booking',
                'ticket_system',
                'recommendation',
                'whatsapp_number',
                // 'access_area',
                'modify_as',
                'whts_note',
                'insta_whts_url',
                // 'pixel_code',
                // 'analytics_code',

            ]));

            if ($request->ticket_terms) {
                $event->ticket_terms = $request->ticket_terms;
            }
            if ($request->ticket_template_id) {
                $event->ticket_template_id = $request->ticket_template_id;
            }


            if ($request->hasFile('thumbnail')) {
                $categoryFolder = str_replace(' ', '_', strtolower($request->category));
                $originalName = $request->file('thumbnail')->getClientOriginalName();
                $fileName = 'get-your-ticket-' . time() . '-' . $originalName;

                // Store new image using storeFile method
                $filePath = $this->storeFile($request->file('thumbnail'), "thumbnail$categoryFolder", 'public');
                $event->thumbnail = $filePath;
            }

            if ($request->hasFile('layout_image')) {
                $eventName = strtolower(str_replace(' ', '_', $event->name));
                $eventDirectory = "event/" . str_replace(' ', '_', strtolower($request->name));

                $layoutFolder = 'layout_image';
                $file = $request->file('layout_image');
                $fileName = 'get-your-ticket-' . uniqid() . '_' . $file->getClientOriginalName();
                $storedLayoutPath = $this->storeFile($file, "{$eventDirectory}/{$layoutFolder}/{$fileName}");
                $event->layout_image = $storedLayoutPath;
            }

            if ($request->hasFile('insta_thumb')) {
                $categoryFolder = str_replace(' ', '_', strtolower($request->category));
                $originalName = $request->file('insta_thumb')->getClientOriginalName();
                $fileName = 'get-your-ticket-' . time() . '-' . $originalName;

                // Store new image using storeFile method
                $filePath = $this->storeFile($request->file('insta_thumb'), "insta_thumb$categoryFolder", 'public');
                $event->insta_thumb = $filePath;
            }

            if ($request->hasFile('card_url')) {
                $categoryFolder = str_replace(' ', '_', strtolower($request->category));
                $originalName = $request->file('card_url')->getClientOriginalName();
                $fileName = 'get-your-ticket-' . time() . '-' . $originalName;

                // Store new image using storeFile method
                $filePath = $this->storeFile($request->file('card_url'), "card_url$categoryFolder", 'public');
                $event->card_url = $filePath;
            }


            $imagePaths = [];
            for ($i = 1; $i <= 4; $i++) {
                if ($request->hasFile("images_" . $i)) {
                    $image = $request->file("images_" . $i);
                    if ($image instanceof \Illuminate\Http\UploadedFile) {
                        $fileName = 'get-your-ticket-' . uniqid() . '_' . $image->getClientOriginalName();
                        $eventDirectory = 'event/' . str_replace(' ', '_', strtolower($request->name));
                        $folder = 'gallery';
                        $path = $this->storeFile($image, "{$eventDirectory}/{$folder}/{$fileName}");
                        $imagePaths[] = $path;
                    }
                }
            }


            $event->images = $imagePaths;

            $event->save();
            $event->load('tickets');

            if ($request->has('layout')) {
                $layout = $request->input('layout');

                if (is_string($layout)) {
                    $layout = json_decode($layout, true);
                }

                if (is_array($layout)) {
                    $this->storeLayout($layout, $id);
                }
            }
            return response()->json(['status' => true, 'message' => 'Event Updated Successfully', 'event' => $event], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to Event user', 'error' => $e->getMessage()], 500);
        }
    }

    private function storeFile($file, $folder, $disk = 'public')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
        return Storage::disk($disk)->url($path);
    }


    public function destroy(string $id)
    {
        $event = Event::findOrFail($id);

        if ($event) {

            $deletedBookingIds = [];
            $deletedAgentBookingIds = [];
            $deletedPenddingBookingIds = [];

            foreach ($event->tickets as $ticket) {

                $deletedBookingIds = array_merge($deletedBookingIds, $ticket->bookings()->pluck('id')->toArray());
                $deletedAgentBookingIds = array_merge($deletedAgentBookingIds, $ticket->agentBooking()->pluck('id')->toArray());
                $deletedPenddingBookingIds = array_merge($deletedPenddingBookingIds, $ticket->PenddingBookings()->pluck('id')->toArray());

                $ticket->bookings()->delete();
                $ticket->agentBooking()->delete();
                $ticket->PenddingBookings()->delete();
                $ticket->complimentaryBookings()->delete();
                $ticket->posBookings()->delete();
            }

            $event->tickets()->delete();

            if (!empty($deletedBookingIds)) {
                MasterBooking::whereJsonContains('booking_id', $deletedBookingIds)->update(['deleted_at' => now()]);
            }
            if (!empty($deletedAgentBookingIds)) {
                AgentMaster::whereJsonContains('booking_id', $deletedAgentBookingIds)->update(['deleted_at' => now()]);
            }
            if (!empty($deletedPenddingBookingIds)) {
                PenddingBookingsMaster::whereJsonContains('booking_id', $deletedPenddingBookingIds)->update(['deleted_at' => now()]);
            }

            $event->delete();

            return response()->json([
                'status' => true,
                'message' => 'Event and all related data deleted successfully'
            ], 200);
        }
    }

    // public function export(Request $request)
    // {

    //     $organizer = $request->input('organizer');
    //     $category = $request->input('category');
    //     $eventType = $request->input('event_type');
    //     $status = $request->input('status');
    //     $eventDates = $request->input('date_range') ? explode(',', $request->input('date_range')) : null;
    //     $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

    //     $query = Event::query();

    //     if ($request->has('organizer')) {
    //         $query->where('user_id', $organizer);
    //     }

    //     if ($request->has('category')) {
    //         $query->where('category', $category);
    //     }

    //     if ($eventType) {
    //         $query->where('event_type', $eventType);
    //     }

    //     if ($request->has('status')) {
    //         $query->where('status', $status);
    //     }

    //     if ($eventDates) {
    //         if (count($eventDates) === 1) {
    //             $singleDate = Carbon::parse($eventDates[0])->toDateString();
    //             $query->whereDate('date_range', $singleDate);
    //         } elseif (count($eventDates) === 2) {
    //             $startDate = Carbon::parse($eventDates[0])->startOfDay();
    //             $endDate = Carbon::parse($eventDates[1])->endOfDay();
    //             $query->whereBetween('date_range', [$startDate, $endDate]);
    //         }
    //     }

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

    //     $events = $query->get();
    //     return Excel::download(new EventExport($events), 'events_export.xlsx');
    // }
    public function export(Request $request)
    {
        $loggedInUser = Auth::user();
        $organizer = $request->input('organizer');
        $category = $request->input('category');
        $eventType = $request->input('event_type');
        $status = $request->input('status');
        $eventDates = $request->input('date_range') ? explode(',', $request->input('date_range')) : null;
        $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

        $query = Event::query()
            ->with('user');

        // Check if user is Admin or not
        if (!$loggedInUser->hasRole('Admin')) {
            // Get user's own events
            $query->where('user_id', $loggedInUser->id);
        }

        // Apply filters
        if ($request->has('organizer')) {
            $query->where('user_id', $organizer);
        }

        if ($request->has('category')) {
            $query->where('category', $category);
        }

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        if ($request->has('status')) {
            $query->where('status', $status);
        }

        if ($eventDates) {
            if (count($eventDates) === 1) {
                $singleDate = Carbon::parse($eventDates[0])->toDateString();
                $query->whereDate('date_range', $singleDate);
            } elseif (count($eventDates) === 2) {
                $startDate = Carbon::parse($eventDates[0])->startOfDay();
                $endDate = Carbon::parse($eventDates[1])->endOfDay();
                $query->whereBetween('date_range', [$startDate, $endDate]);
            }
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

        $events = $query->get()->map(function ($event, $index) {
            return [
                'sr_no' => $index + 1,
                'name' => $event->name,
                'category' => $event->category,
                'organizer' => optional($event->user)->name ?? 'N/A', // Safely access user name
                'event_date' => $event->date_range,
                'event_type' => $event->event_type,
                'status' => match ((string)$event->status) {
                    '0' => 'Ongoing',
                    '1' => 'Upcoming',
                    '2' => 'Finished',
                    default => 'Unknown'
                },
                'organisation' => $event->user->organisation,
            ];
        })->toArray();
        //return response()->json(['status' => true, 'events' => $events], 200);
        return Excel::download(new EventExport($events), 'events_export.xlsx');
    }
    public function eventWhatsapp(Request $request)
    {
        $today = Carbon::today();
        $categoryTitle = $request->category;
        $bookingType = $request->type;

        $query = Event::where('status', "1")->with([
            'tickets' => function ($query) {
                $query->select('id', 'event_id', 'price', 'sale_price', 'sale', 'booking_not_open', 'sold_out');
            },
            'user' => function ($query) {
                $query->select('id', 'name', 'organisation'); // Include fields you want to retrieve
            },
            'category' => function ($query) {
                $query->select('id', 'title'); // Fetch category title
            }
        ]);
        $bookingTypeFields = [
            'online' => 'online_booking',
            'agent' => 'agent_booking',
            'sponsor' => 'sponsor_booking',
            'pos' => 'pos_booking',
            'complimentary' => 'complimentary_booking',
            'exhibition' => 'exhibition_booking',
            'amusement' => 'amusement_booking',
        ];

        if ($bookingType && isset($bookingTypeFields[$bookingType])) {
            $query->where($bookingTypeFields[$bookingType], 1);
        }

        if ($categoryTitle) {
            $category = Category::where('title', $categoryTitle)->select('id')->first();
            if ($category) {
                $events = $query->where('category', $category->id)
                    ->get(['id', 'name', 'thumbnail', 'event_key', 'date_range', 'category', 'city', 'user_id']);
            } else {
                return response()->json(['status' => false, 'message' => 'Category not found'], 404);
            }
        } else {
            $events = $query->get(['id', 'name', 'thumbnail', 'event_key', 'date_range', 'category', 'city', 'user_id']);
        }
        // Check if any events are fetched
        if ($events->isEmpty()) {
            return response()->json(['status' => true, 'message' => 'No events found with status 1'], 200);
        }

        // Initialize arrays for ongoing and future events
        $ongoingEvents = collect();
        $futureEvents = collect();

        // Categorize events
        foreach ($events as $event) {
            $dates = array_map('trim', explode(',', $event->date_range));
            // Handle single date or date range
            if (count($dates) === 1) {
                $startDate = Carbon::parse($dates[0]);
                $endDate = $startDate; // Set endDate the same as startDate for single date
            } else {
                [$startDate, $endDate] = array_map('trim', $dates);
                $startDate = Carbon::parse($startDate);
                $endDate = Carbon::parse($endDate);
            }

            if ($today->between($startDate, $endDate)) {
                $ongoingEvents->push($event);
            } elseif ($today->lt($startDate)) {
                $futureEvents->push($event);
            }
        }

        // Sort events by start date
        $ongoingEvents = $ongoingEvents->sortBy(fn($event) => Carbon::parse(explode(',', $event->date_range)[0]));
        $futureEvents = $futureEvents->sortBy(fn($event) => Carbon::parse(explode(',', $event->date_range)[0]));

        // Combine ongoing and future events
        $sortedEvents = $ongoingEvents->merge($futureEvents);


        $sortedEvents->transform(function ($event) {
            return [
                'e_name' => $event->name . ' - ' . $event->event_key,
                'event_key' => $event->event_key
            ];
        });
        return response()->json(['status' => true, 'events' => $sortedEvents], 200);
    }


    public function editWhatsapp(string $id)
    {
        $parts = explode(' - ', $id);
        $eventKey = end($parts);
        $eventKey = "AA" . ltrim($eventKey, "AA");
        $event = Event::with('tickets')->where('event_key', $eventKey)->firstOrFail();

        // Only return ticket names and prices
        $tickets = $event->tickets->map(function ($ticket) {
            return [
                't_name' => $ticket->name . ' - ' . $ticket->price,
                'price' => $ticket->price
            ];
        });

        return response()->json(['status' => true, 'tickets' => $tickets], 200);
    }

    public function eventData($id)
    {
        try {
            $user = User::findOrFail($id);

            if (! $user->hasRole('Organizer')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not an Organizer.',
                ], 403);
            }

            // $events = Event::where('user_id', $user->id)->get();
            $events = Event::where('user_id', $user->id)
                ->with(['tickets:id,event_id,name'])
                ->get(['id', 'name','date_range']);

            if ($events->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No events found for this organizer.'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $events,
                'message' => 'Events fetched successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function allEventData()
    {
        try {
            // $events = Event::select(['id', 'name', 'card_url', 'category','user_id'])
            //     ->orderByDesc('id')
            //     ->get();

            // $events = Event::with(['user.roles'])
            // ->where('user_id', $userId)
            // ->whereHas('user.roles', function ($query) {
            //     $query->where('name', 'Organizer');
            // })
            // ->orderByDesc('id')
            // ->get();

            $user =  Auth::user();

            if ($user->hasRole('Admin')) {
                $events = Event::with(['user.roles', 'Category'])
                    ->whereHas('user.roles', function ($query) {
                        $query->where('name', 'Organizer');
                    })
                    ->whereHas('Category', function ($query) {
                        $query->where('attendy_required', 1);
                    })
                    ->orderByDesc('id')
                    ->get();
            } elseif ($user->hasRole('Organizer')) {
                $events = Event::with(['user.roles', 'Category'])
                    ->where('user_id', $user->id)
                    ->whereHas('user.roles', function ($query) {
                        $query->where('name', 'Organizer');
                    })
                    ->whereHas('Category', function ($query) {
                        $query->where('attendy_required', 1);
                    })
                    ->orderByDesc('id')
                    ->get();
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized or no role assigned'
                ], 403);
            }

            $formattedEvents = $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'card_url' => $event->card_url,
                    'category' => $event->category,
                    'attendy_required' => $event->Category->attendy_required,
                    'user_id' => $event->user_id,
                    'role' => optional($event->user->roles->first())->name ?? null
                ];
            });

            if ($formattedEvents->isEmpty()) {
                return response()->json(['status' => false, 'message' => 'No events found'], 404);
            }

            return response()->json(['status' => true, 'data' => $formattedEvents], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error fetching events: ' . $e->getMessage()], 500);
        }
    }


    private function storeLayout(array $layout, $categoryId)
    {
        CatLayout::updateOrCreate(
            ['category_id' => $categoryId],
            [
                'user_photo' => isset($layout['userPhoto']) ? json_encode($layout['userPhoto']) : null,
                // 'zones'      => isset($layout['zoneGroup']) ? json_encode($layout['zoneGroup']) : null,
                'qr_code'    => isset($layout['qrCode']) ? json_encode($layout['qrCode']) : null,
                'text_1'     => isset($layout['textValue_0']) ? json_encode($layout['textValue_0']) : null,
                'text_2'     => isset($layout['textValue_1']) ? json_encode($layout['textValue_1']) : null,
                'text_3'     => isset($layout['textValue_2']) ? json_encode($layout['textValue_2']) : null,
            ]
        );
    }

    public function getLayoutByEventId($id)
    {
        // Step 1: Get the event
        $event = Event::where('id', $id)
            ->select(['id', 'event_key'])
            ->with('eventLayout')
            ->first();


        if (!$event) {
            return response()->json([
                'status' => false,
                'message' => 'Event not found.',
            ], 404);
        }
        if (!$event->IDCardLayout) {
            return response()->json([
                'status' => false,
                'message' => 'Layout not found for this event.',
            ], 404);
        }

        // Step 3: Return layout fields (automatically casted to arrays)
        return response()->json([
            'status' => true,
            'layout' => [
                'user_photo' => json_decode($event->IDCardLayout->user_photo, true),
                // 'zones'      => json_decode($event->IDCardLayout->zones, true),
                'qr_code'    => json_decode($event->IDCardLayout->qr_code, true),
                'text_1'     => json_decode($event->IDCardLayout->text_1, true),
                'text_2'     => json_decode($event->IDCardLayout->text_2, true),
                'text_3'     => json_decode($event->IDCardLayout->text_3, true),
            ]
        ]);
    }

    public function pastEvents()
    {
        $today = Carbon::today()->toDateString();
        $eventsQuery = Event::query();

        // Fetch events and calculate if they are past
        $events = $eventsQuery
            ->select('id', 'user_id', 'category', 'name', 'date_range', 'created_at', 'event_type', 'event_key')
            ->where('status', 1)
            ->with(['tickets:id,event_id,price,sale_price', 'user:id,name', 'Category:id,title'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pastEvents = [];

        foreach ($events as $event) {
            $dateRange = explode(',', $event->date_range);

            if (count($dateRange) == 1) {
                // Single-day event
                $eventDate = Carbon::parse(trim($dateRange[0]));
                $isPast = $today > $eventDate->toDateString();
            } else {
                // Multi-day event
                $endDate = Carbon::parse(trim($dateRange[1]));
                $isPast = $today > $endDate->toDateString();
            }

            if ($isPast) {
                $event->event_status = 3; // Past
                $event->lowest_ticket_price = $event->tickets->min('price') ?? 0;
                $event->lowest_sale_price = $event->tickets->min('sale_price') ?? 0;

                $pastEvents[] = $event;
            }
        }

        if (empty($pastEvents)) {
            return response()->json(['status' => false, 'message' => 'No past events found'], 200);
        }

        return response()->json(['status' => true, 'events' => $pastEvents], 200);
    }

    public function handleWebhookTov(Request $request)
    {
        Log::info('Easebuzz TOV Webhook received:', $request->all());
        // Process the webhook data as needed
        // For example, you can log it or save it to the database
        return response()->json(['message' => 'Webhook received successfully'], 200);
    }

    public function landingOrgId(Request $request, $organisation)
    {
        //return $organisation;
        // 🔹 Get all user IDs under this organisation
        $userIds = User::where('organisation', $organisation)->pluck('id');

        if ($userIds->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No users found for this organisation.'
            ], 200);
        }
        $banner = Banner::whereIn('org_id', $userIds)
            ->select('id', 'org_id', 'images', 'title', 'external_url')
            ->get();
        // 🔹 Get all events for all users in that organisation
        $query = Event::with([
            'organizer:id,organisation',
            'tickets' => function ($query) {
                $query->select('id', 'event_id', 'price', 'sale_price', 'sale', 'booking_not_open', 'sold_out', 'status');
            }
        ])
            ->whereIn('user_id', $userIds) // ✅ Use whereIn for multiple users
            ->where('status', 1)
            ->select('id', 'event_key', 'name', 'user_id', 'category', 'date_range', 'city', 'thumbnail');



        $events = $query->orderBy('date_range', 'asc')->get();
        // $events = $query->orderBy('created_at', 'desc')->get();

        if ($events->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No events found for this organisation.'
            ], 200);
        }

        // 🔹 Transform event data
        $events->transform(function ($event) {
            $activeTickets = $event->tickets->where('status', 1);
            $event->lowest_ticket_price = $activeTickets->min('price');
            $event->lowest_sale_price = $activeTickets->min('sale_price');
            $event->on_sale = $activeTickets->contains('sale', 1);
            $event->booking_close = $activeTickets->every(fn($ticket) => $ticket->sold_out === 1);
            $event->booking_not_start = $activeTickets->every(fn($ticket) => $ticket->booking_not_open === 1);
            $event->organisation = $event->organizer->organisation ?? null;
            $event->city = $event->city ?? null;

            unset($event->tickets,  $event->organizer);
            return $event;
        });

        return response()->json([
            'status' => true,
            'data' => $events,
            'banner' => $banner->isEmpty() ? [] : $banner
        ], 200);
    }
}
