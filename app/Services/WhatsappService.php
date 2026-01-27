<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    public function send($data)
    {

        $number = preg_replace('/[^0-9]/', '', $data->number);
        $modifiedNumber = strlen($number) === 10 ? '91' . $number : $number;

        $template = $data->whatsappTemplateData ?? '';
    
        //  $mediaurl =  $data->mediaurl ?? '';
         $mediaurl =  $data->eventThumbnail ?? "https://cricket.getyourticket.in/uploads/thumbnail/688b2dfbc72ab_ff.jpg";
         $data->buttonValue = [$data->orderId, $data->insta_whts_url ?? 'helloinsta'];
        //  $data->buttonValue = [$data->shortLink, 'DMpaachCAVi/'];
        
        $admin = User::role('Admin', 'api')->with('whatsappConfig')->first();
        if ($admin && $admin->whatsappConfig && isset($admin->whatsappConfig[0])) {
            $apiKey = $admin->whatsappConfig[0]->api_key ?? null;
        } else {
            return ['error' => 'Admin WhatsApp configuration not found'];
        }

        if (!$apiKey) {
            return ['error' => 'API Key missing'];
        }

        // Template values
        $value = $data->values ?? [];
       

        $whatsappApi = "https://waba.smsforyou.biz/api/send-messages";
        $params = [
            'apikey'     => $apiKey,
            'to'         => $modifiedNumber,
            'type'       => 'T',
            'tname'      => $template,
            'values'     => implode(',', $value),
            // 'values'     => implode(',', $value),
            'media_url'  => $mediaurl,
            'button_value' => is_array($data->buttonValue) ? implode(',', $data->buttonValue) : ($data->buttonValue ?? ''),
        ];
        //Log::info('Sending WhatsApp Message', ['response' => $params]);
        $response = Http::get($whatsappApi, $params);
         Log::info('Sending WhatsApp Message', ['response' => $response->body()]);
        //return $response;
        return $response->successful()
            ? ['message' => 'WhatsApp sent successfully', 'url' => $whatsappApi . '?' . http_build_query($params)]
            : ['error' => 'Failed to send WhatsApp', 'details' => $response->body()];
    }
}
