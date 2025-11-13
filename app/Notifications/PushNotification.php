<?php

namespace App\Notifications;

use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $title;
    protected $body;
    protected $data;

    /**
     * Create a new notification instance.
     */
    public function __construct($title, $body, $data = [])
    {
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['firebase'];
    }

    /**
     * Send Firebase notification
     */
    public function toFirebase($notifiable)
    {
        $fcmService = new FirebaseNotificationService();
        return $fcmService->sendToUser($notifiable->id, $this->title, $this->body, $this->data);
    }
}