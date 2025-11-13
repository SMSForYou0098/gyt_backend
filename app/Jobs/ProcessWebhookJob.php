<?php

namespace App\Jobs;

use App\Services\WebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $gateway;
    protected $data;

    public function __construct($gateway, $data)
    {
        $this->gateway = $gateway;
        $this->data = $data;
    }

    public function handle(): void
    {
        try {
            app(WebhookProcessor::class)->process($this->gateway, $this->data);
        } catch (\Exception $e) {
            Log::error("Webhook job failed for {$this->gateway}: " . $e->getMessage(), [
                'data' => $this->data
            ]);
        }
    }
}
