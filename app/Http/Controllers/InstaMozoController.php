<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Instamojo;
use App\Services\BookingService;
use Illuminate\Support\Str;

class InstaMozoController extends Controller
{
    protected $api;

    protected $bookingService;
    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
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
        try {
            $instaMojoUrl = 'https://www.instamojo.com/api/1.1/payment-requests/';

            // Get API credentials based on organizer_id or fallback to Admin credentials
              $config = Instamojo::where('user_id', $request->organizer_id)
                ->first();

            if (! $config) {
                $adminId = User::role('Admin')->value('id');
                 $config = Instamojo::where('user_id', $adminId)->first();
            }


            // $config = Instamojo::where('user_id', $request->organizer_id)->first();
            // $adminConfig = Instamojo::where('user_id', User::role('Admin')->value('id'))->first();
            // $apiKey = $config->instamojo_api_key ?? $adminConfig->instamojo_api_key;
            // $authToken = $config->instamojo_auth_token ?? $adminConfig->instamojo_auth_token;

            $apiKey = $config->instamojo_api_key;
            $authToken = $config->instamojo_auth_token;

            // Generate transaction ID
            $txnid = random_int(100000000000, 999999999999);

            $categoryData = $request->category;
            $session = $this->generateEncryptedSessionId()['original'];
            $sessionId = $this->generateEncryptedSessionId()['encrypted'];

            $gateway = 'instamojo';
            $request->merge(['gateway' => $gateway]);

            // Prepare API request data
            $postData = [
                'purpose' => $request->productinfo ?? 'Ticket Booking',
                'amount' => number_format($request->amount, 2, '.', ''),
                'buyer_name' => $request->firstname,
                'email' => $request->email,
                'phone' => $request->phone,
              	'surl' => url('/api/payment-response/' . $gateway . '/' . $request->event_id . '/' . $sessionId . '?status=success&category=' . urlencode($categoryData)),
              	'furl' => url('/api/payment-response/' . $gateway . '/' . $request->event_id . '/' . $sessionId . '?status=failure&category=' . urlencode($categoryData)),
                'send_email' => 'False',
                'webhook' => rtrim(env('NAV_DOMAIN', 'https://cricket.getyourticket.in'), '/') . '/api/payment-webhook/instamojo/vod?sessionId=' . $session . '&category=' . urlencode($categoryData),

              	// 'webhook' => 'https://cricket.getyourticket.in/api/payment-webhook/instamojo/vod?sessionId=' . $session . '&category=' . urlencode($categoryData),
              	// 'webhook' => 'https://fronx.tasteofvadodara.in/api/payment-webhook/instamojo/vod?sessionId=' . $session . '&category=' . urlencode($categoryData),
                'allow_repeated_payments' => 'False',
                'redirect_url' => url('/api/payment-response/' . $gateway . '/' . $request->event_id . '/' . $session . '?status=success&category=' . urlencode($categoryData)),
            ];
           $response = Http::withHeaders([
               'X-Api-Key' => $apiKey,
                'X-Auth-Token' => $authToken
            ])->post($instaMojoUrl, $postData);


            $responseData = $response->json();
            if (!empty($responseData['success']) && $responseData['success'] == true) {
                $paymentUrl = $responseData['payment_request']['longurl'];

                 $bookingResult = $this->bookingService->storePendingBookings($request, $session, $txnid, $gateway);
               
                if ($bookingResult['status'] === true)  {
                    return response()->json(['status' => true,'result' => $responseData, 'txnid' => $txnid, 'url' => $paymentUrl]);
                } else {
                    return response()->json(['status' => false, 'message' => 'Payment Failed'], 400);
                }
            } else {
                return response()->json(['status' => false, 'message' => 'Payment request failed.']);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => false, 'message' => 'Configuration not found'], 404);
        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

}
