<?php

namespace App\Services;

use App\Models\User;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send($data)
    {

        $number = $data->number;
        $templateName = $data->templateName;
        $config_status = "0";

        $api_key = null;
        $sender_id = null;

        // Step 1: Get the SMS template
        $templateData = SmsTemplate::where('template_name', $templateName)->first();
        if (!$templateData) {
            return ['error' => 'Template not found'];
        }

        $templateID = $templateData->template_id;
        $messages = $templateData->content;

        // Step 2: Replace placeholders
        $finalMessage = strtr($messages, $data->replacements ?? []);



        // Step 3: Get admin SMS config if required
        if ($config_status === "0") {
            $admin = User::role('Admin', 'api')->with('smsConfig')->first();
            if ($admin && $admin->smsConfig && count($admin->smsConfig) > 0) {
                $smsConfig = $admin->smsConfig[0];
                $api_key = $smsConfig->api_key;
                $sender_id = $smsConfig->sender_id;
            } else {
                return ['error' => 'Admin SMS configuration not found'];
            }
        }

        if (!$api_key || !$sender_id) {
            return ['error' => 'API key or Sender ID missing'];
        }

        // Step 4: Send SMS
        $otpApi = "https://login.smsforyou.biz/V2/http-api.php";
        $params = [
            'apikey'      => $api_key,
            'senderid'    => $sender_id,
            'number'      => $number,
            'message'     => $finalMessage,
            'format'      => 'json',
            'template_id' => $templateID,
        ];
        // Log::info('Sending sms Message', ['response' => $params]);
        $response = Http::get($otpApi, $params);
        // Log::info('Sending sms Message', ['response' => $response->body()]);

        return $response->successful()
            ? ['message' => 'SMS sent successfully', 'url' => $otpApi . '?' . http_build_query($params)]
            : ['error' => 'Failed to send SMS'];
    }
}
