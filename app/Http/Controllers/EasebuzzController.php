<?php

namespace App\Http\Controllers;


use App\Models\AmusementBooking;
use App\Models\AmusementMasterBooking;
use App\Models\AmusementPendingBooking;
use App\Models\AmusementPendingMasterBooking;
use App\Models\Booking;
use App\Models\EasebuzzConfig;
use App\Models\MasterBooking;
use App\Models\PaymentLog;
use App\Models\PenddingBooking;
use App\Models\PenddingBookingsMaster;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Ticket;
use App\Models\WhatsappApi;
use App\Services\SmsService;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Http;

class EasebuzzController extends Controller
{

    protected $smsService, $whatsappService;

    protected $config;
    protected $url;
    public function __construct(SmsService $smsService, WhatsappService $whatsappService)
    {
        // Retrieve configuration from the database
        $config = EasebuzzConfig::first();

        if (!$config) {
            throw new \Exception('Configuration not found');
        }
        $this->url = 'https://testpay.easebuzz.in/';

        $this->smsService = $smsService;
        $this->whatsappService = $whatsappService;
    }

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
        // return response()->json("hello");

        try {
            include_once(app_path('Services/easebuzz_helper.php'));

            $getSession = $this->generateEncryptedSessionId();
            $session = $getSession['original'];

            //  $adminConfig = EasebuzzConfig::where('user_id', User::role('Admin')->value('id'))->first();

            $config = EasebuzzConfig::where('user_id', $request->organizer_id)
                ->first();

            if (! $config) {
                $adminId = User::role('Admin')->value('id');
                $config = EasebuzzConfig::where('user_id', $adminId)->first();
            }

            // $config = EasebuzzConfig::where('user_id', $request->organizer_id)->firstOrFail();
            // $adminConfig = EasebuzzConfig::where('user_id', User::role('Admin')->value('id'))->first();


            $env = $config->env;
            $key = $config->merchant_key;
            $salt = $config->salt;



            $prod_url = $config->prod_url;
            $test_url = $config->test_url;
            $categoryData = $request->category;

            $headers = [
                "Accept: application/json",
                "Content-Type: application/x-www-form-urlencoded",
            ];

            function generateTxnId()
            {
                return random_int(100000000000, 999999999999);
            }

            $sesstionId = $getSession['encrypted'];
            $txnid = generateTxnId();

            // If amount is 0, directly mark booking as successful
            // Handle 0 amount bookings
            if ($request->amount == "0" && json_decode($request->requestData)->tickets->quantity > 0) {
                return $this->handleZeroAmountBooking($request, $session, $txnid);
            }

            $gateway = 'easebuzz';
            $request->merge(['gateway' => $gateway]);
            // return response()->json($gateway);
            $params = [
                "key" => $key,
                "txnid" => $txnid,
                "amount" => number_format($request->amount, 2, '.', ''),
                "productinfo" => $request->productinfo,
                "firstname" => $request->firstname,
                "email" => $request->email,
                "udf1" => "",
                "udf2" => "",
                "udf3" => "",
                "udf4" => "",
                "udf5" => "",
                "udf6" => "",
                "udf7" => "",
                "udf8" => "",
                "udf9" => "",
                "udf10" => "",
                'phone' => $request->phone,
                'furl' => url('/api/payment-response/' . $gateway . '/' . $request->event_id . '/' . $session . '?status=failure&category=' . $categoryData),
                'surl' => url('/api/payment-response/' . $gateway . '/' . $request->event_id . '/' . $session . '?status=success&category=' . $categoryData)
            ];

            // Initiate payment
            $paymentParams = initiate_payment($params, false, $key, $salt, $env);

            // Store booking information
            if ($request->category === 'Amusement') {
                $bookings = $this->storeEmusment($request, $session, $txnid);
            } else {
                $bookings = $this->store($request, $session, $txnid);
            }

            $url = ($env == 'test')
                ? ($config->test_url ?? $adminConfig->test_url ?? null)
                : ($config->prod_url ?? $adminConfig->prod_url ?? null);
            // Check booking status
            if ($bookings->original['status'] == true) {
                return response()->json(['result' => $paymentParams, 'txnid' => $txnid, 'url' => $url . $paymentParams['data']]);
            } else {
                return response()->json(['status' => false, 'message' => 'Payment Failed'], 400);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => false, 'message' => 'Configuration not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }


    public function handlePaymentResponse(Request $request, $gateway, $id, $session_id)
    {
        try {


            //$decryptedSessionId = decrypt($session_id);
            $status = $request->input('status');
            $category = $params['category'] ?? null;
            //return  redirect()->away('http://192.168.0.144:3000/events/' . $id . '/process?status=' . $status . '&session_id=' . $session_id . '&category=' . $category);
            //return redirect()->away('https://ssgarba.com/events/' . $id . '/process?status=' . $status . '&session_id=' . $session_id . '&category=' . $category);
            return  redirect()->away('https://getyourticket.in/events/' . $id . '/process?status=' . $status . '&session_id=' . $session_id . '&category=' . $category);
        } catch (\Exception $e) {
            Log::error('Payment Response Error', ['gateway' => $gateway, 'error' => $e->getMessage()]);
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function extractLastPathSegment($url)
    {
        try {
            $parsedUrl = parse_url($url);
            if (!$parsedUrl) {
                return null;
            }

            // Get path segments
            $path = $parsedUrl['path'] ?? '';
            $segments = explode('/', trim($path, '/'));
            $lastSegment = end($segments);

            // Get query parameters
            $query = [];
            if (isset($parsedUrl['query'])) {
                $queryString = html_entity_decode($parsedUrl['query']);
                parse_str($queryString, $query);
            }

            // Decrypt the session ID
            try {
                $decrypted = $lastSegment;
            } catch (\Exception $e) {
                return null;
            }

            // Return both session ID and category
            return [
                'session_id' => $decrypted,
                'category' => $query['category'] ?? null
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
    private function checkExistingBooking($sessionId, $paymentId)
    {
        // Check in regular bookings
        $existingBooking = Booking::where('session_id', $sessionId)
            ->where('payment_id', $paymentId)
            ->exists();

        if ($existingBooking) {
            return true;
        }
        // Check in amusement bookings
        $existingAmusementBooking = AmusementBooking::where('session_id', $sessionId)
            ->where('payment_id', $paymentId)
            ->exists();

        if ($existingAmusementBooking) {
            return true;
        }
        // return $existingAmusementMasterBooking;
    }
    // Add this private method to extract PhonePe webhook data
    private function extractPhonePeWebhookData($request)
    {
        try {
            $params = $request->all();

            // Check if it's a PhonePe webhook structure
            if (!isset($params['type']) || !isset($params['payload'])) {
                throw new \Exception('Invalid PhonePe webhook structure');
            }

            $payload = $params['payload'];

            // Extract basic information
            $merchantOrderId = $payload['merchantOrderId'] ?? null;
            $state = $payload['state'] ?? null;
            $amount = $payload['amount'] ?? null;

            // Determine status based on PhonePe state
            $status = 'unknown';
            switch (strtoupper($state)) {
                case 'COMPLETED':
                case 'SUCCESS':
                    $status = 'success';
                    break;
                case 'FAILED':
                case 'CANCELLED':
                case 'EXPIRED':
                    $status = 'failed';
                    break;
                case 'PENDING':
                    $status = 'pending';
                    break;
                default:
                    $status = 'unknown';
            }

            // Extract payment details
            $paymentDetails = $payload['paymentDetails'][0] ?? null;
            $transactionId = $paymentDetails['transactionId'] ?? $payload['orderId'] ?? null;
            $paymentMode = $paymentDetails['paymentMode'] ?? 'phonepe';

            // Extract UTR if available
            $utr = null;
            if (isset($paymentDetails['rail']['utr'])) {
                $utr = $paymentDetails['rail']['utr'];
            }

            // For PhonePe, we need to extract session ID from merchantOrderId
            // Assuming merchantOrderId format is like: TXN_1754388795_W47jk0
            // We need to map this to session_id somehow
            $sessionId = $this->extractSessionFromMerchantOrderId($merchantOrderId);

            // Determine category (you may need to adjust this based on your implementation)
            $category = $this->determineCategoryFromMerchantOrderId($merchantOrderId);

            return [
                'payment_id' => $transactionId,
                'session_id' => $sessionId,
                'category' => $category,
                'status' => $status,
                'amount' => $amount,
                'mode' => $paymentMode,
                'merchant_order_id' => $merchantOrderId,
                'order_id' => $payload['orderId'] ?? null,
                'utr' => $utr,
                'timestamp' => $paymentDetails['timestamp'] ?? time(),
                'raw_payload' => $params
            ];
        } catch (\Exception $e) {
            Log::error('PhonePe webhook data extraction failed: ' . $e->getMessage());
            throw $e;
        }
    }

    // Helper method to extract session ID from merchant order ID
    private function extractSessionFromMerchantOrderId($merchantOrderId)
    {
        // This depends on how you store the relationship between merchantOrderId and session_id
        // Option 1: If you store it in pending bookings
        $pendingBooking = PenddingBooking::where('txnid', $merchantOrderId)->first();
        if ($pendingBooking) {
            return $pendingBooking->session_id;
        }

        // Option 2: If you store it in amusement pending bookings
        $amusementPendingBooking = AmusementPendingBooking::where('txnid', $merchantOrderId)->first();
        if ($amusementPendingBooking) {
            return $amusementPendingBooking->session_id;
        }

        // Option 3: If you have a specific mapping table or pattern
        // You might need to create a mapping table or use a different approach

        Log::warning("Could not find session_id for merchant order ID: " . $merchantOrderId);
        return null;
    }

    // Add this private method to extract Razorpay webhook data
    // private function extractRazorpayWebhookData($request)
    // {
    //     try {
    //         $params = $request->all();

    //         // Check if it's a Razorpay webhook structure
    //         if (!isset($params['event']) || !isset($params['payload'])) {
    //             throw new \Exception('Invalid Razorpay webhook structure');
    //         }

    //         $event = $params['event'];
    //         $payload = $params['payload'];

    //         // Handle different Razorpay events
    //         $paymentId = null;
    //         $orderId = null;
    //         $amount = null;
    //         $status = null;
    //         $method = 'razorpay';
    //         $sessionId = null;
    //         $category = null;

    //         if ($event === 'payment_link.paid') {
    //             // Extract from payment_link.paid webhook structure
    //             $paymentLink = $payload['payment_link']['entity'] ?? null;
    //             $payment = $payload['payment']['entity'] ?? null;
    //             $order = $payload['order']['entity'] ?? null;

    //             if (!$paymentLink || !$payment) {
    //                 throw new \Exception('Missing payment_link or payment entity in webhook payload');
    //             }

    //             $paymentId = $payment['id'] ?? null;
    //             $orderId = $payment['order_id'] ?? ($order['id'] ?? null);
    //             $amount = ($payment['amount'] ?? 0) / 100; // Convert paise to rupees
    //             $status = $payment['status'] ?? null;
    //             $method = $payment['method'] ?? 'razorpay';

    //             // Extract session ID from callback_url
    //             $callbackUrl = $paymentLink['callback_url'] ?? null;
    //             if ($callbackUrl) {
    //                 $sessionId = $this->extractSessionFromCallbackUrl($callbackUrl);
    //                 $category = $this->extractCategoryFromCallbackUrl($callbackUrl);
    //             }
    //         } else {
    //             Log::info("Razorpay webhook event '{$event}' ignored - only processing payment.captured and payment_link.paid");
    //             throw new \Exception("Event '{$event}' not supported for booking processing");
    //         }

    //         // Determine booking status
    //         $bookingStatus = 'unknown';
    //         if ($status === 'captured') {
    //             $bookingStatus = 'success';
    //         } else {
    //             $bookingStatus = 'failed';
    //         }

    //         return [
    //             'payment_id' => $paymentId,
    //             'session_id' => $sessionId,
    //             'category' => $category,
    //             'status' => $bookingStatus,
    //             'amount' => $amount,
    //             'method' => $method,
    //             'order_id' => $orderId,
    //             'event' => $event,
    //             'raw_payload' => $params
    //         ];
    //     } catch (\Exception $e) {
    //         Log::error('Razorpay webhook data extraction failed: ' . $e->getMessage());
    //         throw $e;
    //     }
    // }
    private function extractRazorpayWebhookData($request)
    {
        try {
            $params = $request->all();

            if (!isset($params['event']) || !isset($params['payload'])) {
                throw new \Exception('Invalid Razorpay webhook structure');
            }

            $event = $params['event'];
            $payload = $params['payload'];

            $paymentId = null;
            $orderId = null;
            $amount = null;
            $status = null;
            $method = 'razorpay';
            $sessionId = null;
            $category = null;

            if ($event === 'payment.captured') {
                // ✅ Handle normal checkout flow
                $payment = $payload['payment']['entity'] ?? null;
                if (!$payment) {
                    throw new \Exception('Missing payment entity in webhook payload');
                }

                $paymentId = $payment['id'] ?? null;
                $orderId = $payment['order_id'] ?? null;
                $amount = ($payment['amount'] ?? 0) / 100;
                $status = $payment['status'] ?? null;
                $method = $payment['method'] ?? 'razorpay';

                // session & category order notes માંથી લાવી શકાય
                $notes = $payment['notes'] ?? [];
                $sessionId = $notes['session_id'] ?? null;
                $category  = $notes['category'] ?? null;
            } elseif ($event === 'payment_link.paid') {
                // ✅ Handle payment link flow (if used)
                $paymentLink = $payload['payment_link']['entity'] ?? null;
                $payment = $payload['payment']['entity'] ?? null;

                if (!$paymentLink || !$payment) {
                    throw new \Exception('Missing payment_link or payment entity');
                }

                $paymentId = $payment['id'] ?? null;
                $orderId = $payment['order_id'] ?? null;
                $amount = ($payment['amount'] ?? 0) / 100;
                $status = $payment['status'] ?? null;
                $method = $payment['method'] ?? 'razorpay';

                $callbackUrl = $paymentLink['callback_url'] ?? null;
                if ($callbackUrl) {
                    $sessionId = $this->extractSessionFromCallbackUrl($callbackUrl);
                    $category = $this->extractCategoryFromCallbackUrl($callbackUrl);
                }
            } else {
                Log::info("Razorpay webhook event '{$event}' ignored");
                throw new \Exception("Event '{$event}' not supported");
            }

            // ✅ Booking status
            $bookingStatus = ($status === 'captured') ? 'success' : 'failed';

            return [
                'payment_id' => $paymentId,
                'session_id' => $sessionId,
                'category'   => $category,
                'status'     => $bookingStatus,
                'amount'     => $amount,
                'method'     => $method,
                'order_id'   => $orderId,
                'event'      => $event,
                'raw_payload' => $params
            ];
        } catch (\Exception $e) {
            Log::error('Razorpay webhook data extraction failed: ' . $e->getMessage());
            throw $e;
        }
    }
    // Helper method to extract session ID from Razorpay callback URL
    private function extractSessionFromCallbackUrl($callbackUrl)
    {
        try {
            // Parse the callback URL to extract session ID
            // Format: https://gyt.tieconvadodara.com/api/payment-response/razorpay/AA00002/eob7OmzWBCtq5Vb3hzqyfQPezysx3Bsq?status=success&category=Business Confrence

            $parsedUrl = parse_url($callbackUrl);
            if (!$parsedUrl || !isset($parsedUrl['path'])) {
                throw new \Exception('Invalid callback URL format');
            }

            // Extract path segments
            $pathSegments = explode('/', trim($parsedUrl['path'], '/'));

            // Find the session ID (should be the last segment before query parameters)
            // Expected path: /api/payment-response/razorpay/{event_id}/{session_id}
            if (count($pathSegments) >= 4) {
                $sessionId = end($pathSegments); // Get the last segment

                if (!empty($sessionId)) {
                    return $sessionId;
                }
            }

            Log::warning("Could not extract session_id from callback URL: " . $callbackUrl);
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to extract session ID from callback URL: ' . $e->getMessage());
            return null;
        }
    }

    // Helper method to extract category from Razorpay callback URL
    private function extractCategoryFromCallbackUrl($callbackUrl)
    {
        try {
            // Parse query parameters from callback URL
            $parsedUrl = parse_url($callbackUrl);

            if (!$parsedUrl || !isset($parsedUrl['query'])) {
                return 'Event'; // Default fallback
            }

            // Parse query string
            parse_str($parsedUrl['query'], $queryParams);

            // Extract category from query parameters
            $category = $queryParams['category'] ?? 'Event';

            // URL decode the category
            $category = urldecode($category);

            // Map category names if needed
            if (stripos($category, 'amusement') !== false) {
                return 'Amusement';
            } elseif (stripos($category, 'business') !== false || stripos($category, 'conference') !== false) {
                return 'Event';
            }

            return $category ?: 'Event';
        } catch (\Exception $e) {
            Log::error('Failed to extract category from callback URL: ' . $e->getMessage());
            return 'Event';
        }
    }





    // Helper method to determine category from merchant order ID
    private function determineCategoryFromMerchantOrderId($merchantOrderId)
    {
        // Check in pending bookings first
        $pendingBooking = PenddingBooking::where('txnid', $merchantOrderId)->first();
        if ($pendingBooking) {
            return 'Event'; // or whatever category you use for regular events
        }

        // Check in amusement pending bookings
        $amusementPendingBooking = AmusementPendingBooking::where('txnid', $merchantOrderId)->first();
        if ($amusementPendingBooking) {
            return 'Amusement';
        }

        // Default fallback
        return 'Event';
    }

    // Update the main handleWebhook method
    public function handleWebhook(Request $request, $gateway)
    {
        // $response = Http::post("https://dark.getyourticket.in/api/dark/payment-webhook/{$gateway}/vod", $request->all());
        //$response = Http::post("https://dark.getyourticket.in/api/dark/payment-webhook/{$gateway}/vod", $request->all());

        //     return response()->json([
        //         'status' => $response->successful(),
        //         'message' => 'Webhook forwarded',
        //         'response' => $response->json(),
        //     ]);

        // Log::info("[$gateway] Webhook received:", $request->all());

        try {
            $params = $request->all();
            $sessionId = null;
            $category = null;
            $status = null;
            $paymentId = null;

            // Handle different gateway webhook formats
            if ($gateway === 'phonepe') {
                $webhookData = $this->extractPhonePeWebhookData($request);

                $sessionId = $webhookData['session_id'];
                $category = $webhookData['category'];
                $status = $webhookData['status'];
                $paymentId = $webhookData['payment_id'];

                // Format params for consistent logging
                $params = array_merge($params, [
                    'status' => $status,
                    'amount' => $webhookData['amount'],
                    'mode' => $webhookData['mode'],
                    'merchant_order_id' => $webhookData['merchant_order_id'],
                    'order_id' => $webhookData['order_id'],
                    'utr' => $webhookData['utr'],
                    'category' => $category
                ]);
            } elseif ($gateway === 'razorpay') {
                $webhookData = $this->extractRazorpayWebhookData($request);

                $sessionId = $webhookData['session_id'];
                $category = $webhookData['category'];
                $status = $webhookData['status'];
                $paymentId = $webhookData['payment_id'];

                // Format params for consistent logging
                $params = array_merge($params, [
                    'status' => $status,
                    'amount' => $webhookData['amount'],
                    'order_id' => $webhookData['order_id'],
                    'payment_id' => $webhookData['payment_id'],
                    'method' => $webhookData['method'],
                    'event' => $webhookData['event'],
                    'category' => $category
                ]);
            } elseif ($gateway === 'easebuzz') {
                $statusRaw = $request->input('status');
                $status = strtolower(trim($statusRaw));
                if (!$status) {
                    Log::warning("[$gateway] Missing 'status' in webhook.");
                    return response()->json(['error' => 'Missing status field'], 400);
                }

                $paymentId = $params['easepayid'] ?? null;

                // Extract session ID from surl for Easebuzz
                if (!isset($params['surl'])) {
                    Log::warning("[$gateway] Missing 'surl' in webhook.");
                    return response()->json(['error' => 'Missing surl'], 400);
                }
                $urlData = $this->extractLastPathSegment($params['surl']);
            } elseif ($gateway === 'instamojo') {
                $statusRaw = $request->input('status');
                $status = strtolower(trim($statusRaw));
                $paymentId = $params['payment_id'] ?? null;

                // Extract session ID and category from URL parameters
                if (!isset($params['sessionId']) || !isset($params['category'])) {
                    Log::warning("[$gateway] Missing session_id or category in webhook URL.");
                    return response()->json(['error' => 'Missing required parameters'], 400);
                }

                try {
                    $urlData = [
                        'session_id' => $params['sessionId'],
                        'category' => urldecode($params['category'])
                    ];
                } catch (\Exception $e) {
                    Log::error("[$gateway] Parameter processing failed: " . $e->getMessage());
                    return response()->json(['error' => 'Invalid parameters'], 400);
                }
            } elseif ($gateway === 'cashfree') {
                $webhookData = $this->extractCashfreeWebhookData(new Request($params));
                $sessionId   = $webhookData['session_id'];
                $category    = $webhookData['category'];
                $status      = $webhookData['status'];
                $paymentId   = $webhookData['payment_id'];

                $params = array_merge($params, [
                    'status'     => $status,
                    'amount'     => $webhookData['amount'],
                    'order_id'   => $webhookData['order_id'],
                    'payment_id' => $webhookData['payment_id'],
                    'mode'       => $webhookData['mode'],
                    'category'   => $category,
                    'raw_payload' => $webhookData['raw_payload'] ?? null
                ]);
            }
            // Normalize status for non-PhonePe gateways
            if ($gateway !== 'phonepe' || $gateway !== 'razorpay' || $gateway !== 'cashfree') {
                //if ($gateway !== 'phonepe' && $gateway !== 'razorpay') {
                $successStatuses = ['success', 'credit', 'completed', 'paid'];
                $failureStatuses = ['failed', 'failure', 'error', 'cancelled', 'declined'];

                if (in_array($status, $successStatuses)) {
                    $status = 'success';
                } elseif (in_array($status, $failureStatuses)) {
                    $status = 'failed';
                } else {
                    Log::warning("[$gateway] Unknown status value: $status");
                    return response()->json(['error' => 'Unknown status value'], 400);
                }

                // Extract session and category for non-PhonePe and non-Razorpay gateways
                if (isset($urlData)) {
                    $sessionId = $urlData['session_id'];
                    $category = $urlData['category'];
                }
            }            // Validate required data
            if (!$paymentId) {
                Log::warning("[$gateway] Missing payment ID.");
                return response()->json(['error' => 'Missing payment ID'], 400);
            }

            if (!$sessionId || !$category) {
                Log::warning("[$gateway] Invalid or incomplete data format.");
                return response()->json(['error' => 'Invalid data format'], 400);
            }

            // Set default mode for non-PhonePe and non-Razorpay gateways
            if ($gateway !== 'phonepe' && $gateway !== 'razorpay') {
                $params['mode'] = $params['mode'] ?? 'NA';
            }

            // Check for duplicate webhook
            if ($this->checkExistingBooking($sessionId, $paymentId)) {
                Log::warning("[$gateway] Duplicate webhook received for session_id: $sessionId and payment_id: $paymentId");
                return response()->json(['message' => 'Webhook already processed'], 200);
            }

            // Store payment log
            $this->storePaymentLog($gateway, $sessionId, $params);

            // Trigger appropriate handler
            if ($category === 'Amusement') {
                $this->transferAmusementBooking($sessionId, $status, $paymentId);
            } else {
                Log::info("[$gateway] Processing booking transfer - Session: $sessionId, Status: $status, Payment: $paymentId");
                return $this->transferEventBooking($sessionId, $status, $paymentId);
            }

            return response()->json(['message' => 'Webhook processed successfully'], 200);
        } catch (\Exception $e) {
            Log::error("[$gateway] Webhook processing failed: " . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    private function extractCashfreeWebhookData($request)
    {
        try {
            $data = $request->all();

            $orderId     = $data['data']['order']['order_id'] ?? null;
            $paymentId   = $data['data']['payment']['cf_payment_id'] ?? null;
            $statusRaw   = $data['data']['payment']['payment_status'] ?? null;
            $amount      = $data['data']['payment']['payment_amount'] ?? null;
            $paymentMode = $data['data']['payment']['payment_group'] ?? null;

            // ✅ Normalize status
            $status = match (strtoupper($statusRaw)) {
                'SUCCESS', 'PAID' => 'success',
                'FAILED', 'CANCELLED' => 'failed',
                default => 'pending'
            };

            // ✅ Extract session_id from order_id (if you stored it like order_{sessionId})
            $sessionId = $orderId;
            // }
            $pending = PenddingBooking::with('ticket.event.Category')
                ->where('session_id', $sessionId)
                ->first();

            $category = optional($pending->ticket?->event?->Category)->title ?? 'Event';

            return [
                'payment_id'  => $paymentId,
                'session_id'  => $sessionId,
                'category'    => $category,
                'status'      => $status,
                'amount'      => $amount,
                'mode'        => $paymentMode,
                'order_id'    => $orderId,
                'raw_payload' => $data
            ];
        } catch (\Exception $e) {
            Log::error('Cashfree webhook data extraction failed: ' . $e->getMessage());
            throw $e;
        }
    }
    // Update the storePaymentLog method to handle PhonePe
    private function storePaymentLog($gateway, $sessionId, $params)
    {
        if ($gateway == 'phonepe') {
            $paymentData = [
                'session_id' => $sessionId ?? null,
                'payment_id' => $params['payment_id'] ?? $params['order_id'] ?? null,
                'amount' => $params['amount'] ?? null,
                'status' => $params['status'] ?? null,
                'txnid' => $params['merchant_order_id'] ?? null,
                'mode' => $params['mode'] ?? 'phonepe',
                'addedon' => isset($params['timestamp']) ? date('Y-m-d H:i:s', $params['timestamp'] / 1000) : now()->toDateTimeString(),
                'params' => $params,
                'category' => $params['category'] ?? null,
            ];
        } elseif ($gateway == 'easebuzz') {
            $paymentData = [
                'session_id' => $sessionId ?? null,
                'payment_id' => $params['easepayid'] ?? null,
                'amount' => $params['amount'] ?? null,
                'status' => $params['status'] ?? null,
                'txnid' => $params['txnid'] ?? null,
                'mode' => $params['mode'] ?? null,
                'addedon' => $params['addedon'] ?? null,
                'params' => $params,
                'category' => $params['category'] ?? null,
            ];
        } elseif ($gateway == 'instamojo') {
            $paymentData = [
                'session_id' => $sessionId,
                'payment_id' => $params['payment_id'] ?? null,
                'txnid' => $params['payment_request_id'] ?? null,
                'amount' => $params['amount'] ?? null,
                'status' => $params['status'] ?? null,
                'params' => $params,
                'category' => $params['category'] ?? null,
            ];
        } elseif ($gateway == 'razorpay') {
            $paymentData = [
                'session_id' => $sessionId,
                'payment_id' => $params['payment_id'] ?? null,
                'txnid' => $params['order_id'] ?? null,
                'amount' => $params['amount'] ?? null,
                'status' => $params['status'] ?? null,
                'mode' => $params['method'] ?? 'razorpay',
                'addedon' => now()->toDateTimeString(),
                'params' => $params,
                'category' => $params['category'] ?? null,
            ];
        } elseif ($gateway == 'cashfree') {
            $paymentData = [
                'session_id' => $sessionId ?? null,
                'payment_id' => $params['payment_id'] ?? $params['cf_payment_id'] ?? null,
                'txnid' => $params['cf_order_id'] ?? $params['order_id'] ?? null,
                'amount' => $params['amount'] ?? null,
                'status' => $params['status'] ?? null,
                'mode' => $params['payment_mode'] ?? 'cashfree',
                'addedon' => $params['payment_time'] ?? now()->toDateTimeString(),
                'params' => $params,
                'category' => $params['category'] ?? null,
            ];
        } else {
            throw new \Exception("Unsupported payment gateway: " . $gateway);
        }

        // Log payment data
        Log::info('Payment Log: ' . ucfirst($gateway), ['data' => $paymentData]);

        // Update pending bookings
        if ($sessionId || isset($params['easepayid']) || isset($params['payment_id'])) {
            $updateData = [
                'payment_id' => $paymentData['payment_id'],
                'payment_status' => $paymentData['status'],
                'payment_method' => $paymentData['mode'] ?? $gateway
            ];
            PenddingBooking::where('session_id', $sessionId)->update($updateData);
        }

        // Insert or update PaymentLog
        $existing = PaymentLog::where('txnid', $paymentData['txnid'])->first();
        if ($existing) {
            $existing->update($paymentData);
            $result = $existing->fresh();
        } else {
            $result = PaymentLog::create($paymentData);
        }

        return [$existing, $result];
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
            $attendees = $request->attendees ?? [];
            if (isset($attendees[$i]['id'])) {
                $requestData->attendee_id = $attendees[$i]['id'];
            }
            if ($request->category === 'Amusement') {

                // Create amusement booking
                $booking = $this->amusementBookingDataZero($requestData, $request, $session, $txnid, $i);
            } else {
                // Create regular booking
                // $booking = $this->bookingDataZero($requestData, $request, $session, $txnid);
                $booking = $this->bookingDataZero($requestData, $request, $session, $txnid, [
                    'is_master_booking' => ($requestData->tickets->quantity > 1)
                ]);
            }

            if (!$booking) {
                return response()->json(['status' => false, 'message' => 'Booking failed'], 400);
            }

            $bookings[] = $booking; // Store in array
            $bookingIds[] = $booking->id;
        }

        $countOfBookings = count($bookingIds);
        $isMasterBooking = false;

        if (intval($countOfBookings) > 1) {
            $isMasterBooking = true;
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

    private function bookingDataZero($data, $request, $session, $txnid, $extra = [])
    {

        $booking = new Booking();
        $booking->ticket_id = $request->ticket_id ?? $data->tickets->id;
        $booking->batch_id = Ticket::where('id', $data->tickets->id)->value('batch_id');
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


        $orderId = $booking->token ?? '';
        $shortLink = $orderId;
        $shortLinksms = "getyourticket.in/t/{$orderId}";

        $whatsappTemplate = WhatsappApi::where('title', 'Online Booking')->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $dates = explode(',', $booking->ticket->event->date_range);
        $formattedDates = [];
        foreach ($dates as $date) {
            $formattedDates[] = \Carbon\Carbon::parse($date)->format('d-m-Y');
        }
        $dateRangeFormatted = implode(' | ', $formattedDates);

        $eventDateTime = $dateRangeFormatted . ' | ' . $booking->ticket->event->start_time . ' - ' . $booking->ticket->event->end_time;
        //$eventDateTime = str_replace(',', ' |', $booking->ticket->event->date_range) . ' | ' . $booking->ticket->event->start_time . ' - ' . $booking->ticket->event->end_time;
        $mediaurl = $booking->ticket->event->thumbnail;
        $data = (object) [
            'name' => $booking->name,
            'number' => $booking->number,
            'templateName' => 'Online Booking Template',
            'whatsappTemplateData' => $whatsappTemplateName,
            'mediaurl' => $mediaurl,
            'shortLink' => $shortLink,
            'insta_whts_url' => $booking->ticket->event->insta_whts_url ?? 'helloinsta',
            'values' => [
                $booking->name,
                $booking->number,
                $booking->ticket->event->name,
                1,
                $booking->ticket->name,
                $booking->ticket->event->address,
                $eventDateTime,
                $booking->ticket->event->whts_note ?? 'hello',
            ],

            'replacements' => [
                ':C_Name' => $booking->name,
                ':T_QTY' => 1,
                ':Ticket_Name' => $booking->ticket->name,
                ':Event_Name' => $booking->ticket->event->name,
                ':Event_DateTime' => $eventDateTime,
                ':S_Link' => $shortLinksms,
            ]
        ];
        $isMasterBooking = $extra['is_master_booking'] ?? false;

        if (!$isMasterBooking && ($data->tickets->quantity ?? 1) == 1) {
            $this->smsService->send($data);
            $this->whatsappService->send($data);
        }
        return $booking;
    }

    private function bookingData($data, $paymentId)
    {

        // // Build query more safely
        // $query = Booking::query();

        // if ($paymentId) {
        //     $query->where('payment_id', $paymentId);
        // }

        // if (!empty($data->session_id) || !empty($data->token)) {
        //     $query->where(function ($q) use ($data) {
        //         if (!empty($data->session_id)) {
        //             $q->orWhere('session_id', $data->session_id);
        //         }
        //         if (!empty($data->token)) {
        //             $q->orWhere('token', $data->token);
        //         }
        //     });
        // }

        // $exists = $query->first();

        // if ($exists) {
        //     return false;
        // }
        $booking = new Booking();
        $booking->ticket_id = $data->ticket_id;
        $booking->batch_id = Ticket::where('id', $data->ticket_id)->value('batch_id');
        $booking->user_id = $data->user_id;
        $booking->gateway = $data->gateway;
        $booking->session_id = $data->session_id;
        $booking->promocode_id = $data->promocode_id;
        $booking->token = $data->token;
        $booking->payment_id = $paymentId ?? NULL;
        $booking->amount = $data->amount > 0 ? $data->amount : 0;
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
        $booking->save();
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
            'booking_id' => is_array($ids) ? json_encode($ids) : json_encode([$ids]),
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


        $orderId = $booking->order_id ?? '';
        $shortLink = $orderId;
        $shortLinksms = "getyourticket.in/t/{$orderId}";

        $whatsappTemplate = WhatsappApi::where('title', 'Online Booking')->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $dates = explode(',', $booking->ticket->event->date_range);
        $formattedDates = [];
        foreach ($dates as $date) {
            $formattedDates[] = \Carbon\Carbon::parse($date)->format('d-m-Y');
        }
        $dateRangeFormatted = implode(' | ', $formattedDates);

        $eventDateTime = $dateRangeFormatted . ' | ' . $booking->ticket->event->start_time . ' - ' . $booking->ticket->event->end_time;
        //$eventDateTime = str_replace(',', ' |', $booking->ticket->event->date_range) . ' | ' . $booking->ticket->event->start_time . ' - ' . $booking->ticket->event->end_time;

        $totalQty = count($ids);
        $mediaurl = $booking->ticket->event->thumbnail;
        $data = (object) [
            'name' => $booking->name,
            'number' => $booking->number,
            'templateName' => 'Online Booking Template',
            'whatsappTemplateData' => $whatsappTemplateName,
            'mediaurl' => $mediaurl,
            'shortLink' => $shortLink,
            'insta_whts_url' => $booking->ticket->event->insta_whts_url ?? 'helloinsta',
            'values' => [
                $booking->name,
                $booking->number,
                $booking->ticket->event->name,
                $totalQty,
                $booking->ticket->name,
                $booking->ticket->event->address,
                $eventDateTime,
                $booking->ticket->event->whts_note ?? 'hello',
            ],
            'replacements' => [
                ':C_Name' => $booking->name,
                ':T_QTY' => $totalQty,
                ':Ticket_Name' => $booking->ticket->name,
                ':Event_Name' => $booking->ticket->event->name,
                ':Event_DateTime' => $eventDateTime,
                ':S_Link' => $shortLinksms,
            ]
        ];

        if ($totalQty >= 2) {
            $this->smsService->send($data);
            $this->whatsappService->send($data);
        }

        // Return true if all records are created successfully
        return $master;
    }

    private function updateAmusementMasterBooking($bookingMaster, $ids, $paymentId)
    {
        foreach ($bookingMaster as $entry) {
            if (!$entry) {
                continue; // Skip if entry is null
            }

            $data = [
                'user_id' => $entry->user_id,
                'gateway' => $entry->gateway,
                'session_id' => $entry->session_id,
                // 'booking_id' => $ids,
                'booking_id' => is_array($ids) ? json_encode($ids) : json_encode([$ids]),
                'order_id' => $entry->order_id,
                'amount' => $entry->amount,
                'discount' => $entry->discount,
                'payment_method' => $entry->payment_method,
                'payment_id' => $paymentId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Create MasterBooking record
            $master = AmusementMasterBooking::create($data);

            // If creation fails, return false
            if (!$master) {
                return false;
            }
        }

        // Return true if all records are created successfully
        return true;
    }


    private function transferEventBooking($decryptedSessionId, $status, $paymentId)
    {

        // $alreadyProcessed = Booking::where('payment_id', $paymentId)
        //     ->orWhere('session_id', $decryptedSessionId)
        //     ->first();

        // $alreadyInMaster = MasterBooking::Where('session_id', $decryptedSessionId)
        //     ->first();

        // if ($alreadyProcessed || $alreadyInMaster) {
        //     return response()->json([
        //         'status' => false,
        //         'msg' => 'This transaction is already processed. Duplicate booking blocked.'
        //     ], 409);
        // }

        $bookingMaster = PenddingBookingsMaster::where('session_id', $decryptedSessionId)->with('ticket.event')->get();
        $bookings = PenddingBooking::where('session_id', $decryptedSessionId)->with('ticket.event')->get();

        $totalQty = $bookings->count();

        if ($totalQty > 1 && $bookingMaster->isNotEmpty()) {
            $orderId = $bookingMaster->first()->order_id ?? '';
        } else {
            $orderId = $bookings[0]->token ?? '';
        }

        $shortLink = $orderId;
        $shortLinksms = "getyourticket.in/t/{$orderId}";
        $whatsappTemplate = WhatsappApi::where('title', 'Online Booking')->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $dates = explode(',', $bookings[0]->ticket->event->date_range);
        $formattedDates = [];
        foreach ($dates as $date) {
            $formattedDates[] = \Carbon\Carbon::parse($date)->format('d-m-Y');
        }
        $dateRangeFormatted = implode(' | ', $formattedDates);

        $eventDateTime = $dateRangeFormatted . ' | ' . $bookings[0]->ticket->event->start_time . ' - ' . $bookings[0]->ticket->event->end_time;
        // $eventDateTime = str_replace(',', ' |', $bookings[0]->ticket->event->date_range) . ' | ' . $bookings[0]->ticket->event->start_time . ' - ' . $bookings[0]->ticket->event->end_time;

        $totalQty = count($bookings) ?? 1;
        $mediaurl = $bookings[0]->ticket->event->thumbnail;
        $data = (object) [
            'name' => $bookings[0]->name,
            'number' => $bookings[0]->number,
            'templateName' => 'Online Booking Template',
            'whatsappTemplateData' => $whatsappTemplateName,
            'mediaurl' => $mediaurl,
            'shortLink' => $shortLink,
            'insta_whts_url' => $bookings[0]->ticket->event->insta_whts_url ?? 'helloinsta',
            'values' => [
                $bookings[0]->name,
                $bookings[0]->number,
                $bookings[0]->ticket->event->name,
                $totalQty,
                $bookings[0]->ticket->name,
                $bookings[0]->ticket->event->address,
                $eventDateTime,
                $bookings[0]->ticket->event->whts_note ?? 'hello',
            ],
            'replacements' => [
                ':C_Name' => $bookings[0]->name,
                ':T_QTY' => $totalQty,
                ':Ticket_Name' => $bookings[0]->ticket->name,
                ':Event_Name' => $bookings[0]->ticket->event->name,
                ':Event_DateTime' => $eventDateTime,
                ':S_Link' => $shortLinksms,
            ]
        ];

        $masterBookingIDs = [];
        //return $bookings;
        if ($bookings->isNotEmpty()) {
            foreach ($bookings as $individualBooking) {
                if ($status === 'success') {
                    $booking = $this->bookingData($individualBooking, $paymentId);
                    if ($booking) {
                        $masterBookingIDs[] = $booking->id;
                        $individualBooking->delete();
                    }
                } elseif ($status === 'failure') {
                    $individualBooking->payment_status = 2;
                    $individualBooking->payment_id = $paymentId;
                } else {
                    $individualBooking->payment_status = $status;
                }
                $individualBooking->save();
            }
        }

        if ($bookingMaster->isNotEmpty() && $status === 'success') {
            $updated = $this->updateMasterBooking($bookingMaster, $masterBookingIDs, $paymentId);
            if ($updated) {
                $bookingMaster->each->delete();
            }
        }

        // //send sms
        if ($status === 'success') {

            $this->smsService->send($data);
            $this->whatsappService->send($data);
        }
    }


    private function transferAmusementBooking($decryptedSessionId, $status, $paymentId)
    {

        $bookingMaster = AmusementPendingMasterBooking::where('session_id', $decryptedSessionId)->with('ticket.event')->get();
        $bookings = AmusementPendingBooking::where('session_id', $decryptedSessionId)->with('ticket.event')->get();

        $totalQty = $bookings->count();

        // ✅ Choose orderId based on booking count
        if ($totalQty > 1 && $bookingMaster->isNotEmpty()) {
            $orderId = $bookingMaster->first()->order_id ?? '';
        } else {
            $orderId = $bookings[0]->token ?? '';
        }

        $shortLink = $orderId;
        $shortLinksms = "getyourticket.in/t/{$orderId}";
        $whatsappTemplate = WhatsappApi::where('title', 'Online Booking')->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $dates = explode(',', $bookings[0]->ticket->event->date_range);
        $formattedDates = [];
        foreach ($dates as $date) {
            $formattedDates[] = \Carbon\Carbon::parse($date)->format('d-m-Y');
        }
        $dateRangeFormatted = implode(' | ', $formattedDates);

        $eventDateTime = $dateRangeFormatted . ' | ' . $bookings[0]->ticket->event->start_time . ' - ' . $bookings[0]->ticket->event->end_time;
        // $eventDateTime = str_replace(',', ' |', $bookings[0]->ticket->event->date_range) . ' | ' . $bookings[0]->ticket->event->start_time . ' - ' . $bookings[0]->ticket->event->end_time;

        $totalQty = count($bookings) ?? 1;
        $mediaurl = $bookings[0]->ticket->event->thumbnail;
        $data = (object) [
            'name' => $bookings[0]->name,
            'number' => $bookings[0]->number,
            'templateName' => 'Online Booking Template',
            'whatsappTemplateData' => $whatsappTemplateName,
            'mediaurl' => $mediaurl,
            'shortLink' => $shortLink,
            'insta_whts_url' => $bookings[0]->ticket->event->insta_whts_url ?? 'helloinsta',
            'values' => [
                $bookings[0]->name,
                $bookings[0]->number,
                $bookings[0]->ticket->event->name,
                $totalQty,
                $bookings[0]->ticket->name,
                $bookings[0]->ticket->event->address,
                $eventDateTime,
                $bookings[0]->ticket->event->whts_note ?? 'hello',
            ],
            'replacements' => [
                ':C_Name' => $bookings[0]->name,
                ':T_QTY' => $totalQty,
                ':Ticket_Name' => $bookings[0]->ticket->name,
                ':Event_Name' => $bookings[0]->ticket->event->name,
                ':Event_DateTime' => $eventDateTime,
                ':S_Link' => $shortLinksms,
            ]
        ];

        $masterBookingIDs = [];

        if ($bookings->isNotEmpty()) {
            foreach ($bookings as $individualBooking) {
                if ($status === 'success') {
                    $booking = $this->amusementBookingData($individualBooking, $paymentId);
                    if ($booking) {
                        $masterBookingIDs[] = $booking->id;
                        $individualBooking->delete();
                    }
                } elseif ($status === 'failure') {
                    $individualBooking->payment_status = 2;
                    $individualBooking->payment_id = $paymentId;
                } else {
                    $individualBooking->payment_status = $status;
                }
                $individualBooking->save();
            }
        }

        if ($bookingMaster->isNotEmpty() && $status === 'success') {
            $updated = $this->updateAmusementMasterBooking($bookingMaster, $masterBookingIDs, $paymentId);
            if ($updated) {
                $bookingMaster->each->delete();
            }
        }

        // //send sms
        if ($status === 'success') {

            $this->smsService->send($data);
            $this->whatsappService->send($data);
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
                    $booking->batch_id = Ticket::where('id', $requestData->tickets->id)->value('batch_id');
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

            return response()->json(['status' => true, 'message' => 'Tickets Booked Successfully', 'bookings' => $bookings, 'PenddingBookingsMaster' => $penddingBookingsMaster], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to book tickets', 'error' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
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
            return response()->json(['status' => true, 'message' => 'Tickets Booked Successfully', 'bookings' => $bookings, 'PenddingBookingsMaster' => $penddingBookingsMaster], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to book tickets', 'error' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    //we got the error while fetching the result
    private function updateMasterBookingZero($booking, $ids)
    {

        $data = [
            'user_id' => $booking->user_id,
            'session_id' => $booking->session_id,
            'booking_id' => $ids,
            'order_id' => $this->generateHexadecimalCode(),
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

        $orderId = $master->order_id ?? '';
        $shortLink = $orderId;
        $shortLinksms = "getyourticket.in/t/{$orderId}";
        $whatsappTemplate = WhatsappApi::where('title', 'Online Booking')->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $dates = explode(',', $booking->ticket->event->date_range);
        $formattedDates = [];
        foreach ($dates as $date) {
            $formattedDates[] = \Carbon\Carbon::parse($date)->format('d-m-Y');
        }
        $dateRangeFormatted = implode(' | ', $formattedDates);

        $eventDateTime = $dateRangeFormatted . ' | ' . $booking->ticket->event->start_time . ' - ' . $booking->ticket->event->end_time;
        //$eventDateTime = str_replace(',', ' |', $booking->ticket->event->date_range) . ' | ' . $booking->ticket->event->start_time . ' - ' . $booking->ticket->event->end_time;

        $totalQty = count($ids);
        $mediaurl = $booking->ticket->event->thumbnail;
        $data = (object) [
            'name' => $booking->name,
            'number' => $booking->number,
            'templateName' => 'Online Booking Template',
            'whatsappTemplateData' => $whatsappTemplateName,
            'mediaurl' => $mediaurl,
            'shortLink' => $shortLink,
            'insta_whts_url' => $booking->ticket->event->insta_whts_url ?? 'helloinsta',
            'values' => [
                $booking->name,
                $booking->number,
                $booking->ticket->event->name,
                $totalQty,
                $booking->ticket->name,
                $booking->ticket->event->address,
                $eventDateTime,
                $booking->ticket->event->whts_note ?? 'hello',
            ],
            'replacements' => [
                ':C_Name' => $booking->name,
                ':T_QTY' => $totalQty,
                ':Ticket_Name' => $booking->ticket->name,
                ':Event_Name' => $booking->ticket->event->name,
                ':Event_DateTime' => $eventDateTime,
                ':S_Link' => $shortLinksms,
            ]
        ];
        if ($totalQty >= 2) {

            $this->smsService->send($data);
            $this->whatsappService->send($data);
        }

        // Return true if all records are created successfully
        return $master;
    }

    private function updateMasterBooking($bookingMaster, $ids, $paymentId)
    {

        // Safety check - minimal change
        // if ($bookingMaster->isEmpty()) {
        //     return false;
        // }
        // $exists = MasterBooking::Where('session_id', $bookingMaster->first()->session_id)
        //     ->orWhere('order_id', $bookingMaster->first()->order_id)
        //     ->first();

        // if ($exists) {
        //     return false;
        // }

        foreach ($bookingMaster as $entry) {
            $data = [
                'user_id' => $entry->user_id,
                'session_id' => $entry->session_id,
                'booking_id' => $ids,
                'order_id' => $entry->order_id,
                'amount' => $entry->amount,
                'discount' => $entry->discount,
                'payment_method' => $entry->payment_method,
                'gateway' => $entry->gateway,
                'payment_id' => $paymentId,
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

            $promocode->save();
        }

        $orderId = $booking->token ?? '';
        $shortLink = $orderId;
        $shortLinksms = "getyourticket.in/t/{$orderId}";
        $whatsappTemplate = WhatsappApi::where('title', 'Online Booking')->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $dates = explode(',', $booking->ticket->event->date_range);
        $formattedDates = [];
        foreach ($dates as $date) {
            $formattedDates[] = \Carbon\Carbon::parse($date)->format('d-m-Y');
        }
        $dateRangeFormatted = implode(' | ', $formattedDates);

        $eventDateTime = $dateRangeFormatted . ' | ' . $booking->ticket->event->start_time . ' - ' . $booking->ticket->event->end_time;
        // $eventDateTime = str_replace(',', ' |', $booking->ticket->event->date_range) . ' | ' . $booking->ticket->event->start_time . ' - ' . $booking->ticket->event->end_time;

        $totalQty = 1;
        $mediaurl = $booking->ticket->event->thumbnail;
        $data = (object) [
            'name' => $booking->name,
            'number' => $booking->number,
            'templateName' => 'Online Booking Template',
            'whatsappTemplateData' => $whatsappTemplateName,
            'mediaurl' => $mediaurl,
            'shortLink' => $shortLink,
            'insta_whts_url' => $booking->ticket->event->insta_whts_url ?? 'helloinsta',
            'values' => [
                $booking->name,
                $booking->number,
                $booking->ticket->event->name,
                $totalQty,
                $booking->ticket->name,
                $booking->ticket->event->address,
                $eventDateTime,
                $booking->ticket->event->whts_note ?? 'hello',
            ],
            'replacements' => [
                ':C_Name' => $booking->name,
                ':T_QTY' => $totalQty,
                ':Ticket_Name' => $booking->ticket->name,
                ':Event_Name' => $booking->ticket->event->name,
                ':Event_DateTime' => $eventDateTime,
                ':S_Link' => $shortLinksms,
            ]
        ];

        $isMasterBooking = $extra['is_master_booking'] ?? false;

        if (!$isMasterBooking && ($data->tickets->quantity ?? 1) == 1) {
            $this->smsService->send($data);
            $this->whatsappService->send($data);
        }

        return $booking;
    }


    private function amusementBookingData($data, $paymentId)
    {

        $booking = new AmusementBooking();
        $booking->ticket_id = $data->ticket_id;
        $booking->user_id = $data->user_id;
        $booking->gateway = $data->gateway;
        $booking->session_id = $data->session_id;
        $booking->promocode_id = $data->promocode_id;
        $booking->token = $data->token;
        $booking->payment_id = $paymentId ?? NULL;
        $booking->amount = $data->amount ?? 0;
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
        $booking->booking_date = $data->booking_date;
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



    public function success(Request $request)
    {
        Log::info('Easebuzz Request received:', $request->all());
        return response()->json(['message' => 'Payment successful', 'data' => $request->all()]);
    }

    protected function parseDomainFromReferer(?string $referer): ?string
    {
        if (!$referer)
            return null;
        $parsed = parse_url($referer);
        return $parsed['host'] ?? null;
    }

    public function verifyBooking(Request $request)
    {
        // $referer = $request->headers->get('referer');
        // $refererDomain = $this->parseDomainFromReferer($referer);
        // //return $refererDomain;
        // $allowedDomain = env('REFERER_DOMAIN', 'ssgarba.com');

        // if ($refererDomain !== $allowedDomain) {
        //     return response()->json(['error' => 'Access denied.'], 403);
        // }
        try {
            $decryptedSessionId = $request->session_id;
            if ($decryptedSessionId) {
                //return $decryptedSessionId;
                $paymentLog = PaymentLog::where('session_id', $decryptedSessionId)->first();
                //return $paymentLog;
                if (!$paymentLog) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Payment log not found.'
                    ], 404);
                }

                $status = strtolower(trim($paymentLog->status));
                $successStatuses = ['success', 'credit', 'completed', 'paid'];
                $failureStatuses = ['failed', 'failure', 'error', 'cancelled', 'declined'];
                if (in_array($status, $successStatuses)) {
                    $status = 'success';
                } else if (in_array($status, $failureStatuses)) {
                    $status = 'failed';
                }

                if ($status != 'success') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Payment Failed.'
                    ], 400);
                } else {
                    $paymentId = null;
                    if ($paymentLog) {
                        $paymentId = $paymentLog->payment_id;
                    }
                    //return $paymentId;
                    return $this->verifyBookingData($decryptedSessionId, $paymentId);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid session ID or booking not found.'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to verify booking.',
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    private function VerifyBookingData($decryptedSessionId, $paymentId)
    {

        $master = MasterBooking::where('session_id', $decryptedSessionId)->get();

        if (count($master) > 0) {
            $bookingIds = is_array($master[0]->booking_id) ? $master[0]->booking_id : json_decode($master[0]->booking_id, true);

            $master[0]->bookings = !empty($bookingIds)
                ? Booking::whereIn('id', $bookingIds)
                ->with(['ticket.event.user', 'user', 'attendee'])
                ->latest()
                ->get()
                ->map(function ($booking) {
                    // Attach event name and organizer to each booking
                    $booking->event_name = $booking->ticket->event->name;
                    $booking->organizer = $booking->ticket->event->user->name;
                    $booking->is_deleted = $booking->trashed();
                    return $booking;
                })
                : collect();

            return response()->json([
                'status' => true,
                'bookings' => $master,
                'isMaster' => true
            ], 200);
        } else {

            // Handle single booking if no master booking is found
            $booking = Booking::with('ticket.event.user', 'attendee', 'user')
                ->where('session_id', $decryptedSessionId)
                ->get()

                ->map(function ($booking) {
                    $booking->event_name = $booking->ticket->event->name;
                    $booking->organizer = $booking->ticket->event->user->name;
                    $booking->is_deleted = $booking->trashed();
                    return $booking;
                });
            //	return $booking;
            //return response()->json($decryptedSessionId);
            if ($booking->isNotEmpty()) {
                return response()->json([
                    'status' => true,
                    'bookings' => $booking,
                    'isMaster' => false
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No bookings found for the provided session ID.'
                ], 404);
            }
        }
    }
    public function verifyAmusementBooking(Request $request)
    {
        $referer = $request->headers->get('referer');
        $refererDomain = $this->parseDomainFromReferer($referer);
        $allowedDomain = env('REFERER_DOMAIN', 'getyourticket.in');

        if ($refererDomain !== $allowedDomain) {
            return response()->json(['error' => 'Access denied.'], 403);
        }

        try {
            //$decryptedSessionId = decrypt($request->session_id);
            $decryptedSessionId = $request->session_id;
            //return '$decryptedSessionId';
            if ($decryptedSessionId) {
                //return $decryptedSessionId;
                $paymentLog = PaymentLog::where('session_id', $decryptedSessionId)->first();

                if (!$paymentLog) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Payment log not found.'
                    ], 404);
                }
                $status = strtolower(trim($paymentLog->status));
                $successStatuses = ['success', 'credit', 'completed', 'paid'];
                $failureStatuses = ['failed', 'failure', 'error', 'cancelled', 'declined'];
                if (in_array($status, $successStatuses)) {
                    $status = 'success';
                } else if (in_array($status, $failureStatuses)) {
                    $status = 'failed';
                }

                if ($status != 'success') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Payment Failed.'
                    ], 400);
                } else {
                    $paymentId = null;
                    if ($paymentLog) {
                        $paymentId = $paymentLog->payment_id;
                    }
                    return $this->verifyAmusementBookingData($decryptedSessionId, $paymentId);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid session ID or booking not found.'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to verify booking.',
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    private function verifyAmusementBookingData($decryptedSessionId, $paymentId)
    {

        $master = AmusementMasterBooking::where('session_id', $decryptedSessionId)->get();

        if (count($master) > 0) {
            $bookingIds = is_array($master[0]->booking_id) ? $master[0]->booking_id : json_decode($master[0]->booking_id, true);

            $master[0]->bookings = !empty($bookingIds)
                ? AmusementBooking::whereIn('id', $bookingIds)
                ->with(['ticket.event.user', 'user', 'attendee'])
                ->latest()
                ->get()
                ->map(function ($booking) {
                    // Attach event name and organizer to each booking
                    $booking->event_name = $booking->ticket->event->name;
                    $booking->organizer = $booking->ticket->event->user->name;
                    $booking->is_deleted = $booking->trashed();
                    return $booking;
                })
                : collect();

            return response()->json([
                'status' => true,
                'bookings' => $master,
                'isMaster' => true
            ], 200);
        } else {
            // Handle single booking if no master booking is found
            $booking = AmusementBooking::with('ticket.event.user', 'attendee', 'user')
                ->where('session_id', $decryptedSessionId)
                ->get()
                ->map(function ($booking) {
                    $booking->event_name = $booking->ticket->event->name;
                    $booking->organizer = $booking->ticket->event->user->name;
                    $booking->is_deleted = $booking->trashed();
                    return $booking;
                });

            if ($booking->isNotEmpty()) {
                return response()->json([
                    'status' => true,
                    'bookings' => $booking,
                    'isMaster' => false
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No bookings found for the provided session ID.'
                ], 404);
            }
        }
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
}
