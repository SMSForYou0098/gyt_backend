<?php

namespace App\Channels;

use App\Services\FirebaseNotificationService;
use Illuminate\Notifications\Notification;

class FirebaseChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        return $notification->toFirebase($notifiable);
    }
}