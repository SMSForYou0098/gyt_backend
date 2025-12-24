<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Booking;

class RefundBookingMail extends Mailable
{
    use Queueable, SerializesModels;

    public $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function build()
    {
        return $this->from('refund@getyourticket.in', 'Get Your Ticket')
            ->subject('Refund Initiated - ' . $this->booking->ticket->event->name)
            ->view('email.refund_booking');
    }
}
