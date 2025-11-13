<?php

namespace App\Console\Commands;

use App\Models\AccreditationBooking;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\ComplimentaryBookings;
use App\Models\PosBooking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetBookingStatus extends Command
{

    protected $signature = 'bookings:reset-status';
    protected $description = 'Reset the status of bookings updated yesterday with type season';


    public function handle()
    {
        // Get bookings that were updated yesterday and have type "season"
        $bookings = Booking::where('type', 'season')->get();

        foreach ($bookings as $booking) {
            $booking->status = 0;
            $booking->save();
        }

        $generatedBookings = ComplimentaryBookings::where('type', 'generated')->get();
        foreach ($generatedBookings as $compBooking) {
            $compBooking->status = 0;
            $compBooking->save();
        }

        $agentBookings = Agent::where('type', 'season')->get();
        foreach ($agentBookings as $agentdata) {
            $agentdata->status = 0;
            $agentdata->save();
        }

        $AccreditationBooking = AccreditationBooking::where('type', 'season')->get();
        foreach ($AccreditationBooking as $accreditationdata) {
            $accreditationdata->status = 0;
            $accreditationdata->save();
        }

        $posBookings = PosBooking::with(['ticketData.eventData'])->get();

        foreach ($posBookings as $posData) {
            $ticket = $posData->ticketData;
            $event = $ticket ? $ticket->eventData : null;

            if ($event && $event->event_type === 'season') {
                $posData->status = 0;
                $posData->save();
            }
        }



        $message = 'Booking statuses reset: ' . count($bookings) . ' season bookings, ' . count($generatedBookings) . ' complimentary bookings.';
        $this->info($message);
        Log::info('[ResetBookingStatus] ' . $message);
        $this->info('Booking statuses have been reset successfully.');
    }
}
