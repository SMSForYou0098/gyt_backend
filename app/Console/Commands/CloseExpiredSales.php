<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseExpiredSales extends Command
{
    protected $signature = 'app:close-expired-sales';
    protected $description = 'Close sales whose sale_date is past';

    public function handle()
    {
        $today = Carbon::today();
        $tickets = Ticket::where('sale', 1)->get();
        $count = 0;

        foreach ($tickets as $ticket) {
            if ($ticket->sale_date) {
                $dates = explode(',', $ticket->sale_date);
    
                $endDate = isset($dates[1]) ? $dates[1] : $dates[0];
    
                try {
                    $end = \Carbon\Carbon::parse(trim($endDate));
    
                    if ($end->lt($today)) {
                        $ticket->sale = 0;
                        $ticket->save();
                        $count++;
                    }
                } catch (\Exception $e) {
                    // Log::error("Invalid sale_date in ticket ID {$ticket->id}: " . $ticket->sale_date);
                }
            }
        }
    
        $this->info("Closed $count expired ticket sales.");
    }
}
