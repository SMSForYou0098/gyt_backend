<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\WhatsappApi;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class SendRefundWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bookingId;

    public function __construct($bookingId)
    {
        $this->bookingId = $bookingId;
    }

    public function handle(WhatsappService $whatsappService)
    {
        $booking = Booking::withTrashed()->find($this->bookingId);
        if (!$booking) {
            return;
        }

        $ticket = $booking->ticket;
        $event  = $ticket->event;

        $whatsappTemplate = WhatsappApi::where('title', 'refund confirmation')->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $socialMessage = 'Follow Us on Social Media for Future Event Updates and Offers. WhatsApp: wa.gyt.co.in | Facebook: fb.gyt.co.in';

        $dates = explode(',', $event->date_range);
        $formattedDates = [];
        foreach ($dates as $date) {
            $formattedDates[] = Carbon::parse($date)->format('d-m-Y');
        }

        $data = (object)[
            'name' => $booking->name,
            'number' => $booking->number,
            'email' => $booking->email,
            'templateName' => 'Refund Notification Template',
            'whatsappTemplateData' => $whatsappTemplateName,
            'shortLink' => $booking->token,
            'insta_whts_url' => $event->insta_whts_url ?? '',
            'mediaurl' => $event->thumbnail,
            'values' => [
                $booking->name ?? 'Guest',
                $event->name ?? 'Event',
                $socialMessage,
            ],
            'replacements' => [
                ':C_Name' => $booking->name,
                ':Event_Name' => $event->name,
                ':Event_Description' => $socialMessage,
            ]
        ];

        $whatsappService->send($data);
    }
}
