<?php

namespace App\Http\Controllers;

use App\Models\FcmToken;
use App\Notifications\PushNotification;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class NotificationController extends Controller
{
    
    protected $serverKey;
    
    public function __construct()
    {
        // $this->middleware(['auth', 'admin']);
        $this->serverKey = config('services.firebase.server_key');
    }

    public function sendToToken(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);
    
        $fcmService = new FirebaseNotificationService();
        
        // Get all tokens or filter by device type if needed
        $query = FcmToken::query();
        
        $tokens = $query->pluck('token')->toArray();
        
        $result = $fcmService->sendToMultipleDevices(
            $tokens,
            $request->title,
            $request->body,
            $request->data ?? []
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Notification sent to ' . count($tokens) . ' devices',
            'result' => $result
        ]);
    }
    public function sendDirect($userId)
    {
        $fcmService = new FirebaseNotificationService();
        
        $result = $fcmService->sendToUser(
            $userId, 
            'Hello from Laravel', 
            'This is a test notification',
            [
                'type' => 'test',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ]
        );
        
        return response()->json([
            'success' => true,
            'result' => $result
        ]);
    }
    public function sendWithNotification($userId)
    {
        $user = User::findOrFail($userId);
        
        $user->notify(new PushNotification(
            'Hello from Laravel Notification', 
            'This is a test using notification class',
            [
                'type' => 'notification',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ]
        ));
        
        return response()->json([
            'success' => true,
            'message' => 'Notification sent successfully'
        ]);
    }
    public function storeToken(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'user_id' => 'nullable|numeric'
            ]);
            
            FcmToken::updateOrInsert(
                ['token' => $request->token],
                [
                    'user_id' => $request->user_id ?? null,
                    'updated_at' => now(),
                    'created_at' => DB::raw('IFNULL(created_at, NOW())')
                ]
            );
            
            return response()->json(['success' => true, 'message' => 'Token saved']);
        } catch (\Exception $e) {
            Log::error('Error saving FCM token: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error saving token'], 500);
        }
    }
    
    public function dashboard()
    {
        $tokens = FcmToken::latest()->paginate(20);
        $stats = [
            'total_tokens' => FcmToken::count(),
            'unique_users' => FcmToken::distinct('user_id')->count('user_id'),
            'new_tokens' => FcmToken::where('created_at', '>=', now()->subDays(7))->count(),
            'with_user_id' => FcmToken::whereNotNull('user_id')->count(),
            'without_user_id' => FcmToken::whereNull('user_id')->count(),
           
        ];
        
        return view('admin.notifications.dashboard', compact('stats'));
    }
    
    // public function showSendForm()
    // {
    //     return view('admin.notifications.send');
    // }
    
    public function sendToAll(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'image_url' => 'nullable|url',
            'click_url' => 'nullable|url'
        ]);
        
        try {
            // Get all tokens
            $tokens = FcmToken::pluck('token')->toArray();
            
            if (empty($tokens)) {
                return back()->with('error', 'No FCM tokens found in database.');
            }
            
            // Send in batches of 500 (FCM limit)
            $batches = array_chunk($tokens, 500);
            $successCount = 0;
            $failureCount = 0;
            
            foreach ($batches as $batchTokens) {
                $response = $this->sendBatchNotification(
                    $batchTokens, 
                    $validated['title'], 
                    $validated['body'], 
                    $validated['image_url'] ?? null,
                    $validated['click_url'] ?? null
                );
                
                // Process response
                if (isset($response['success']) && isset($response['failure'])) {
                    $successCount += $response['success'];
                    $failureCount += $response['failure'];
                }
            }
            
            return back()->with('success', "Notification sent! Delivered: $successCount, Failed: $failureCount");
            
        } catch (\Exception $e) {
            Log::error('Error sending notification: ' . $e->getMessage());
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }
    
    private function sendBatchNotification($tokens, $title, $body, $imageUrl = null, $clickUrl = null)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        $data = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'badge' => '1',
            ],
            'data' => [
                'url' => $clickUrl ?? '',
                'timestamp' => time()
            ]
        ];
        
        if ($imageUrl) {
            $data['notification']['image'] = $imageUrl;
        }
        
        // Add click_action for web
        $data['webpush'] = [
            'notification' => [
                'click_action' => $clickUrl ?? ''
            ]
        ];
        
        $response = Http::withHeaders([
            'Authorization' => 'key=' . $this->serverKey,
            'Content-Type' => 'application/json'
        ])->post($url, $data);
        
        $result = $response->json();
        
        // Check for invalid tokens and remove them
        if (isset($result['results']) && is_array($result['results'])) {
            foreach ($result['results'] as $index => $item) {
                if (isset($item['error']) && in_array($item['error'], [
                    'InvalidRegistration', 
                    'NotRegistered'
                ])) {
                    // Remove invalid token
                    FcmToken::where('token', $tokens[$index])->delete();
                }
            }
        }
        
        return [
            'success' => $result['success'] ?? 0,
            'failure' => $result['failure'] ?? 0
        ];
    }
}
