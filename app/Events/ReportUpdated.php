<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    public $report;

    public function __construct($report)
    {
        // print_r($report[0]['id']);exit;
        // \Log::info('Report created event dispatched: '.  $report[0]['id']);
        $this->reportId = $report[0]['id'];
    }

    public function broadcastOn()
    {
        return ['reports'];
    }

    public function broadcastAs()
    {
        return 'new-report';
    }
}
