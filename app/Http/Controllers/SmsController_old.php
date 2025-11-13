<?php

namespace App\Http\Controllers;

use App\Models\SmsConfig;
use App\Models\SmsCustomApi;
use App\Models\SmsTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SmsController extends Controller
{
    public function index($id)
    {
        $SmsConfig = SmsConfig::where('user_id', $id)->first();
        $Custom = SmsCustomApi::where('user_id', $id)->first();
        $templates = SmsTemplate::where('user_id', $id)->get();
        return response()->json(['status' => true, 'config' => $SmsConfig, 'custom' => $Custom, 'templates' => $templates], 200);
    }

    public function DefaultApi(Request $request)
    {
        try {
            $sms = SmsConfig::firstOrNew(['user_id' => $request->user_id]);
            $sms->user_id = $request->user_id;
            $sms->api_key = $request->api_key;
            $sms->sender_id = $request->sender_id;
            $sms->status = $request->status;
            $sms->save();
            $this->UserSmsUpdate($request->user_id, $request->sms);
            return response()->json(['status' => true, 'message' => 'SMS Configuration saved successfully.'], 200);
        } catch (\Exception $e) {
            // Handle errors
            return response()->json(['status' => false, 'error' => 'Failed to save SMS Configuration.', 'message' => $e->getMessage()], 500);
        }
    }
    public function CustomApi(Request $request)
    {
        try {
            $sms = SmsCustomApi::firstOrNew(['user_id' => $request->user_id]);
            $sms->user_id = $request->user_id;
            $sms->url = $request->url;
            $sms->save();
            $this->UserSmsUpdate($request->user_id, $request->sms);
            return response()->json(['status' => true, 'message' => 'Custom Api saved successfully.'], 200);
        } catch (\Exception $e) {
            // Handle errors
            return response()->json(['status' => false, 'error' => 'Failed to save Custom Api.', 'message' => $e->getMessage()], 500);
        }
    }
    public function UserSmsUpdate($id, $status)
    {
        try {
            $user = User::findOrFail($id);
            $user->sms = $status;
            $user->save();
            //return response()->json(['status' => true, 'message' => 'Custom Api saved successfully.'], 200);
        } catch (\Exception $e) {
            // Handle errors
            return response()->json(['status' => false, 'error' => 'Failed to save Custom Api.', 'message' => $e->getMessage()], 500);
        }
    }

    // Save the settings
    public function store(Request $request)
    {
        try {
            // Retrieve existing SMS configuration or create a new instance
            $template = new SmsTemplate();
            // Update SMS configuration with request data
            $template->user_id = $request->input('user_id');
            $template->template_id = $request->input('template_id');
            $template->template_name = $request->input('template_name');
            $template->content = $request->input('content');
            $template->status = $request->input('status');

            // Save the SMS configuration
            $template->save();

            return response()->json(['status' => true, 'message' => 'SMS template saved successfully.'], 200);
        } catch (\Exception $e) {
            // Handle errors
            return response()->json(['status' => false, 'error' => 'Failed to save SMS configuration.', 'message' => $e->getMessage()], 500);
        }
    }
    public function update(Request $request, $id)
    {
        try {
            $template = SmsTemplate::findOrFail($id);
            $template->user_id = $request->input('user_id');
            $template->template_id = $request->input('template_id');
            $template->template_name = $request->input('template_name');
            $template->content = $request->input('content');
            $template->status = $request->input('status');
            // Save the SMS configuration
            $template->save();

            return response()->json(['status' => true, 'message' => 'SMS template updated successfully.'], 200);
        } catch (\Exception $e) {
            // Handle errors
            return response()->json(['status' => false, 'error' => 'Failed to save SMS configuration.', 'message' => $e->getMessage()], 500);
        }
    }

    public function sendSms(Request $request)
    {
        $request->validate([
            'number' => 'required|string',
            'api_key' => 'nullable|string',
            'sender_id' => 'nullable|string',
            'config_status' => 'required|string',
        ]);

        $number = $request->input('number');
              // Ensure the number is a string
        $number = (string) $number;

        // Get the length of the number
        $length = strlen($number);

        // Extract the last 10 digits
        if ($length > 10) {
            $number = substr($number, -10);
        }
        // $message = $request->input('message');
        $name = $request->input('name');
        $config_status = $request->input('config_status');

        $qty = $request->qty;
        $ticketName = $request->ticketName;
        $eventName = $request->eventName;

        // Function to truncate the event name (similar to JavaScript function)
        function truncateString($string, $length = 9)
        {
            return strlen($string) > $length ? substr($string, 0, $length) . '...' : $string;
        }
        $message = "Booking Confirmed!\n" .
            "Hey {$name}\n" .
            "QTY {$qty}\n" .
            "Type {$ticketName}\n" .
            "Download E-tickets for " . truncateString($eventName) . " by login using {$number}\n" .
            "at https://bsn.ltd/SMSFrU/c/sevak" .
            "Sevak Trust\n" .
            "Get Your Ticket\n" .
            "SMS4U";

        if ($config_status === "0") {
            $admin = User::role('admin')->with('smsConfig')->first();
            if ($admin && $admin->smsConfig) {
                $smsConfig = $admin->smsConfig[0];
                $api_key = $smsConfig->api_key;
                $sender_id = $smsConfig->sender_id;
            } else {
                return response()->json(['error' => 'Admin SMS configuration not found'], 500);
            }
        } else {
            $request->validate([
                'api_key' => 'required|string',
                'sender_id' => 'required|string',
            ]);
            $api_key = $request->input('api_key');
            $sender_id = $request->input('sender_id');
        }
        //return response()->json(['api_key' => $api_key ,'sender_id'=>$sender_id], 200);

        $otpApi = "https://login.smsforyou.biz/V2/http-api.php";
        $params = [
            'apikey' => $api_key,
            'senderid' => $sender_id,
            'number' => $number,
            'message' => $message,
            'format' => 'json',
            'template_id' => '1407172441533775961'
        ];
        $response = Http::get($otpApi, $params);
        $fullUrl = $otpApi . '?' . http_build_query($params);
        if ($response->successful()) {
            return response()->json(['message' => 'SMS sent successfully', 'fullUrl' => $fullUrl], 200);
        } else {
            return response()->json(['error' => 'Failed to send SMS'], 500);
        }
    }


}
