<?php

namespace App\Services;

use Cashfree\Cashfree;
use Exception;
use Illuminate\Support\Facades\Log;

class CashfreeService
{
    private $cashfree;

    public function __construct()
    {
        // Initialize Cashfree configuration
        $this->cashfree = new Cashfree(
             0, // XEnvironment (0 = SANDBOX, 1 = PRODUCTION)
            '','',
            '', // XPartnerApiKey (empty for non-partner accounts)
            '', // XPartnerMerchantId (empty for non-partner accounts)
            '', // XClientSignature (empty if not using signature)
            false // XEnableErrorAnalytics - Set to false to avoid the bug
        );
    }

    public function createOrder($orderId, $amount, $customerId, $customerName, $customerEmail, $customerPhone)
    {
        $gateway = 'cashfree';
        $sessionId = session()->getId();
        try {
            $request = [
                'order_amount' => (float)$amount,
                'order_currency' => 'INR',
                'order_id' => $orderId,
                'customer_details' => [
                    'customer_id' => (string)$customerId,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone
                ],
                "order_meta" => [
                    "return_url" => url('/api/payment-response/' . $gateway . '/' . 1 . '/' . $sessionId)
                ]
            ];
            
            // Debug: Log the request
            Log::info('Cashfree Request:', $request);
            
            $response = $this->cashfree->PGCreateOrder($request);
            
            // Debug: Log the response
            Log::info('Cashfree Response:', $response);
            
            return $response;
        } catch (Exception $e) {
            Log::error('Cashfree Error:', ['error' => $e->getMessage()]);
            return 'Failed to create Cashfree order: ' . $e->getMessage();
        }
    }

    public function fetchOrder($orderId)
    {
        try {
            return $this->cashfree->PGFetchOrder($orderId);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch Cashfree order: ' . $e->getMessage());
        }
    }

    public function getPaymentUrl($paymentSessionId)
    {
        // Construct the payment URL using the payment session ID
        $baseUrl = $this->cashfree->XEnvironment == 0 
            ? 'https://sandbox.cashfree.com/checkout' 
            : 'https://checkout.cashfree.com/checkout';
        
        return $baseUrl . '?payment_session_id=' . $paymentSessionId;
    }

    public function createOrderAndGetPaymentUrl($orderId, $amount, $customerId, $customerName, $customerEmail, $customerPhone)
    {
        try {
            $orderResponse = $this->createOrder($orderId, $amount, $customerId, $customerName, $customerEmail, $customerPhone);
            
            if (is_string($orderResponse)) {
                // Error occurred
                return $orderResponse;
            }
            
            // Extract payment session ID from response
            $responseData = $orderResponse[0]; // The first element contains the order data
            $paymentSessionId = $responseData['payment_session_id'];
            
            return [
                'order_data' => $responseData,
                'payment_url' => $this->getPaymentUrl($paymentSessionId),
                'payment_session_id' => $paymentSessionId
            ];
        } catch (Exception $e) {
            return 'Failed to create order and payment URL: ' . $e->getMessage();
        }
    }
}