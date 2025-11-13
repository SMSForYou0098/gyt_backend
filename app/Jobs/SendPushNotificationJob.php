<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\FirebaseNotificationService;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tokens;
    protected $title;
    protected $body;
    protected $url;
    protected $image;
    protected $data;

    public function __construct(array $tokens, string $title, string $body, string $url = '', string $image = '', array $data = [])
    {
        $this->tokens = $tokens;
        $this->title = $title;
        $this->body = $body;
        $this->url = $url;
        $this->image = $image;
        $this->data = $data;
    }

    public function handle()
    {
        $fcmService = new FirebaseNotificationService();
        return $fcmService->sendToMultipleDevices(
            $this->tokens,
            $this->title,
            $this->body,
            $this->url,
            $this->image,
            $this->data
        );
    }
}
