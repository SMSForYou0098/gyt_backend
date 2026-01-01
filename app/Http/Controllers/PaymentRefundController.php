<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Razorpay\Api\Api;

class PaymentRefundController extends Controller
{
    public function refund(Request $request)
    {
        $request->validate([
            'gateway' => 'required|in:easebuzz,razorpay,cashfree',
            'amount'  => 'required|numeric|min:1',
        ]);

        switch ($request->gateway) {

            case 'easebuzz':
                return $this->easebuzzRefund($request);

            case 'razorpay':
                return $this->razorpayRefund($request);

            case 'cashfree':
                return $this->cashfreeRefund($request);
        }
    }

    private function easebuzzRefund($request)
    {
        $key  = env('EASEBUZZ_KEY');
        $salt = env('EASEBUZZ_SALT');

        $hash = strtolower(hash(
            'sha512',
            $key . '|' . $request->txnid . '|' . $request->amount . '|' . $request->email . '|' . $request->phone . '|' . $salt
        ));

        $response = Http::asForm()->post('https://pay.easebuzz.in/refund/', [
            'key'    => $key,
            'txnid'  => $request->txnid,
            'amount' => $request->amount,
            'email'  => $request->email,
            'phone'  => $request->phone,
            'hash'   => $hash
        ]);

        return response()->json([
            'gateway' => 'easebuzz',
            'response' => $response->json()
        ]);
    }

    private function razorpayRefund($request)
    {
        $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

        $refund = $api->payment->fetch($request->payment_id)
            ->refund([
                'amount' => $request->amount * 100 // paise
            ]);

        return response()->json([
            'gateway' => 'razorpay',
            'response' => $refund
        ]);
    }

    private function cashfreeRefund($request)
    {
        $response = Http::withHeaders([
            'x-client-id'     => env('CASHFREE_APP_ID'),
            'x-client-secret' => env('CASHFREE_SECRET'),
            'x-api-version'   => '2022-09-01'
        ])->post(env('CASHFREE_REFUND_URL'), [
            'order_id' => $request->order_id,
            'refund_amount' => $request->amount,
            'refund_id' => uniqid('refund_')
        ]);

        return response()->json([
            'gateway' => 'cashfree',
            'response' => $response->json()
        ]);
    }
}
