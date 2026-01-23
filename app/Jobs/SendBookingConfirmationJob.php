<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\SmsService;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendBookingConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bookingId;
    protected $isMasterBooking;
    protected $masterBookingInnerCount;
    protected $orderId;

    /**
     * Create a new job instance.
     */
    public function __construct($bookingId, $orderId, $isMasterBooking = false, $masterBookingInnerCount = 1)
    {
        $this->bookingId = $bookingId;
        $this->orderId = $orderId;
        $this->isMasterBooking = $isMasterBooking;
        $this->masterBookingInnerCount = $masterBookingInnerCount;
    }

    /**
     * Execute the job.
     */
    public function handle(SmsService $smsService, WhatsappService $whatsappService)
    {
        try {
            $booking = Booking::findOrFail($this->bookingId);
            
            // Load required relationships
            $booking->load('ticket.event', 'user');

            // Format event date range
            $dates = explode(',', $booking->ticket->event->date_range);
            $formattedDates = [];
            foreach ($dates as $date) {
                $formattedDates[] = Carbon::parse($date)->format('d-m-Y');
            }
            $dateRangeFormatted = implode(' | ', $formattedDates);

            $eventDateTime = $dateRangeFormatted . ' | ' . $booking->ticket->event->start_time . ' - ' . $booking->ticket->event->end_time;
            $mediaurl = $booking->ticket->event->thumbnail;
            
            // Get WhatsApp template
            $whatsappTemplate = \App\Models\WhatsappApi::where('title', 'Online Booking')->first();
            $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

            // Calculate quantity: For master booking, use the count of inner bookings, otherwise use 1
            $quantity = $this->isMasterBooking ? $this->masterBookingInnerCount : 1;
            $shortLink = $this->isMasterBooking ? $this->orderId : $booking->token;
            $shortLinksms = "getyourticket.in/t/{$shortLink}";
            // Prepare notification data
            $data = (object) [
                'name' => $booking->name,
                'number' => $booking->number,
                'shortLink' => $shortLinksms,
                'templateName' => 'Online Booking Template',
                'whatsappTemplateData' => $whatsappTemplateName,
                'mediaurl' => $mediaurl,
                'values' => [
                    $booking->name,
                    $booking->number,
                    $booking->ticket->event->name,
                    $quantity,
                    $booking->ticket->name,
                    preg_replace('/\s+/', ' ', trim($booking->ticket->event->address)),
                    $eventDateTime,
                    $booking->ticket->event->whts_note ?? 'Thank you for your booking',
                ],
                'replacements' => [
                    ':C_Name' => $booking->name,
                    ':T_QTY' => $quantity,
                    ':S_Link'=> $shortLinksms,
                    ':Ticket_Name' => $booking->ticket->name,
                    ':Event_Name' => $booking->ticket->event->name,
                    ':Event_DateTime' => $eventDateTime,
                    ':Message' => 'Your booking has been approved and confirmed!',
                ]
            ];

            // Send SMS and WhatsApp notifications
            $smsResponse = $smsService->send($data);
            $whatsappResponse = $whatsappService->send($data);

            Log::info('Booking confirmation sent successfully', [
                'booking_id' => $booking->id,
                'is_master_booking' => $this->isMasterBooking,
                'quantity' => $quantity,
                'sms_response' => $smsResponse,
                'whatsapp_response' => $whatsappResponse,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending booking confirmation in job', [
                'booking_id' => $this->bookingId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Retry the job on failure
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('SendBookingConfirmationJob failed', [
            'booking_id' => $this->bookingId,
            'error' => $exception->getMessage(),
        ]);
    }
}
