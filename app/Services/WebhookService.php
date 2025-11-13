<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;
use App\Models\AmusementBooking;
use App\Models\AmusementMasterBooking;
use App\Models\PenddingBooking;
use App\Models\AmusementPendingBooking;
use App\Models\AmusementPendingMasterBooking;
use App\Models\EasebuzzConfig;
use App\Models\MasterBooking;
use App\Models\PaymentLog;
use App\Models\PenddingBookingsMaster;
use App\Models\PromoCode;
use App\Models\Ticket;
use App\Models\WhatsappApi;

class WebhookProcessor
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


    public function process($gateway, $params)
    {
        Log::info("[$gateway] Processing webhook in service...", $params);

        try {
            $sessionId = null;
            $category  = null;
            $status    = null;
            $paymentId = null;
            $urlData   = null;

            // ---- PhonePe ----
            if ($gateway === 'phonepe') {
                $webhookData = $this->extractPhonePeWebhookData(new Request($params));
                $sessionId   = $webhookData['session_id'];
                $category    = $webhookData['category'];
                $status      = $webhookData['status'];
                $paymentId   = $webhookData['payment_id'];

                $params = array_merge($params, [
                    'status'            => $status,
                    'amount'            => $webhookData['amount'],
                    'mode'              => $webhookData['mode'],
                    'merchant_order_id' => $webhookData['merchant_order_id'],
                    'order_id'          => $webhookData['order_id'],
                    'utr'               => $webhookData['utr'],
                    'category'          => $category
                ]);
            }

            // ---- Razorpay ----
            elseif ($gateway === 'razorpay') {
                $webhookData = $this->extractRazorpayWebhookData(new Request($params));
                $sessionId   = $webhookData['session_id'];
                $category    = $webhookData['category'];
                $status      = $webhookData['status'];
                $paymentId   = $webhookData['payment_id'];

                $params = array_merge($params, [
                    'status'     => $status,
                    'amount'     => $webhookData['amount'],
                    'order_id'   => $webhookData['order_id'],
                    'payment_id' => $webhookData['payment_id'],
                    'method'     => $webhookData['method'],
                    'event'      => $webhookData['event'],
                    'category'   => $category
                ]);
            }
            // ---- Cashfree ----
            // ---- Cashfree ----
            elseif ($gateway === 'cashfree') {
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


            // ---- Easebuzz ----
            elseif ($gateway === 'easebuzz') {
                $statusRaw = $params['status'] ?? null;
                $status    = strtolower(trim($statusRaw));
                $paymentId = $params['easepayid'] ?? null;

                if (!isset($params['surl'])) {
                    Log::warning("[$gateway] Missing 'surl' in webhook.");
                    return;
                }

                $urlData   = $this->extractLastPathSegment($params['surl']);
                $sessionId = $urlData['session_id'];
                $category  = $urlData['category'];
            }

            // ---- Instamojo ----
            elseif ($gateway === 'instamojo') {
                $statusRaw = $params['status'] ?? null;
                $status    = strtolower(trim($statusRaw));
                $paymentId = $params['payment_id'] ?? null;

                $urlData   = [
                    'session_id' => $params['sessionId'] ?? null,
                    'category'   => urldecode($params['category'] ?? 'Event')
                ];
                $sessionId = $urlData['session_id'];
                $category  = $urlData['category'];
            }

            // ---- Status normalize for non-phonepe/razorpay ----
            if ($gateway !== 'phonepe' && $gateway !== 'razorpay') {
                $successStatuses = ['success', 'credit', 'completed', 'paid'];
                $failureStatuses = ['failed', 'failure', 'error', 'cancelled', 'declined'];

                if (in_array($status, $successStatuses)) {
                    $status = 'success';
                } elseif (in_array($status, $failureStatuses)) {
                    $status = 'failed';
                } else {
                    Log::warning("[$gateway] Unknown status value: $status");
                    return;
                }
            }

            // ---- Validate ----
            if (!$paymentId || !$sessionId || !$category) {
                Log::warning("[$gateway] Missing required fields", compact('paymentId', 'sessionId', 'category'));
                return;
            }

            // ---- Duplicate check ----
            if ($this->checkExistingBooking($sessionId, $paymentId)) {
                Log::warning("[$gateway] Duplicate webhook for session $sessionId / payment $paymentId");
                return;
            }

            // ---- Store Log ----
            $this->storePaymentLog($gateway, $sessionId, $params);

            // ---- Transfer Booking ----
            if ($category === 'Amusement') {
                $this->transferAmusementBooking($sessionId, $status, $paymentId);
            } else {
                $this->transferEventBooking($sessionId, $status, $paymentId);
            }

            Log::info("[$gateway] Webhook processed successfully", compact('sessionId', 'status', 'paymentId'));
        } catch (\Exception $e) {
            Log::error("[$gateway] Webhook processing failed: " . $e->getMessage(), [
                'params' => $params
            ]);
        }
    }

    // અહીં તમારો પહેલાનો આખો private methods code 그대로 મૂકો:
    // - extractLastPathSegment
    // - checkExistingBooking
    // - extractPhonePeWebhookData
    // - extractSessionFromMerchantOrderId
    // - extractRazorpayWebhookData
    // - extractSessionFromCallbackUrl
    // - extractCategoryFromCallbackUrl
    // - determineCategoryFromMerchantOrderId
    // - storePaymentLog
    // - transferAmusementBooking
    // - transferEventBooking
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
            $sessionId = null;
            // if ($orderId && str_contains($orderId, '_')) {
            //     $sessionId = explode('_', $orderId)[1] ?? null;
            // }

            // // Fallback: lookup in pending table
            // if (!$sessionId) {
                $pending = PenddingBooking::where('session_id', $orderId)->first();
                $sessionId = $pending->session_id ?? null;
            // }

            $category = $pending->category ?? 'Event';

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
                'txnid' => $params['order_id'] ?? $params['cf_order_id'] ?? null,
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
            'name' => $$bookings[0]->name,
            'number' => $$bookings[0]->number,
            'templateName' => 'Online Booking Template',
            'whatsappTemplateData' => $whatsappTemplateName,
            'mediaurl' => $mediaurl,
            'shortLink' => $shortLink,
            'insta_whts_url' => $$bookings[0]->ticket->event->insta_whts_url ?? 'helloinsta',
            'values' => [
                $$bookings[0]->name,
                $$bookings[0]->number,
                $$bookings[0]->ticket->event->name,
                $totalQty,
                $$bookings[0]->ticket->name,
                $$bookings[0]->ticket->event->address,
                $eventDateTime,
                $$bookings[0]->ticket->event->whts_note ?? 'hello',
            ],
            'replacements' => [
                ':C_Name' => $$bookings[0]->name,
                ':T_QTY' => $totalQty,
                ':Ticket_Name' => $$bookings[0]->ticket->name,
                ':Event_Name' => $$bookings[0]->ticket->event->name,
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


    private function transferEventBooking($decryptedSessionId, $status, $paymentId)
    {
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
        //$eventDateTime = str_replace(',', ' |', $bookings[0]->ticket->event->date_range) . ' | ' . $bookings[0]->ticket->event->start_time . ' - ' . $bookings[0]->ticket->event->end_time;

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
            $promocode = PromoCode::where('code', $booking->promocode_id)->first();

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

    private function updateMasterBooking($bookingMaster, $ids, $paymentId)
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

    private function bookingData($data, $paymentId)
    {

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
}
