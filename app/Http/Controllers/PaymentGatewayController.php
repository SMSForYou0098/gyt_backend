<?php

namespace App\Http\Controllers;

use App\Models\CashfreeConfig;
use Easebuzz\PayWithEasebuzzLaravel\PayWithEasebuzzLib;
use Illuminate\Http\Request;
use App\Models\EasebuzzConfig;
use App\Models\Razorpay;
use App\Models\Instamojo;
use App\Models\Paytm;
use App\Models\Stripe;
use App\Models\PayPal;
use App\Models\PhonePe;

class PaymentGatewayController extends Controller
{
    public function getPaymentGateways($id)
    {
        $gateways = [
            'razorpay' => Razorpay::where('user_id', $id)->first(),
            'instamojo' => Instamojo::where('user_id', $id)->first(),
            'easebuzz' => EasebuzzConfig::where('user_id', $id)->first(),
            'paytm' => Paytm::where('user_id', $id)->first(),
            'stripe' => Stripe::where('user_id', $id)->first(),
            'paypal' => PayPal::where('user_id', $id)->first(),
            'phonepe' => PhonePe::where('user_id', $id)->first(),
            'cashfree' => CashfreeConfig::where('user_id', $id)->first(),
        ];

        return response()->json(['gateways' => $gateways],200);
    }
    // Store or update Razorpay credentials
    public function storeRazorpay(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'razorpay_key' => 'required|string|max:255',
            'razorpay_secret' => 'required|string|max:255',
        ]);

        $razorpay = Razorpay::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'razorpay_key' => $request->razorpay_key,
                'razorpay_secret' => $request->razorpay_secret,
                'status' => $request->status,
            ]
        );

        return response()->json(['message' => 'Razorpay credentials stored successfully', 'data' => $razorpay], 201);
    }

    // Store or update Instamojo credentials
    public function storeInstamojo(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'instamojo_api_key' => 'required|string|max:255',
            'instamojo_auth_token' => 'required|string|max:255',
        ]);

        $instamojo = Instamojo::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'instamojo_api_key' => $request->instamojo_api_key,
                'instamojo_auth_token' => $request->instamojo_auth_token,
                'status' => $request->status,
            ]
        );

        return response()->json(['message' => 'Instamojo credentials stored successfully', 'data' => $instamojo], 201);
    }

    // Store or update Easebuzz credentials
    public function storeEasebuzz(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'easebuzz_salt' => 'required|string|max:255',
            'easebuzz_key' => 'required|string|max:255',
        ]);

        $easebuzz = EasebuzzConfig::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'merchant_key' => $request->easebuzz_key,
                'salt' => $request->easebuzz_salt,
                'env' => $request->env,
                'prod_url' => $request->prod_url,
                'test_url' => $request->test_url,
                'status' => $request->status,
            ]
        );

        return response()->json(['status'=> true,'message' => 'Easebuzz credentials stored successfully', 'data' => $easebuzz], 201);
    }

    // Store or update Paytm credentials
    public function storePaytm(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'merchant_id' => 'required|string|max:255',
            'merchant_key' => 'required|string|max:255',
            'merchant_website' => 'required|string|max:255',
            'industry_type' => 'required|string|max:255',
            'channel' => 'required|string|max:255',
        ]);

        $paytm = Paytm::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'merchant_id' => $request->merchant_id,
                'merchant_key' => $request->merchant_key,
                'merchant_website' => $request->merchant_website,
                'industry_type' => $request->industry_type,
                'channel' => $request->channel,
            ]
        );

        return response()->json(['message' => 'Paytm credentials stored successfully', 'data' => $paytm], 201);
    }

    // Store or update Stripe credentials
    public function storeStripe(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'stripe_key' => 'required|string|max:255',
            'stripe_secret' => 'required|string|max:255',
        ]);

        $stripe = Stripe::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'stripe_key' => $request->stripe_key,
                'stripe_secret' => $request->stripe_secret,
            ]
        );

        return response()->json(['message' => 'Stripe credentials stored successfully', 'data' => $stripe], 201);
    }

    // Store or update PayPal credentials
    public function storePayPal(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'client_id' => 'required|string|max:255',
            'secret' => 'required|string|max:255',
        ]);

        $paypal = PayPal::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'client_id' => $request->client_id,
                'secret' => $request->secret,
            ]
        );

        return response()->json(['message' => 'PayPal credentials stored successfully', 'data' => $paypal], 201);
    }

    // Store or update PhonePe credentials
	public function storePhonePe(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            // Assuming fields for PhonePe
            'client_id' => 'required|string|max:255',
            'secret' => 'required|string|max:255',
        ]);
 
        $phonepe = PhonePe::updateOrCreate(
            ['user_id' => $request->user_id],
            [
              	'status' => $request->status,
                'client_id' => $request->client_id,
                'secret' => $request->secret,
            ]
        );
 
        return response()->json(['message' => 'PhonePe credentials stored successfully', 'data' => $phonepe], 201);
    }

    // Store or update PhonePe storeCashfree
	public function storeCashfree(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            // Assuming fields for Cashfree
            'app_id' => 'required|string|max:255',
            'secret_key' => 'required|string|max:255',
        ]);
 
        $Cashfree = CashfreeConfig::updateOrCreate(
            ['user_id' => $request->user_id],
            [
              	'status' => $request->status,
                'app_id' => $request->app_id,
                'secret_key' => $request->secret_key,
                'env' => $request->env,
            ]
        );
 
        return response()->json(['message' => 'cashfree credentials stored successfully', 'data' => $Cashfree], 201);
    }

}
