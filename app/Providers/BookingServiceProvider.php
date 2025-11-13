<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Booking;
use App\Models\Agent;
use App\Models\ExhibitionBooking;
use App\Models\AmusementBooking;
use App\Models\ComplimentaryBookings;
use App\Models\PosBooking;
use App\Models\MasterBooking;
use App\Models\AmusementMasterBooking;
use App\Models\AgentMaster;

class BookingServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('BookingService', function ($app) {
            return new class {
                public function getBookingsByOrderId($orderId)
                {
                    return [
                        'Booking' => Booking::where('token', $orderId)->with(['ticket.event.user', 'attendee'])->first(),
                        'AgentBooking' => Agent::where('token', $orderId)->with(['ticket.event.user', 'attendee'])->first(),
                        'ExhibitionBooking' => ExhibitionBooking::where('token', $orderId)->with(['ticket.event.user', 'attendee'])->first(),
                        'AmusementBooking' => AmusementBooking::where('token', $orderId)->with(['ticket.event.user', 'attendee'])->first(),
                        'ComplimentaryBookings' => ComplimentaryBookings::where('token', $orderId)->with('ticket.event.user')->first(),
                        'PosBooking' => PosBooking::where('token', $orderId)->with('ticket.event.user')->first(),
                        'MasterBooking' => MasterBooking::where('order_id', $orderId)->first(),
                        'AmusementMasterBooking' => AmusementMasterBooking::where('order_id', $orderId)->first(),
                        'AgentMasterBooking' => AgentMaster::where('order_id', $orderId)->first(),
                    ];
                }
            };
        });
    }

    public function boot()
    {
        // No additional setup required
    }
}

