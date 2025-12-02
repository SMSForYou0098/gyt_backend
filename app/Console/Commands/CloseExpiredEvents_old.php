<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CloseExpiredEvents_old extends Command
{
    protected $signature = 'events:close-expired-old';
    protected $description = 'Close events and their tickets when end date is completed';

    public function handle()
    {
        $today = Carbon::today()->format('Y-m-d');

        $events = Event::where('status', 1)->get(); // only active events
      // Log::info('Active events count: ' . $events->count());

        foreach ($events as $event) {

            // handle: "2025-09-09,2025-10-11" OR "2025-09-15"
            $dates = explode(',', $event->date_range);
            $endDate = trim(end($dates)); // always get last date

           Log::info("Checking event ID: {$event->id} | End Date: {$endDate} | Today: {$today}");

            if ($today > $endDate) {

                $event->update(['status' => 0]);
                Ticket::where('event_id', $event->id)->update(['status' => 0]);

                Log::info("Expired → Event ID {$event->id} closed & tickets closed");
            }
        }

        $this->info("Expired events and their tickets updated successfully.");
    }
}
