<?php

namespace App\Services;

use PhonePe\payments\v2\standardCheckout\StandardCheckoutClient;
use PhonePe\payments\v2\models\request\builders\StandardCheckoutPayRequestBuilder;
use PhonePe\common\exceptions\PhonePeException;
use PhonePe\Env;
use Exception;
use Illuminate\Support\Facades\Log;

class PhonePeService
{
    protected $client;
    protected $clientId;
    protected $clientVersion;
    protected $clientSecret;
    protected $environment;

    public function __construct($data = [])
    {
        try {
            //$this->clientId = config('phonepe.client_id');
            //$this->clientVersion = config('phonepe.client_version');
            //$this->clientSecret = config('phonepe.client_secret');
            //$this->environment = config('phonepe.environment') === 'production' ? Env::PRODUCTION : Env::UAT;


            // Initialize the PhonePe client with error handling
            $this->client = StandardCheckoutClient::getInstance(
               $this->clientId,
                $this->clientVersion,
                $this->clientSecret,
                $this->environment
            );

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Create a payment request
     */
    public function createPayment($paymentData)
    {
        try {
            // Validate required data
            if (empty($paymentData['transaction_id']) || empty($paymentData['amount'])) {
                throw new Exception('Missing required payment data: transaction_id or amount');
            }
            $this->clientId = $paymentData['client_id'];
            $this->clientVersion = $paymentData['client_version'] ?? 1;
            $this->clientSecret = $paymentData['client_secret'];
            $this->environment = ($paymentData['environment'] ?? 'staging') === 'production'
                ? Env::PRODUCTION
                : Env::UAT;
            // Build full URLs for callback and redirect
          
            $redirectUrl = $paymentData['redirect_url'];
			
            Log::info('PhonePe Service Initialization:', [
                'client_id' => $this->clientId,
                'client_version' => $this->clientVersion,
                'environment' => $this->environment
            ]);

            $this->client = StandardCheckoutClient::getInstance(
               $this->clientId,
                $this->clientVersion,
                $this->clientSecret,
                $this->environment
            );
            // Create payment request using StandardCheckoutPayRequestBuilder
            $payRequest = StandardCheckoutPayRequestBuilder::builder()
                ->merchantOrderId($paymentData['transaction_id'])
                ->amount((int) ($paymentData['amount'] * 100)) // Convert to paise
                ->redirectUrl($redirectUrl)

                ->message('Payment for Order #' . $paymentData['transaction_id'])
                //->context($paymentData['context'] ?? [])
                ->build();
	
            usleep(100000); 
            $payResponse = $this->client->pay($payRequest);
            if (!method_exists($payResponse, 'getState')) {
                throw new Exception('Invalid payment response object');
            }
            $state = $payResponse->getState();
            // Handle the response based on PhonePe SDK structure
            if ($state === "PENDING") {
                $redirectUrl = method_exists($payResponse, 'getRedirectUrl')
                    ? $payResponse->getRedirectUrl()
                    : null;

                return [
                    'success' => true,
                    'message' => 'Payment initiated successfully',
                    'transaction_id' => $paymentData['transaction_id'],
                    'payment_url' => $redirectUrl,
                    'state' => $state,
                    'data' => [
                        'redirect_url' => $redirectUrl,
                        'state' => $state,
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Payment initiation failed',
                    'error' => 'Payment state: ' . $state,
                    'state' => $state,
                    'data' => [
                        'state' => $state,
                    ]
                ];
            }

        } catch (PhonePeException $e) {
            return [
                'success' => false,
                'message' => 'PhonePe payment failed',
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_type' => 'PhonePeException'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Payment creation failed',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
                'exception_type' => 'GeneralException'
            ];
        }
    }

    // ... rest of the methods remain the same ...

    /**
     * Check payment status
     */
    public function checkPaymentStatus($transactionId)
    {
        try {
            usleep(100000); // 100ms delay

            $statusCheckResponse = $this->client->getOrderStatus($transactionId, true);

            $state = 'UNKNOWN';
            if (method_exists($statusCheckResponse, 'getState')) {
                $state = $statusCheckResponse->getState();
            }

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'state' => $state,
                'status' => $state,
                'data' => [
                    'state' => $state,
                ]
            ];

        } catch (PhonePeException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exception_type' => 'PhonePeException'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exception_type' => 'GeneralException'
            ];
        }
    }

    /**
     * Get client info for debugging
     */
    public function getClientInfo()
    {
        return [
            'client_class' => get_class($this->client),
            'available_methods' => get_class_methods($this->client),
            'client_id' => $this->clientId,
            'environment' => $this->environment,
            'client_version' => $this->clientVersion,
            'config' => [
                'base_url' => config('phonepe.base_url'),
                'callback_url' => config('phonepe.callback_url'),
                'redirect_url' => config('phonepe.redirect_url'),
            ]
        ];
    }

    /**
     * Validate payment callback
     */
    public function validateCallback($callbackData)
    {
        try {
            return [
                'success' => true,
                'data' => $callbackData
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}