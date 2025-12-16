<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CloseExpiredEvents extends Command
{
    protected $signature = 'events:close-expired';
    protected $description = 'Close events and their tickets when end date is completed';

       public function handle()
    {
        $today = now()->toDateString();

        // Step 1: Close expired events based on the LAST date in date_range
        Event::where('status', 1)
            ->whereRaw("SUBSTRING_INDEX(date_range, ',', -1) < ?", [$today])
            ->update(['status' => 0]);

        // Step 2: Get all events that are closed today (status = 0)
        $expiredEvents = Event::where('status', 0)->pluck('id')->toArray();

        // Step 3: Close all tickets for expired events
        if (!empty($expiredEvents)) {
            Ticket::whereIn('event_id', $expiredEvents)->update(['status' => 0]);
        }

        $this->info("Expired events and tickets closed successfully.");
    }
}
