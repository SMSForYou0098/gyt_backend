<?php

namespace App\Services;

use App\Models\FcmToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client;

class FirebaseNotificationService
{
    protected $projectId;
    protected $accessToken;

    public function __construct()
    {
         $configPath = config('firebase.projects.app.credentials');
        // Check if file exists
        if ($configPath) {
            // Check if the path is absolute or relative
            if (file_exists($configPath)) {
                $credentialsPath = $configPath;
            } elseif (file_exists(base_path($configPath))) {
                $credentialsPath = base_path($configPath);
            } elseif (file_exists(storage_path($configPath))) {
                $credentialsPath = storage_path($configPath);
            } else {
                // Fallback to a direct path
                $credentialsPath = storage_path('app/firebase/service-account.json');
            }
        } else {
            // If no config path, use the default location
            $credentialsPath = storage_path('app/firebase/service-account.json');
        }
        
        $credentialsFile = json_decode(file_get_contents($credentialsPath), true);
        $this->projectId = $credentialsFile['project_id'];
        $this->getAccessToken($credentialsPath);
    }

    /**
     * Get Google Cloud Access Token using Google Client
     */
    private function getAccessToken($credentialsPath)
    {
        try {
            $client = new Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $this->accessToken = $client->fetchAccessTokenWithAssertion()['access_token'];
        } catch (\Exception $e) {
            Log::error('Firebase authentication error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send notification to a single device
     */
    public function sendToDevice($token, $title, $body, $data = [])
    {
        // Make sure data is an associative array with string values
        $formattedData = [];
        foreach ($data as $key => $value) {
            // FCM requires all values to be strings
            $formattedData[$key] = (string) $value;
        }
    
         $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'webpush' => [  // Add webpush configuration
                    'headers' => [
                        'Urgency' => 'high'
                    ],
                    'notification' => [
                        //'icon' => '/notification-icon.png',
                        //'badge' => '/badge-icon.png',
                        'vibrate' => [100, 50, 100],
                        'requireInteraction' => true
                    ],
                    'fcm_options' => [
                        'link' => 'https://nav.ssgarba.com/'
                        // 'link' => 'https://ticket.getyourticket.in/'
                    ]
                ],
                'android' => [
                    'notification' => [
                        'sound' => 'default',
                        'click_action' => 'REACT_NOTIFICATION_CLICK',
                        'channel_id' => 'high_importance_channel', // Add this
                        'priority' => 'high', // Add this
                    ],
                    'priority' => 'high', // Add this at android root level
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'content-available' => 1,
                        ],
                    ],
                ],
            ],
        ];
    
        // Only add data if it's not empty
        if (!empty($formattedData)) {
            $message['message']['data'] = $formattedData;
        }
    
        return $this->sendNotification($message);
    }

    /**
     * Send notification to multiple devices
     */
    public function sendToMultipleDevices($tokens, $title, $body, $data = [])
    {
        if (empty($tokens)) {
            return ['error' => 'No tokens provided'];
        }
        
        // Format data to ensure all values are strings
        $formattedData = [];
        foreach ($data as $key => $value) {
            $formattedData[$key] = (string) $value;
        }
        
        // Method 1: Using multicast (best for up to 500 tokens)
        if (count($tokens) <= 500) {
            return $this->sendMulticast($tokens, $title, $body, $formattedData);
        } 
        // Method 2: Split into batches for larger numbers
        else {
            $results = [];
            $tokenBatches = array_chunk($tokens, 500);
            
            foreach ($tokenBatches as $batch) {
                $results[] = $this->sendMulticast($batch, $title, $body, $formattedData);
            }
            
            return $results;
        }
    }
    public function sendToAllDevices($title, $body, $data = [])
    {
        $tokens = FcmToken::pluck('token')->toArray();
        return $this->sendToMultipleDevices($tokens, $title, $body, $data);
    }
    private function sendMulticast($tokens, $title, $body, $data)
    {
        try {
            // For FCM HTTP v1 API, we need to create a message for each token
            $messages = [];
            foreach ($tokens as $token) {
                $message = [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'android' => [
                        'notification' => [
                            'sound' => 'default',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'content-available' => 1,
                            ],
                        ],
                    ],
                ];
                
                // Only add data if it's not empty
                if (!empty($data)) {
                    $message['data'] = $data;
                }
                
                $messages[] = $message;
            }
            
            // Use batch API to send multiple messages at once
            return $this->sendBatchNotification($messages);
            
        } catch (\Exception $e) {
            Log::error('Firebase multicast notification exception: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    /**
     * Send notification to topic
     */
    public function sendToTopic($topic, $title, $body, $data = [])
    {
        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'android' => [
                    'notification' => [
                        'sound' => 'default',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'content-available' => 1,
                        ],
                    ],
                ],
            ],
        ];

        return $this->sendNotification($message);
    }

    /**
     * Send notification to user
     */
    public function sendToUser($userId, $title, $body, $data = [])
    {
        $tokens = FcmToken::where('user_id', $userId)->pluck('token')->toArray();
        return $this->sendToMultipleDevices($tokens, $title, $body, $data);
    }

    /**
     * Send notification (Firebase HTTP v1 API)
     */
    private function sendNotification($message)
    {
        try {
            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
            
            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $message);
            
            if ($response->failed()) {
                Log::error('Firebase notification error: ' . $response->body());
            }
            
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Firebase notification exception: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    private function sendBatchNotification($messages)
    {
        try {
            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
            
            $responses = [];
            
            // We can't send more than 500 messages in one request, so we send them individually
            // but we could implement a more efficient batch approach if needed
            foreach ($messages as $message) {
                $payload = [
                    'message' => $message
                ];
                
                $response = Http::withToken($this->accessToken)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url, $payload);
                
                $responses[] = $response->json();
            }
            
            return [
                'total' => count($messages),
                'responses' => $responses
            ];
            
        } catch (\Exception $e) {
            Log::error('Firebase batch notification exception: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}