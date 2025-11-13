<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PenddingBooking;
use App\Services\BookingService;
use Illuminate\Support\Str;

class CashfreeController extends Controller
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    private function generateEncryptedSessionId()
    {
        $originalSessionId = Str::random(32);
        $encryptedSessionId = encrypt($originalSessionId);

        return [
            'original' => $originalSessionId,
            'encrypted' => $encryptedSessionId
        ];
    }

    /**
     * Step 1: Initiate Payment
     */
    public function initiatePayment(Request $request)
    {
        $request->validate([
            'firstname' => 'required|string',
            'email'     => 'required|email',
            'phone'     => 'required|digits:10',
            'amount'    => 'required|numeric|min:1',
        ]);

        // Generate unique IDs
        $gateway  = 'cashfree';
        $session  = $this->generateEncryptedSessionId()['original'];
        $categoryData = $request->category;
        $orderId    = 'order_' . rand(1111111111, 9999999999);
        $customerId = 'customer_' . rand(111111111, 999999999);
        $txnid      = random_int(100000000000, 999999999999);

        $url = env('CASHFREE_ENV') === 'sandbox'
            ? "https://sandbox.cashfree.com/pg/orders"
            : "https://api.cashfree.com/pg/orders";

        $headers = [
            "Content-Type: application/json",
            "x-api-version: 2022-01-01",
            "x-client-id: " . env('CASHFREE_API_KEY'),
            "x-client-secret: " . env('CASHFREE_API_SECRET'),
        ];

        $payload = [
            'order_id'        => $session,
            'order_amount'    => $request->amount,
            "order_currency"  => "INR",
            "customer_details" => [
                "customer_id"   => $customerId,
                "customer_name" => $request->firstname,
                "customer_email" => $request->email,
                "customer_phone" => $request->phone,
            ],
            "order_meta" => [
                "return_url" => url('/api/payment-response/' . $gateway . '/' . $request->event_id . '/' . $session . '?status=success&category=' . urlencode($categoryData)),
            ],

        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return response()->json(['status' => false, 'message' => $err], 500);
        }

        $responseData = json_decode($response);

        // Save pending booking with order_id


        $this->bookingService->storePendingBookings(
            $request,
            $session,
            $txnid,
            $gateway,
            $orderId // store order_id so we can match later
        );

        if (isset($responseData->payment_link)) {
            return response()->json([
                'status' => true,
                'url'    => $responseData->payment_link,
            ]);
        }


        return response()->json([
            'status'  => false,
            'message' => 'Payment link not found',
            'data'    => $responseData,
        ], 400);
    }
}
