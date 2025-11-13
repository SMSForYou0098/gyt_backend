<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AmusementAgentBooking;
use App\Models\AmusementAgentMasterBooking;
use App\Models\Event;
use App\Models\SmsTemplate;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsappApi;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AmusementAgentBookingController extends Controller
{

    // List All Bookings
    public function list(Request $request, $id)
    {
        try {
            $loggedInUser = Auth::user();
            $isAdmin = $loggedInUser->hasRole('Admin');

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
                $Masterbookings = AmusementAgentMasterBooking::withTrashed()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get();

                $allBookingIds = [];
                $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $startDate, $endDate) {
                    $bookingIds = is_array($masterBooking->booking_id) ? $masterBooking->booking_id : json_decode($masterBooking->booking_id);

                    if (is_array($bookingIds)) {
                        $allBookingIds = array_merge($allBookingIds, $bookingIds);
                        $masterBooking->bookings = AmusementAgentBooking::whereIn('id', $bookingIds)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->with(['ticket.event.user', 'user'])
                            ->latest()
                            ->get()
                            ->map(function ($booking) {
                                $booking->agent_name = $booking->agentUser->name ?? '';
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
                $normalBookings = AmusementAgentBooking::withTrashed()
                    ->with(['ticket.event.user', 'user'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get()
                    ->map(function ($booking) {
                        $booking->agent_name = $booking->agentUser->name ?? '';
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
            } else {
                $eventIds = Event::where('user_id', $id)->pluck('id');
                $tickets = Ticket::whereIn('event_id', $eventIds)->pluck('id');

                $Masterbookings = AmusementAgentMasterBooking::withTrashed()
                    ->where('agent_id', $id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->latest()
                    ->get();

                $allBookingIds = [];
                $Masterbookings->each(function ($masterBooking) use (&$allBookingIds, $tickets, $startDate, $endDate) {
                    $bookingIds = $masterBooking->booking_id;

                    if (is_array($bookingIds)) {
                        $allBookingIds = array_merge($allBookingIds, $bookingIds);
                        $masterBooking->bookings = AmusementAgentBooking::whereIn('id', $bookingIds)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            // ->whereHas('ticket', function ($query) use ($tickets) {
                            //     $query->whereIn('id', $tickets);
                            // })
                            ->with(['ticket.event.user', 'user'])
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

                $normalBookings = AmusementAgentBooking::withTrashed()
                    ->with(['ticket.event.user', 'user'])
                    ->where('agent_id', $id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    // ->whereHas('ticket', function ($query) use ($tickets) {
                    //     $query->whereIn('id', $tickets);
                    // })
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
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage() . "on line" . $e->getLine(),
            ], 500);
        }
    }

    //store agent
    public function store(Request $request, $id)
    {
        try {
            $bookings = [];
            $firstIteration = true;
            if ($request->tickets['quantity'] > 0) {
                for ($i = 0; $i < $request->tickets['quantity']; $i++) {
                    $booking = new AmusementAgentBooking();
                    $booking->ticket_id = $request->tickets['id'];
                    $booking->agent_id = $request->agent_id;
                    $booking->user_id = $request->user_id;

                    $ticket = Ticket::findOrFail($request->tickets['id']);
                    $event = $ticket->event;


                    // $booking->token = $this->generateRandomCode();
                    $booking->token = $this->generateHexadecimalCode();
                    $booking->email = $request->email;
                    $booking->name = $request->name;
                    $booking->number = $request->number;
                    $booking->type = $request->type;
                    $booking->payment_method = $request->payment_method;
                    $booking->attendee_id = $request->attendees[$i]['id'] ?? null;
                    $booking->status = 0;
                    $booking->booking_date = $request->booking_date;
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

                    $whatsappTemplate = WhatsappApi::where('title', 'Booking Confirmation')->first();
                    $whatsappTemplateName = $whatsappTemplate->template_name ?? '';
                    // send SMS
                    $smsData = (object)[
                        'eventName' => $event->name,
                        'eventThumbnail' => $event->thumbnail ?? '',
                        'number' => $booking->number,
                        'templateName' => 'Booking Template online',
                        'whatsappTemplateData' => $whatsappTemplateName,
                        'name' => $booking->name,
                        'qty' => $request->tickets['quantity'],
                        'ticketName' => $ticket->name,
                        'credits' => $request->amount ?? '',
                        'ctCredits' => $request->base_amount ?? '',
                        'shopName' => $event->user->name ?? '',
                        'shopKeeperName' => $event->user->shop_keeper_name ?? '',
                        'shopKeeperNumber' => $event->user->shop_keeper_number ?? '',
                        'eventLocation' => $event->address ?? '',
                    ];


                    if ($i === 0) {
                        $this->sendSmsDirect($smsData);
                        $this->sendWhatsappDirect($smsData);
                    }
                }
            }

            return response()->json(['status' => true, 'message' => 'Tickets Booked Successfully', 'bookings' => $bookings], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to book tickets', 'error' => $e->getMessage()], 500);
        }
    }

    //send sms
    private function sendSmsDirect($data)
    {
        $eventName = strlen($data->eventName) > 9 ? substr($data->eventName, 0, 9) . '...' : $data->eventName;
        $number = $data->number;
        $templateName = $data->templateName;
        $config_status = "0"; // Assuming this is set somewhere in your code
        $url = rtrim(env('ALLOWED_DOMAIN', 'https://getyourticket.in/'), '/') . '/dashboard/bookings';

        // $url = 'https://getyourticket.in/dashboard/bookings'; // Assuming this is set somewhere in your code
        $api_key = null; // Assuming this is set somewhere in your code
        $sender_id = null; // Assuming this is set somewhere in your code
        $templateData = SmsTemplate::where('template_name', $templateName)->first();
        if (!$templateData) {
            return ['error' => 'Template not found'];
        }

        $templateID = $templateData->template_id;
        $messages = $templateData->content;

        $finalMessage = str_replace(
            [':C_Name', ':T_QTY', ':Ticket_Name', ':Event_Name', ':C_number', ':Credits', ':CT_Credits', ':Shop_Name', ':Shop_Keeper_Name', ':Shop_Keeper_Number'],
            [
                $data->name,
                $data->qty,
                $data->ticketName,
                $eventName,
                $number,
                $data->credits,
                $data->ctCredits,
                $data->shopName,
                $data->shopKeeperName,
                $data->shopKeeperNumber
            ],
            $messages
        );

        if ($config_status === "0") {
            $admin = User::role('Admin', 'api')->with('smsConfig')->first();
            if ($admin && $admin->smsConfig) {
                $smsConfig = $admin->smsConfig[0];
                $api_key = $smsConfig->api_key;
                $sender_id = $smsConfig->sender_id;
            } else {
                return ['error' => 'Admin SMS configuration not found'];
            }
        } elseif (!$api_key || !$sender_id) {
            return ['error' => 'API key or Sender ID missing'];
        }

        $otpApi = "https://login.smsforyou.biz/V2/http-api.php";
        $params = [
            'apikey' => $api_key,
            'senderid' => $sender_id,
            'number' => $number,
            'message' => $finalMessage,
            'format' => 'json',
            // 'shortlink' => 1,
            // 'originalurl' => $url ?? '',
            'template_id' => $templateID,
        ];

        $response = Http::get($otpApi, $params);

        return $response->successful()
            ? ['message' => 'SMS sent successfully', 'fullUrl' => $otpApi . '?' . http_build_query($params)]
            : ['error' => 'Failed to send SMS'];
    }

    //send whatsapp
    private function sendWhatsappDirect($data)
    {
        $eventName = $data->eventName;
        $number = preg_replace('/[^0-9]/', '', $data->number);
        $modifiedNumber = strlen($number) === 10 ? '91' . $number : $number;

        $template = $data->whatsappTemplateData;
        $apiKey = null;
        $mediaurl =  $data->eventThumbnail;
        // $mediaurl =  $data->eventThumbnail ?? "https://fronx.tasteofvadodara.in/uploads/thumbnail/680758de54594_10.jpg";
        // $mediaurl = $data->eventThumbnail ?? "https://fronx.tasteofvadodara.in/uploads/thumbnail/default.jpg";

        $admin = User::role('Admin', 'api')->with('whatsappConfig')->first();
        if ($admin && $admin->whatsappConfig) {
            $whatsappConfig = $admin->whatsappConfig[0];
            $apiKey = $whatsappConfig->api_key ?? null;
        } else {
            return ['error' => 'Admin WhatsApp configuration not found'];
        }

        if (!$apiKey) {
            return ['error' => 'API Key missing'];
        }

        $startDate = isset($data->eventDate) ? trim(explode(',', $data->eventDate)[0]) : '';
        $endDate = isset($data->eventDate) ? trim(explode(',', $data->eventDate)[1] ?? '') : '';
        $startTime = $data->eventstratTime ?? '';
        $endTime = $data->eventendTime ?? '';

        // Format dates
        $startDateFormatted = Carbon::parse($startDate)->format('F j');           // e.g., April 11
        $endDateFormatted = Carbon::parse($endDate)->format('F j Y');              // e.g., May 1 2025

        // Format times
        $startTimeFormatted = Carbon::parse($startTime)->format('g:i A');          // e.g., 11:00 AM
        $endTimeFormatted = Carbon::parse($endTime)->format('g:i A');              // e.g., 7:00 PM

        // Final string
        $eventDateTime = "{$startDateFormatted} to {$endDateFormatted} | {$startTimeFormatted} - {$endTimeFormatted}";

        // Final URL-encoded value
        $value = [
            $data->name,
            $modifiedNumber,
            $eventName,
            $data->qty,
            $data->ticketName,
            $data->eventLocation,
            $eventDateTime, // use combined date + time
        ];


        $whatsappApi = "https://waba.smsforyou.biz/api/send-messages";
        $params = [
            'apikey'     => $apiKey,
            'to'         => $modifiedNumber,
            'type'       => 'T',
            'tname'      => $template,
            'values'     => implode(',', $value),
            'media_url'  => $mediaurl
        ];

        // return $params;
        $response = Http::get($whatsappApi, $params);

        return $response->successful()
            ? ['message' => 'WhatsApp sent successfully', 'fullUrl' => $whatsappApi . '?' . http_build_query($params)]
            : ['error' => 'Failed to send WhatsApp', 'details' => $response->body()];
    }

    public function agentAmusementMaster(Request $request, $id)
    {
        try {
            $agentMasterBooking = new AmusementAgentMasterBooking();
            $bookingIds = $request->input('bookingIds');

            if (is_string($bookingIds)) {
                $bookingIds = json_decode($bookingIds, true);
                if (is_null($bookingIds)) {
                    $bookingIds = explode(',', trim($bookingIds, '[]'));
                }
            }

            if (!is_array($bookingIds)) {
                $bookingIds = [$bookingIds]; // Convert single ID into array
            }

            // Store as JSON in DB
            $agentMasterBooking->booking_id = json_encode($bookingIds);
            $agentMasterBooking->user_id = $request->user_id;
            $agentMasterBooking->agent_id = $request->agent_id;
            $agentMasterBooking->order_id = $this->generateHexadecimalCode(); // Generate an order ID
            $agentMasterBooking->amount = $request->amount;
            $agentMasterBooking->discount = $request->discount;
            $agentMasterBooking->payment_method = $request->payment_method;
            $agentMasterBooking->save();

            // Retrieve the created agent master booking
            $agentMasterBookingDetails = AmusementAgentMasterBooking::where('order_id', $agentMasterBooking->order_id)
                ->with('user')
                ->first();

            if ($agentMasterBookingDetails) {
                // Convert back to array
                $bookingIds = json_decode($agentMasterBookingDetails->booking_id, true);

                if (!is_array($bookingIds)) {
                    $bookingIds = [$bookingIds]; // Ensure it's an array
                }

                $agentMasterBookingDetails->bookings = AmusementAgentBooking::whereIn('id', $bookingIds)
                    ->with('ticket.event.user.smsConfig')
                    ->get();
            }

            return response()->json([
                'status' => true,
                'message' => 'Agent Master Ticket Created Successfully',
                'booking' => $agentMasterBookingDetails
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create agent master booking',
                'error' => $e->getMessage()
            ], 500);
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

    public function userFormNumberAmusement(Request $request, $id)
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

    public function agentAmusementBooking($id)
    {
        // Define a cache key based on the agent ID
        $cacheKey = "agent_bookings_{$id}";
        $user = Auth::user();
        $isOrganizer = $user->hasRole('Organizer');
        $isAdmin = $user->hasRole('Admin');
        if ($isAdmin) {
            $allBookings = AmusementAgentBooking::withTrashed()
                ->latest()
                ->with('ticket.event.user', 'user')
                ->get();
            $Masterbookings = AmusementAgentMasterBooking::withTrashed()
                ->latest()->get();
        } elseif ($isOrganizer) {
            $agentIds = $user->usersUnder()->pluck('id');
            $allBookings = AmusementAgentBooking::withTrashed()
                ->latest()
                ->whereIn('agent_id', $agentIds)
                ->with('ticket.event.user', 'user')
                ->get();
            $Masterbookings = AmusementAgentMasterBooking::withTrashed()->whereIn('agent_id', $agentIds)->latest()->get();
        } else {
            $allBookings = AmusementAgentBooking::withTrashed()
                ->latest()
                ->where('agent_id', $id)
                ->with('ticket.event.user', 'user')
                ->get();
            $Masterbookings = AmusementAgentMasterBooking::withTrashed()->orWhere('agent_id', $id)->latest()->get();
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
        //    return response()->json($allbookings);
        $bookings = $data['bookings'];
        $amount = $data['amount'];
        $discount = $data['discount'];
        if ($bookings->isNotEmpty()) {
            //    return response()->json(['status' => true, 'bookings' => $bookings, 'amount' => $amount, 'discount' => $discount], 200);
            return response()->json(['status' => true, 'bookings' => $bookings, 'amount' => $amount, 'discount' => $discount, 'allbookings' => $allbookings], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'No Bookings Found'], 200);
        }
    }
}
