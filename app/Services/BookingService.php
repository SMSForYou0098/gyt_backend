<?php

namespace App\Services;

use App\Models\PenddingBooking;
use App\Models\PenddingBookingsMaster;
use App\Models\Booking;
use App\Models\MasterBooking;
use App\Models\AmusementPendingBooking;
use App\Models\AmusementPendingMasterBooking;
use App\Models\AmusementBooking;
use App\Models\AmusementMasterBooking;
use App\Models\Ticket;
use App\Models\Promocode;
use App\Models\WhatsappApi;
use App\Services\SmsService;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;
use Exception;

class BookingService
{
    protected $smsService;
    protected $whatsappService;

    public function __construct(SmsService $smsService = null, WhatsappService $whatsappService = null)
    {
        $this->smsService = $smsService;
        $this->whatsappService = $whatsappService;
    }
    /**
     * Store pending bookings
     *
     * @param object $request The request object containing booking data
     * @param string $session The session ID
     * @param string $txnid The transaction ID
     * @return array
     */
    public function storePendingBookings($request, $session, $txnid, $gateway = 'unknown')
    {
        try {
            $requestData = json_decode($request->requestData);

            if (!$requestData) {
                throw new Exception('Invalid request data - JSON decode failed');
            }

            $qty = $requestData->tickets->quantity ?? 0;
            $bookings = [];
            $masterBookingData = [];
            $firstIteration = true;
            $penddingBookingsMaster = null;

            if ($qty > 0) {
                for ($i = 0; $i < $qty; $i++) {
                    $booking = $this->createPendingBooking(
                        $requestData,
                        $gateway,
                        $request,
                        $session,
                        $txnid,
                        $i,
                        $firstIteration
                    );

                    if ($booking) {
                        $bookings[] = $booking;
                        $masterBookingData[] = $booking->id;
                        $firstIteration = false;
                    }
                }

                // Create master booking if multiple bookings
                if (count($bookings) > 1) {
                    $penddingBookingsMaster = $this->createPendingMasterBooking(
                        $masterBookingData,
                        $session,
                        $requestData,
                        $request
                    );
                }
            }

            return [
                'status' => true,
                'message' => 'Tickets Booked Successfully',
                'bookings' => $bookings,
                'master_booking' => $penddingBookingsMaster,
                'booking_count' => count($bookings)
            ];

        } catch (Exception $e) {
            Log::error('BookingService - Store Pending Bookings Error: ' . $e->getMessage(), [
                'session' => $session,
                'txnid' => $txnid,
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return [
                'status' => false,
                'message' => 'Failed to book tickets',
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ];
        }
    }

    /**
     * Create a single pending booking
     *
     * @param object $requestData
     * @param object $request
     * @param string $session
     * @param string $txnid
     * @param int $index
     * @param bool $firstIteration
     * @return PenddingBooking|null
     */
    private function createPendingBooking($requestData, $gateway, $request, $session, $txnid, $index, $firstIteration)
    {
        try {
            $booking = new PenddingBooking();

            // Basic booking data
            $booking->ticket_id = $requestData->tickets->id;
            $booking->batch_id = Ticket::where('id', $requestData->tickets->id)->value('batch_id');
            $booking->user_id = $requestData->user_id;
            $booking->email = $requestData->email;
            $booking->name = $requestData->name;
            $booking->number = $requestData->number;
            $booking->type = $requestData->type;
            $booking->payment_method = $requestData->payment_method;
            $booking->gateway = $gateway;

            // Generate token
            $booking->token = $this->generateHexadecimalCode();
            $booking->session_id = $session;
          	$booking->inf_id = $request->inf_id ?? null;

            $booking->promocode_id = $request->promo_code ?? null;
            $booking->txnid = $txnid;
            $booking->status = 0;
            $booking->payment_status = 0;
            $booking->attendee_id = $request->attendees[$index]['id'] ?? null;
            $booking->total_tax = $request->total_tax ?? null;

            // Amount details (only for first booking to avoid duplication)
            if ($firstIteration) {
                $booking->amount = $request->amount > 0 ? $request->amount : 0;
                $booking->discount = $request->discount ?? null;
                $booking->base_amount = $request->base_amount ?? null;
                $booking->convenience_fee = $request->convenience_fee ?? null;
            }

            $booking->save();

            // Load relationships
            $booking->load(['user', 'ticket.event.user.smsConfig']);

            Log::info('Pending booking created successfully', [
                'booking_id' => $booking->id,
                'ticket_id' => $booking->ticket_id,
                'session' => $session
            ]);

            return $booking;

        } catch (Exception $e) {
            Log::error('BookingService - Create Pending Booking Error: ' . $e->getMessage(), [
                'index' => $index,
                'session' => $session,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create pending master booking
     *
     * @param array $masterBookingData
     * @param string $session
     * @param object $requestData
     * @param object $request
     * @return PenddingBookingsMaster|null
     */
    private function createPendingMasterBooking($masterBookingData, $session, $requestData, $request)
    {
        try {
            $penddingBookingsMaster = new PenddingBookingsMaster();

            $penddingBookingsMaster->booking_id = $masterBookingData;
            $penddingBookingsMaster->session_id = $session;
            $penddingBookingsMaster->user_id = $requestData->user_id;
          	$penddingBookingsMaster->inf_id = $requestData->inf_id ?? null;

            $penddingBookingsMaster->amount = $request->amount ?? 0;
            $penddingBookingsMaster->gateway = $request->gateway ?? 'unknown';
            $penddingBookingsMaster->order_id = $this->generateHexadecimalCode();
            $penddingBookingsMaster->discount = $request->discount ?? null;
            $penddingBookingsMaster->payment_method = $request->payment_method ?? null;

            $penddingBookingsMaster->save();

            Log::info('Pending master booking created successfully', [
                'master_booking_id' => $penddingBookingsMaster->id,
                'booking_count' => count($masterBookingData),
                'session' => $session
            ]);

            return $penddingBookingsMaster;

        } catch (Exception $e) {
            Log::error('BookingService - Create Pending Master Booking Error: ' . $e->getMessage(), [
                'session' => $session,
                'booking_ids' => $masterBookingData
            ]);
            return null;
        }
    }

    /**
     * Generate hexadecimal code
     *
     * @param int $length
     * @return string
     */
    private function generateHexadecimalCode($length = 8)
    {
        $characters = '0123456789ABCDEF';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    /**
     * Get booking statistics
     *
     * @param string $session
     * @return array
     */
    public function getBookingStats($session)
    {
        try {
            $bookingsCount = PenddingBooking::where('session_id', $session)->count();
            $masterBookingsCount = PenddingBookingsMaster::where('session_id', $session)->count();

            return [
                'status' => true,
                'session_id' => $session,
                'pending_bookings_count' => $bookingsCount,
                'master_bookings_count' => $masterBookingsCount,
                'has_master_booking' => $masterBookingsCount > 0
            ];

        } catch (Exception $e) {
            Log::error('BookingService - Get Booking Stats Error: ' . $e->getMessage());

            return [
                'status' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel pending bookings by session
     *
     * @param string $session
     * @return array
     */
    public function cancelPendingBookings($session)
    {
        try {
            $bookingsDeleted = PenddingBooking::where('session_id', $session)->delete();
            $masterBookingsDeleted = PenddingBookingsMaster::where('session_id', $session)->delete();

            Log::info('Pending bookings cancelled', [
                'session' => $session,
                'bookings_deleted' => $bookingsDeleted,
                'master_bookings_deleted' => $masterBookingsDeleted
            ]);

            return [
                'status' => true,
                'message' => 'Pending bookings cancelled successfully',
                'bookings_deleted' => $bookingsDeleted,
                'master_bookings_deleted' => $masterBookingsDeleted
            ];

        } catch (Exception $e) {
            Log::error('BookingService - Cancel Pending Bookings Error: ' . $e->getMessage());

            return [
                'status' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Transfer event bookings from pending to confirmed
     *
     * @param string $sessionId
     * @param string $status
     * @param string $paymentId
     * @return array
     */
    public function transferEventBooking($sessionId, $status, $paymentId)
    {
        try {
            $bookings = PenddingBooking::where('session_id', $sessionId)->with('ticket.event')->get();
            $bookingMaster = PenddingBookingsMaster::where('session_id', $sessionId)->get();

            if ($bookings->isEmpty()) {
                return [
                    'status' => false,
                    'message' => 'No pending bookings found for session: ' . $sessionId
                ];
            }

            // Prepare notification data
            $notificationData = $this->prepareEventNotificationData($bookings);
            $masterBookingIDs = [];

            // Process individual bookings
            foreach ($bookings as $individualBooking) {
                if ($status === 'success') {
                    $booking = $this->createConfirmedEventBooking($individualBooking, $paymentId);
                    if ($booking) {
                        $masterBookingIDs[] = $booking->id;
                        $individualBooking->delete();
                    }
                } else {
                    $this->updatePendingBookingStatus($individualBooking, $status, $paymentId);
                }
            }

            // Process master booking if exists
            if ($bookingMaster->isNotEmpty() && $status === 'success') {
                $this->createConfirmedMasterBooking($bookingMaster, $masterBookingIDs, $paymentId);
                $bookingMaster->each->delete();
            }

            // Send notifications on success
            if ($status === 'success' && $this->smsService && $this->whatsappService) {
                $this->smsService->send($notificationData);
                $this->whatsappService->send($notificationData);
            }

            return [
                'status' => true,
                'message' => 'Event booking transfer completed',
                'transferred_bookings' => count($masterBookingIDs),
                'payment_status' => $status
            ];

        } catch (Exception $e) {
            Log::error('BookingService - Transfer Event Booking Error: ' . $e->getMessage(), [
                'session_id' => $sessionId,
                'status' => $status,
                'payment_id' => $paymentId
            ]);

            return [
                'status' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Transfer amusement bookings from pending to confirmed
     *
     * @param string $sessionId
     * @param string $status
     * @param string $paymentId
     * @return array
     */
    public function transferAmusementBooking($sessionId, $status, $paymentId)
    {
        try {
            $bookings = AmusementPendingBooking::where('session_id', $sessionId)->get();
            $bookingMaster = AmusementPendingMasterBooking::where('session_id', $sessionId)->get();

            if ($bookings->isEmpty()) {
                return [
                    'status' => false,
                    'message' => 'No pending amusement bookings found for session: ' . $sessionId
                ];
            }

            // Prepare notification data
            $notificationData = $this->prepareAmusementNotificationData($bookings);
            $masterBookingIDs = [];

            // Process individual bookings
            foreach ($bookings as $individualBooking) {
                if ($status === 'success') {
                    $booking = $this->createConfirmedAmusementBooking($individualBooking, $paymentId);
                    if ($booking) {
                        $masterBookingIDs[] = $booking->id;
                        $individualBooking->delete();
                    }
                } else {
                    $this->updatePendingAmusementBookingStatus($individualBooking, $status, $paymentId);
                }
            }

            // Process master booking if exists
            if ($bookingMaster->isNotEmpty() && $status === 'success') {
                $this->createConfirmedAmusementMasterBooking($bookingMaster, $masterBookingIDs, $paymentId);
                $bookingMaster->each->delete();
            }

            // Send notifications on success
            if ($status === 'success' && $this->smsService && $this->whatsappService) {
                $this->smsService->send($notificationData);
                $this->whatsappService->send($notificationData);
            }

            return [
                'status' => true,
                'message' => 'Amusement booking transfer completed',
                'transferred_bookings' => count($masterBookingIDs),
                'payment_status' => $status
            ];

        } catch (Exception $e) {
            Log::error('BookingService - Transfer Amusement Booking Error: ' . $e->getMessage(), [
                'session_id' => $sessionId,
                'status' => $status,
                'payment_id' => $paymentId
            ]);

            return [
                'status' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create confirmed event booking from pending booking
     *
     * @param PenddingBooking $pendingBooking
     * @param string $paymentId
     * @return Booking|null
     */
    private function createConfirmedEventBooking($pendingBooking, $paymentId)
    {
        try {
            $booking = new Booking();
            $booking->ticket_id = $pendingBooking->ticket_id;
            $booking->batch_id = $pendingBooking->batch_id;
            $booking->user_id = $pendingBooking->user_id;
            $booking->gateway = $pendingBooking->gateway;
            $booking->session_id = $pendingBooking->session_id;
            $booking->promocode_id = $pendingBooking->promocode_id;
            $booking->token = $pendingBooking->token;
            $booking->payment_id = $paymentId;
            $booking->amount = $pendingBooking->amount > 0 ? $pendingBooking->amount : 0;
            $booking->email = $pendingBooking->email;
            $booking->name = $pendingBooking->name;
            $booking->number = $pendingBooking->number;
            $booking->type = $pendingBooking->type;
            $booking->dates = $pendingBooking->dates ?? now();
            $booking->payment_method = $pendingBooking->payment_method;
            $booking->discount = $pendingBooking->discount;
            $booking->status = 0;
            $booking->payment_status = 1;
            $booking->txnid = $pendingBooking->txnid;
            $booking->device = $pendingBooking->device;
            $booking->base_amount = $pendingBooking->base_amount;
            $booking->convenience_fee = $pendingBooking->convenience_fee;
            $booking->attendee_id = $pendingBooking->attendee_id;
            $booking->total_tax = $pendingBooking->total_tax;
            $booking->save();

            // Handle promocode if exists
            $this->processPromocode($booking->promocode_id);

            return $booking;

        } catch (Exception $e) {
            Log::error('BookingService - Create Confirmed Event Booking Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create confirmed amusement booking from pending booking
     *
     * @param AmusementPendingBooking $pendingBooking
     * @param string $paymentId
     * @return AmusementBooking|null
     */
    private function createConfirmedAmusementBooking($pendingBooking, $paymentId)
    {
        try {
            $booking = new AmusementBooking();
            $booking->ticket_id = $pendingBooking->ticket_id;
            $booking->user_id = $pendingBooking->user_id;
            $booking->gateway = $pendingBooking->gateway;
            $booking->session_id = $pendingBooking->session_id;
            $booking->promocode_id = $pendingBooking->promocode_id;
            $booking->token = $pendingBooking->token;
            $booking->payment_id = $paymentId;
            $booking->amount = $pendingBooking->amount ?? 0;
            $booking->email = $pendingBooking->email;
            $booking->name = $pendingBooking->name;
            $booking->number = $pendingBooking->number;
            $booking->type = $pendingBooking->type;
            $booking->dates = $pendingBooking->dates ?? now();
            $booking->payment_method = $pendingBooking->payment_method;
            $booking->discount = $pendingBooking->discount;
            $booking->status = 0;
            $booking->payment_status = 1;
            $booking->txnid = $pendingBooking->txnid;
            $booking->device = $pendingBooking->device;
            $booking->base_amount = $pendingBooking->base_amount;
            $booking->convenience_fee = $pendingBooking->convenience_fee;
            $booking->attendee_id = $pendingBooking->attendee_id;
            $booking->total_tax = $pendingBooking->total_tax;
            $booking->booking_date = $pendingBooking->booking_date;
            $booking->save();

            // Handle promocode if exists
            $this->processPromocode($booking->promocode_id);

            return $booking;

        } catch (Exception $e) {
            Log::error('BookingService - Create Confirmed Amusement Booking Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create confirmed master booking from pending master booking
     *
     * @param $bookingMaster
     * @param array $bookingIds
     * @param string $paymentId
     * @return bool
     */
    private function createConfirmedMasterBooking($bookingMaster, $bookingIds, $paymentId)
    {
        try {
            foreach ($bookingMaster as $entry) {
                $data = [
                    'user_id' => $entry->user_id,
                    'session_id' => $entry->session_id,
                    'booking_id' => $bookingIds,
                    'order_id' => $entry->order_id,
                    'amount' => $entry->amount,
                    'discount' => $entry->discount,
                    'payment_method' => $entry->payment_method,
                    'gateway' => $entry->gateway,
                    'payment_id' => $paymentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $master = MasterBooking::create($data);
                if (!$master) {
                    return false;
                }
            }
            return true;

        } catch (Exception $e) {
            Log::error('BookingService - Create Confirmed Master Booking Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create confirmed amusement master booking from pending master booking
     *
     * @param $bookingMaster
     * @param array $bookingIds
     * @param string $paymentId
     * @return bool
     */
    private function createConfirmedAmusementMasterBooking($bookingMaster, $bookingIds, $paymentId)
    {
        try {
            foreach ($bookingMaster as $entry) {
                if (!$entry)
                    continue;

                $data = [
                    'user_id' => $entry->user_id,
                    'gateway' => $entry->gateway,
                    'session_id' => $entry->session_id,
                    'booking_id' => is_array($bookingIds) ? json_encode($bookingIds) : json_encode([$bookingIds]),
                    'order_id' => $entry->order_id,
                    'amount' => $entry->amount,
                    'discount' => $entry->discount,
                    'payment_method' => $entry->payment_method,
                    'payment_id' => $paymentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $master = AmusementMasterBooking::create($data);
                if (!$master) {
                    return false;
                }
            }
            return true;

        } catch (Exception $e) {
            Log::error('BookingService - Create Confirmed Amusement Master Booking Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update pending booking status
     *
     * @param PenddingBooking $booking
     * @param string $status
     * @param string $paymentId
     */
    private function updatePendingBookingStatus($booking, $status, $paymentId)
    {
        if ($status === 'failure') {
            $booking->payment_status = 2;
            $booking->payment_id = $paymentId;
        } else {
            $booking->payment_status = $status;
        }
        $booking->save();
    }

    /**
     * Update pending amusement booking status
     *
     * @param AmusementPendingBooking $booking
     * @param string $status
     * @param string $paymentId
     */
    private function updatePendingAmusementBookingStatus($booking, $status, $paymentId)
    {
        if ($status === 'failure') {
            $booking->payment_status = 2;
            $booking->payment_id = $paymentId;
        } else {
            $booking->payment_status = $status;
        }
        $booking->save();
    }

    /**
     * Prepare notification data for event bookings
     *
     * @param $bookings
     * @return object
     */
    private function prepareEventNotificationData($bookings)
    {
        $firstBooking = $bookings->first();
        $orderId = $firstBooking->token ?? '';
        $shortLink = $orderId;
        $whatsappTemplate = WhatsappApi::where('title', 'garbabookingv2')->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $eventDateTime = str_replace(',', ' |', $firstBooking->ticket->event->date_range) . ' | ' .
            $firstBooking->ticket->event->start_time . ' - ' . $firstBooking->ticket->event->end_time;

        $totalQty = count($bookings);
        $mediaurl = $firstBooking->ticket->event->thumbnail;

        return (object) [
            'name' => $firstBooking->name,
            'number' => $firstBooking->number,
            'templateName' => 'Booking Template online',
            'whatsappTemplateData' => $whatsappTemplateName,
            'mediaurl' => $mediaurl,
            'shortLink' => $shortLink,
            'values' => [
                $firstBooking->name,
                $firstBooking->number,
                $firstBooking->ticket->event->name,
                $totalQty,
                $firstBooking->ticket->name,
                $firstBooking->ticket->event->address,
                $eventDateTime,
            ],
            'replacements' => [
                ':C_Name' => $firstBooking->name,
                ':T_QTY' => $totalQty,
                ':Ticket_Name' => $firstBooking->ticket->name,
                ':Event_Name' => $firstBooking->ticket->event->name,
                ':C_number' => $firstBooking->number,
            ]
        ];
    }

    /**
     * Prepare notification data for amusement bookings
     *
     * @param $bookings
     * @return object
     */
    private function prepareAmusementNotificationData($bookings)
    {
        $firstBooking = $bookings->first();
        $orderId = $firstBooking->token ?? '';
        $shortLink = $orderId;
        $whatsappTemplate = WhatsappApi::where('title', 'garbabookingv2')->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $eventDateTime = str_replace(',', ' |', $firstBooking->ticket->event->date_range) . ' | ' .
            $firstBooking->ticket->event->start_time . ' - ' . $firstBooking->ticket->event->end_time;

        $totalQty = count($bookings);
        $mediaurl = $firstBooking->ticket->event->thumbnail;

        return (object) [
            'name' => $firstBooking->name,
            'number' => $firstBooking->number,
            'templateName' => 'Booking Template online',
            'whatsappTemplateData' => $whatsappTemplateName,
            'mediaurl' => $mediaurl,
            'shortLink' => $shortLink,
            'values' => [
                $firstBooking->name,
                $firstBooking->number,
                $firstBooking->ticket->event->name,
                $totalQty,
                $firstBooking->ticket->name,
                $firstBooking->ticket->event->address,
                $eventDateTime,
            ],
            'replacements' => [
                ':C_Name' => $firstBooking->name,
                ':T_QTY' => $totalQty,
                ':Ticket_Name' => $firstBooking->ticket->name,
                ':Event_Name' => $firstBooking->ticket->event->name,
                ':C_number' => $firstBooking->number,
            ]
        ];
    }

    /**
     * Process promocode usage
     *
     * @param string|null $promocodeId
     * @return bool
     */
    private function processPromocode($promocodeId)
    {
        if (!$promocodeId) {
            return true;
        }

        try {
            $promocode = Promocode::where('code', $promocodeId)->first();

            if (!$promocode) {
                Log::warning('Invalid promocode used: ' . $promocodeId);
                return false;
            }

            if ($promocode->remaining_count === null) {
                $promocode->remaining_count = $promocode->usage_limit - 1;
            } elseif ($promocode->remaining_count > 0) {
                $promocode->remaining_count--;
            } else {
                Log::warning('Promocode usage limit reached: ' . $promocodeId);
                return false;
            }

            $promocode->save();
            return true;

        } catch (Exception $e) {
            Log::error('BookingService - Process Promocode Error: ' . $e->getMessage());
            return false;
        }
    }
}