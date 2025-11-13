<?php

namespace App\Http\Controllers;

use App\Models\AmusementBooking;
use App\Models\AmusementMasterBooking;
use App\Models\AmusementPendingBooking;
use App\Models\AmusementPendingMasterBooking;
use App\Models\Booking;
use App\Models\MasterBooking;
use App\Models\PenddingBooking;
use App\Models\PenddingBookingsMaster;
use App\Models\PromoCode;
use App\Models\Razorpay;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Razorpay\Api\Api;
use Illuminate\Support\Str;

class RazorPayController extends Controller
{
    protected $api;

    public function __construct() {}

    private function generateEncryptedSessionId()
    {
        // Generate a random session ID
        $originalSessionId = \Str::random(32);
        // Encrypt it
        $encryptedSessionId = encrypt($originalSessionId);

        return [
            'original' => $originalSessionId,
            'encrypted' => $encryptedSessionId
        ];
    }

    public function initiatePayment(Request $request)
    {
        try {
            // Get API credentials based on organizer_id or fallback to Admin credentials
            $config = Razorpay::where('user_id', $request->organizer_id)->first();
            $adminConfig = Razorpay::where('user_id', User::role('Admin')->value('id'))->first();
    
            $apiKey = $config->razorpay_key ?? $adminConfig->razorpay_key;
            $apiSecret = $config->razorpay_secret ?? $adminConfig->razorpay_secret;
    
            // Generate transaction ID
            $txnid = random_int(100000000000, 999999999999);
    
            // Handle zero amount bookings
            if ($request->amount == "0" && optional(json_decode($request->requestData))->tickets->quantity > 0) {
                return $this->handleZeroAmountBooking($request, null, $txnid);
            }
    
            $categoryData = $request->category;
            $session = $this->generateEncryptedSessionId()['original'];
            $sessionId = $this->generateEncryptedSessionId()['encrypted'];
    
            $gateway = 'razorpay';
            $request->merge(['gateway' => $gateway]);
    return response()->json(['status' => true, 'message' => 'Razorpay gateway selected', 'session_id' => $sessionId, 'category' => $categoryData]);
            // Initialize Razorpay API
            $api = new Api($apiKey, $apiSecret);
            // Convert amount to paisa (Razorpay uses paisa as the smallest currency unit)
            $amountInPaisa = $request->amount * 100;
    
            // Prepare order data
            $orderData = [
                'receipt' => 'receipt_' . $txnid,
                'amount' => $amountInPaisa, 
                'currency' => 'INR',
                'payment_capture' => 1, // Auto-capture
                'notes'           => [
                    'name'      => $request->firstname,
                    'email'     => $request->email,
                    'phone'     => $request->phone,
                    'event_id'  => $request->event_id,
                    'category'  => $categoryData,
                    'session_id' => $session
                ]
            ];

            $razorpayOrder = $api->order->create($orderData);

            $razorpayOrderId = $razorpayOrder['id'];
            // Store booking information
            $bookingMethod = ($request->category === 'Amusement') ? 'storeEmusment' : 'store';
            $bookings = $this->$bookingMethod($request, $session, $txnid);
    
            if (!empty($bookings->original['status']) && $bookings->original['status'] == true) {
                // Create response for frontend integration
                $responseData = [
                    //'order_id'    => $razorpayOrderId,
                    'key_id'      => $apiKey,
                    'amount'      => round($amountInPaisa),
                    'name'        => config('app.name', 'Your Application'),
                    'description' => $request->productinfo ?? 'Ticket Booking',
                    'prefill'     => [
                        'name'    => $request->firstname,
                        'email'   => $request->email,
                        'contact' => $request->phone,
                    ],
                    'notes'       => [
                        'txnid'     => $txnid,
                        'event_id'  => $request->event_id,
                        'session_id' => $session,
                    ],
                    'theme'       => [
                        'color'    => '#528FF0'
                    ],
                    'callback_url' => url('/api/payment-response/' . $gateway . '/' . $request->event_id . '/' . $sessionId . '?category=' . urlencode($categoryData)),
                    'webhook'      => url('/api/payment-webhook/razorpay/vod?sessionId=' . $session . '&category=' . urlencode($categoryData)),
                ];
                
                return response()->json([
                    'result' => ['success' => true, 'razorpay_data' => $responseData], 
                    'txnid' => $txnid, 
                    'order_id' => $razorpayOrderId
                ]);
            } else {
                return response()->json(['status' => false, 'message' => 'Payment Failed'], 400);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => false, 'message' => 'Configuration not found'], 404);
        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
    

    
    private function handleZeroAmountBooking($request, $session, $txnid)
    {
        $requestData = json_decode($request->requestData);
        $bookingIds = [];
        $bookings = []; // Store all bookings

        if (!$requestData) {
            return response()->json(['status' => false, 'message' => 'Invalid JSON data'], 400);
        }

        for ($i = 0; $i < $requestData->tickets->quantity; $i++) {
            if ($request->category === 'Amusement') {

                // Create amusement booking
                $booking = $this->amusementBookingDataZero($requestData, $request, $session, $txnid, $i);
            } else {
                // Create regular booking
                $booking = $this->bookingDataZero($requestData, $request, $session, $txnid);
            }

            if (!$booking) {
                return response()->json(['status' => false, 'message' => 'Booking failed'], 400);
            }

            $bookings[] = $booking; // Store in array
            $bookingIds[] = $booking->id;
        }

        $countOfBookings = count($bookingIds);

        if (intval($countOfBookings) > 1) {
            $data = ($request->category === 'Amusement')
                ? $this->updateAmusementMasterBookingZero($bookings[0], $bookingIds)
                : $this->updateMasterBookingZero($bookings[0], $bookingIds);

            return response()->json([
                'status' => true,
                'bookings' => $data ?? $bookings,
                'is_master' => isset($data),
                'message' => 'Bookings created successfully'
            ], 200);
        }

        return response()->json([
            'status' => true,
            'bookings' => $bookings,
            'is_master' => false,
            'message' => 'Bookings created successfully'
        ], 200);
    }

    private function storeEmusment($request, $session, $txnid)
    {
        try {
            $requestData = json_decode($request->requestData);

            $qty = $requestData->tickets->quantity;
            $bookings = [];
            $masterBookingData = [];
            $firstIteration = true;
            $penddingBookingsMaster = null;

            if ($qty > 0) {
                for ($i = 0; $i < $qty; $i++) {
                    $booking = new AmusementPendingBooking();
                    $booking->ticket_id = $requestData->tickets->id;
                    $booking->user_id = $requestData->user_id;
                    $booking->email = $requestData->email;
                    $booking->name = $requestData->name;
                    $booking->number = $requestData->number;
                    $booking->type = $requestData->type;
                    $booking->payment_method = $requestData->payment_method;
                    $booking->gateway = $request->gateway;
                    // $ticket = Ticket::findOrFail($requestData->tickets->id);
                    // $event = $ticket->event; // Assuming a `ticket` belongs to an `event`

                    // if ($event->rfid_required == 1) {
                    //     $booking->token = $this->generateHexadecimalCode();
                    // } else {
                    //     $booking->token = $this->generateRandomCode();
                    // }

                    // $booking->token = $this->generateRandomCode();
                    $booking->token = $this->generateHexadecimalCode();
                    $booking->session_id = $session;
                    $booking->promocode_id = $request->promo_code;
                    $booking->txnid = $txnid;
                    $booking->status = 0;
                    $booking->payment_status = 0;
                    $booking->attendee_id = $request->attendees[$i]['id'] ?? null;
                    $booking->total_tax = $request->total_tax;
                    $booking->booking_date = $request->booking_date;


                    if ($firstIteration) {
                        $booking->amount = $request->amount ?? 0;
                        $booking->discount = $request->discount;
                        $booking->base_amount = $request->base_amount;
                        $booking->convenience_fee = $request->convenience_fee;
                        $firstIteration = false;
                    }

                    $booking->save();
                    $booking->load(['user', 'ticket.event.user.smsConfig']);
                    $bookings[] = $booking;

                    $masterBookingData[] = $booking->id;
                }

                try {
                    if (count($bookings) > 1) {
                        $penddingBookingsMaster = new AmusementPendingMasterBooking();

                        // $penddingBookingsMaster->booking_id = $masterBookingData;
                        $penddingBookingsMaster->booking_id = is_array($masterBookingData) ? json_encode($masterBookingData) : json_encode([$masterBookingData]);
                        $penddingBookingsMaster->session_id = $session;  // Ensure booking_id is cast as an array
                        $penddingBookingsMaster->user_id = $requestData->user_id;
                        $penddingBookingsMaster->amount = $request->amount;
                        // $penddingBookingsMaster->order_id = $this->generateRandomCode();
                        $penddingBookingsMaster->order_id = $this->generateHexadecimalCode();
                        $penddingBookingsMaster->discount = $request->discount;
                        $penddingBookingsMaster->payment_method = $request->payment_method;
                        $penddingBookingsMaster->gateway = $request->gateway;
                        // Save the MasterBooking record
                        $penddingBookingsMaster->save();
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'error' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile()
                    ], 500);
                }
            }
            return response()->json(['status' => true, 'message' => 'Tickets Booked Successfully', 'bookings' => $bookings,  'PenddingBookingsMaster' => $penddingBookingsMaster], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to book tickets', 'error' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    private function store($request, $session, $txnid)
    {
        try {
            $requestData = json_decode($request->requestData);
            $qty = $requestData->tickets->quantity;
            $bookings = [];
            $masterBookingData = [];
            $firstIteration = true;
            $penddingBookingsMaster = null;

            if ($qty > 0) {
                for ($i = 0; $i < $qty; $i++) {
                    $booking = new PenddingBooking();
                    $booking->ticket_id = $requestData->tickets->id;
                    $booking->user_id = $requestData->user_id;
                    $booking->email = $requestData->email;
                    $booking->name = $requestData->name;
                    $booking->number = $requestData->number;
                    $booking->type = $requestData->type;
                    $booking->payment_method = $requestData->payment_method;
                    $booking->gateway = $request->gateway;

                    // $booking->token = $this->generateRandomCode();
                    $booking->token = $this->generateHexadecimalCode();
                    $booking->session_id = $session;
                    $booking->promocode_id = $request->promo_code;
                    $booking->txnid = $txnid;
                    $booking->status = 0;
                    $booking->payment_status = 0;
                    $booking->attendee_id = $request->attendees[$i]['id'] ?? null;
                    $booking->total_tax = $request->total_tax;


                    if ($firstIteration) {
                        $booking->amount = $request->amount > 0 ? $request->amount : 0;
                        // $booking->amount = $request->amount;
                        $booking->discount = $request->discount;
                        $booking->base_amount = $request->base_amount;
                        $booking->convenience_fee = $request->convenience_fee;
                        $firstIteration = false;
                    }


                    $booking->save();
                    $booking->load(['user', 'ticket.event.user.smsConfig']);
                    $bookings[] = $booking;

                    $masterBookingData[] = $booking->id;
                }

                if (count($bookings) > 1) {
                    $penddingBookingsMaster = new PenddingBookingsMaster();

                    $penddingBookingsMaster->booking_id = $masterBookingData;
                    $penddingBookingsMaster->session_id = $session;
                    $penddingBookingsMaster->user_id = $requestData->user_id;
                    $penddingBookingsMaster->amount = $request->amount;
                    $penddingBookingsMaster->gateway = $request->gateway;

                    // $penddingBookingsMaster->order_id = $this->generateRandomCode();
                    $penddingBookingsMaster->order_id = $this->generateHexadecimalCode();
                    $penddingBookingsMaster->discount = $request->discount;
                    $penddingBookingsMaster->payment_method = $request->payment_method;

                    // Save the MasterBooking record
                    $penddingBookingsMaster->save();

                    // You can check the response from master booking if needed
                }
            }

            return response()->json(['status' => true, 'message' => 'Tickets Booked Successfully', 'bookings' => $bookings,  'PenddingBookingsMaster' => $penddingBookingsMaster], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to book tickets', 'error' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    private function amusementBookingDataZero($data, $request, $session, $txnid, $i)
    {

        // return response()->json([$data]);
        $booking = new AmusementBooking();
        $booking->ticket_id = $request->ticket_id ?? $data->tickets->id;
        $booking->user_id = isset($data->user_id) ? $data->user_id : 0;
        $booking->session_id = $data->session_id ?? $session;
        $booking->promocode_id = $data->promocode_id ?? NULL;
        $booking->token = $data->token ?? $this->generateHexadecimalCode();
        $booking->amount = $data->amount ?? 0;
        $booking->email = $data->email;
        $booking->name = $data->name;
        $booking->number = $data->number;
        $booking->type = $data->type;
        $booking->dates = $data->dates ?? now();
        $booking->payment_method = $data->payment_method;
        $booking->discount = $data->discount ?? NULL;
        $booking->status = $data->status = 0;
        $booking->payment_status = 1;
        $booking->txnid = $data->txnid ?? $txnid;
        $booking->device = $data->device ?? NULL;
        $booking->base_amount = $data->base_amount;
        $booking->convenience_fee = $data->convenience_fee ?? NULL;
        $booking->attendee_id = $data->attendees[$i]['id'] ?? $request->attendees[$i]['id'] ?? NULL;
        $booking->total_tax = $data->total_tax ?? NULL;
        $booking->booking_date = $request->booking_date;
        $booking->save();
        $booking->load(['user', 'ticket.event', 'attendee']);
        // $booking->load(['user', 'attendee']);
        if (isset($booking->promocode_id)) {
            $promocode = PromoCode::where('code', $booking->promocode_id)->first();

            if (!$promocode) {
                return response()->json(['status' => false, 'message' => 'Invalid promocode'], 400);
            }

            if ($promocode->remaining_count === null) {
                $promocode->remaining_count = $promocode->usage_limit - 1;
            } elseif ($promocode->remaining_count > null) {
                $promocode->remaining_count--;
            } else {
                return response()->json(['status' => false, 'message' => 'Promocode usage limit reached'], 400);
            }

            // Assign promocode_id to booking
            if (isset($booking->promocode_id)) {
                $booking->promocode_id = $booking->promocode_id;
            }

            $promocode->save();
        }
        // return $data;

        return $booking;
    }

    private function bookingDataZero($data, $request, $session, $txnid)
    {

        $booking = new Booking();
        $booking->ticket_id = $request->ticket_id ?? $data->tickets->id;
        $booking->user_id = isset($data->user_id) ? $data->user_id : 0;
        $booking->session_id = $data->session_id ?? $session;
        $booking->promocode_id = $data->promocode_id ?? NULL;
        $booking->token = $data->token ?? $this->generateHexadecimalCode();
        $booking->amount = $data->amount > 0 ? $data->amount : 0;
        $booking->email = $data->email;
        $booking->name = $data->name;
        $booking->number = $data->number;
        $booking->type = $data->type;
        $booking->dates = $data->dates ?? now();
        $booking->payment_method = $data->payment_method;
        $booking->discount = $data->discount ?? NULL;
        $booking->status = $data->status = 0;
        $booking->payment_status = 1;
        $booking->txnid = $data->txnid ?? $txnid;
        $booking->device = $data->device ?? NULL;
        $booking->base_amount = $data->base_amount;
        $booking->convenience_fee = $data->convenience_fee ?? NULL;
        $booking->attendee_id = $data->attendee_id ?? $request->attendees[0]['id'] ?? NULL;
        $booking->total_tax = $data->total_tax ?? NULL;
        $booking->save();
        $booking->load(['user', 'ticket.event', 'attendee']);
        if (isset($booking->promocode_id)) {
            $promocode = Promocode::where('code', $booking->promocode_id)->first();

            if (!$promocode) {
                return response()->json(['status' => false, 'message' => 'Invalid promocode'], 400);
            }

            if ($promocode->remaining_count === null) {
                $promocode->remaining_count = $promocode->usage_limit - 1;
            } elseif ($promocode->remaining_count > null) {
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

    private function updateAmusementMasterBookingZero($booking, $ids)
    {


        $data = [
            'user_id' => $booking->user_id,
            'session_id' => $booking->session_id,
            // 'booking_id' => $ids,
            'booking_id' =>  is_array($ids) ? json_encode($ids) : json_encode([$ids]),
            'order_id' => $booking->order_id,
            'amount' => $booking->amount ?? 0,
            'discount' => $booking->discount,
            'payment_method' => $booking->payment_method,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Create MasterBooking record
        $master = AmusementMasterBooking::create($data);

        if (!$master) {
            return false;
        }
        $master->bookings = AmusementBooking::whereIn('id', $ids)->with(['user', 'attendee', 'ticket.event'])->get();

        return $master;
    }

    private function updateMasterBookingZero($booking, $ids)
    {

        $data = [
            'user_id' => $booking->user_id,
            'session_id' => $booking->session_id,
            'booking_id' => $ids,
            'order_id' => $booking->order_id,
            'amount' => $booking->amount ?? 0,
            'discount' => $booking->discount,
            'payment_method' => $booking->payment_method,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Create MasterBooking record
        $master = MasterBooking::create($data);

        // If creation fails, return false
        if (!$master) {
            return false;
        }
        // $master->bookings = MasterBooking::whereIn('id', $ids)->get();
        $master->bookings = Booking::whereIn('id', $ids)->with(['user', 'ticket.event', 'attendee'])->get();

        // Return true if all records are created successfully
        return $master;
    }

}
