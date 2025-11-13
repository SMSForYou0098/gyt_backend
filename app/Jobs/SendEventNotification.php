<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WhatsappApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendEventNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = (object)$data;
    }

    public function handle(): void
    {
        $number = preg_replace('/[^0-9]/', '', $this->data->number);
        $modifiedNumber = strlen($number) === 10 ? '91' . $number : $number;
        $eventDay = $this->data->eventDay ?? 'today';

        $templateTitle = $eventDay == 'tomorrow' ? 'Tomorrow Event Notify' : 'Today Event Notify';

        $whatsappTemplate = WhatsappApi::where('title', $templateTitle)->first();
        $template = $whatsappTemplate->template_name ?? '';
        $mediaurl = $this->data->eventThumbnail;

        $admin = User::role('Admin', 'api')->with('whatsappConfig')->first();

        if (!$admin || !$admin->whatsappConfig || !$admin->whatsappConfig[0]->api_key) {
            Log::error('WhatsApp config missing');
            return;
        }

        $apiKey = $admin->whatsappConfig[0]->api_key;

        $value = [
            $this->data->name ?? '',
            'Baroda Premier League 2025',
             'Matches',
            $this->data->eventName1 ?? 'N/A',
            $this->data->eventName2 ?? 'N/A',
            'Please carry your valid entry pass and arrive 15 minutes early.'
        ];

        $params = [
            'apikey'    => $apiKey,
            'to'        => $modifiedNumber,
            'type'      => 'T',
            'tname'     => $template,
            'values'    => implode(',', $value),
            'media_url' => $mediaurl
        ];

        $url = "https://waba.smsforyou.biz/api/send-messages";

        Log::info('Queued WhatsApp Send Request', ['params' => $params]);

        try {
            $response = Http::get($url, $params);
            Log::info('WhatsApp API Response', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('WhatsApp Job Exception', ['message' => $e->getMessage()]);
        }
    }
}
