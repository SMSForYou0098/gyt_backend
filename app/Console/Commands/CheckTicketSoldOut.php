<?php

namespace App\Console\Commands;

use App\Models\Agent;
use Illuminate\Console\Command;
use App\Models\Ticket;
use App\Models\PosBooking;
use App\Models\Booking;
use App\Models\Event;

class CheckTicketSoldOut extends Command
{
    protected $signature = 'tickets:check-soldout';
    protected $description = 'Check if tickets are sold out based on total bookings from all sources';

    public function handle()
    {
        $tickets = Ticket::all();

        foreach ($tickets as $ticket) {
            $ticketQty = $ticket->quantity;

            $onlineCount = Booking::where('ticket_id', $ticket->id)->sum('quantity');
            $posCount = PosBooking::where('ticket_id', $ticket->id)->sum('quantity');
            $agentCount = Agent::where('ticket_id', $ticket->id)->sum('quantity');

            $totalBooked = $onlineCount + $posCount + $agentCount;

            $ticket->sold_out = $totalBooked >= $ticketQty ? 1 : 0;
            $ticket->save();
        }

        $eventIds = Ticket::distinct()->pluck('event_id');

        foreach ($eventIds as $eventId) {
            $eventTickets = Ticket::where('event_id', $eventId)->get();

            $allSoldOut = $eventTickets->every(function ($ticket) {
                return $ticket->sold_out == 1;
            });

            $event = Event::find($eventId);
            if ($event) {
                $event->sold_out = $allSoldOut ? 1 : 0;
                $event->save();
            }
        }

        $this->info('Tickets and event statuses updated based on booking status.');
    }

    // public function handle()
    // {
    //     $tickets = Ticket::all();

    //     foreach ($tickets as $ticket) {
    //         $ticketId = $ticket->id;
    //         $ticketQty = $ticket->quantity;

    //         // Total bookings from 4 sources
    //         $onlineCount = Booking::where('ticket_id', $ticketId)->sum('quantity');
    //         $posCount = PosBooking::where('ticket_id', $ticketId)->sum('quantity');
    //         $agentCount = Agent::where('ticket_id', $ticketId)->sum('quantity');

    //         $totalBooked = $onlineCount + $posCount + $agentCount;

    //         if ($totalBooked >= $ticketQty) {
    //             $ticket->sold_out = 1;
    //             $ticket->save();

    //             if ($ticket->event_id) {
    //                 $event = Event::find($ticket->event_id);
    //                 if ($event) {
    //                     $event->sold_out = 1;
    //                     $event->save();
    //                 }
    //             }
                
    //         } else {
    //             $ticket->sold_out = 0;
    //             $ticket->save();
    //         }

    //     }

    //     $this->info('Tickets checked and updated for sold out status.');
    // }
}
