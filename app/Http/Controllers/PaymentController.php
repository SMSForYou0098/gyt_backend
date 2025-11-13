<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\PaymentGatewayManager;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $gatewayManager;

    public function __construct(PaymentGatewayManager $gatewayManager)
    {
        $this->gatewayManager = $gatewayManager;
    }

    public function processPayment(Request $request)
    {
        // return response()->json($request->all());
        $organizerId = $request->organizer_id;
        // return response()->json($request->all());
        if ($request->event_id) {
            $event = Event::where('event_key', $request->event_id)->first();

            if (!$event) {
                return response()->json(['status' => false, 'message' => 'Event not found'], 404);
            }

            // Check if the event is expired based on date_range

            $today = Carbon::today();
            $dateRange = explode(',', $event->date_range);

            if (count($dateRange) === 2) {
                $endDate = Carbon::parse($dateRange[1]);
            } else {
                $endDate = Carbon::parse($dateRange[0]);
            }

            if ($today->gt($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Event has been expired'
                ], 419);
            }
        }

        if (!$organizerId) {
            return response()->json([
                'success' => false,
                'message' => 'Organizer ID is required.',
            ], 400);
        }

        $requestData = json_decode($request->requestData ?? '{}');

        if (!$requestData) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid requestData format.',
            ], 400);
        }

        $number = $requestData->number;
        $newQty = $requestData->tickets->quantity ?? 0;
        $eventKey = $requestData->tickets->id ?? 0;

        if ($number && $eventKey) {
            $ticket = Ticket::find($eventKey);

            if ($ticket) {
                $userBookingLimit = $ticket->user_booking_limit;


                $totalBookedByUser = Booking::where('ticket_id', $ticket->id)
                    ->where('number', $number)
                    ->count();



                $totalAfterNewBooking = $totalBookedByUser + $newQty;

                if ($userBookingLimit > 0 && $totalAfterNewBooking > $userBookingLimit) {
                    return response()->json([
                        'status' => false,
                        'message' => "You have reached the max limit.",
                    ], 403);
                }
            }
        }


        // Check for 0 amount and ticket quantity > 0
        $requestData = json_decode($request->requestData ?? '{}');
        $ticketQty = $requestData->tickets->quantity ?? 0;

        if ($request->amount == "0" && $ticketQty > 0) {
            $gatewayController = app()->make(\App\Http\Controllers\EasebuzzController::class);
            return app()->call([$gatewayController, 'initiatePayment'], ['request' => $request]);
        }

        $gatewayControllerClass = $this->gatewayManager->getNextGateway($organizerId);

        if (!$gatewayControllerClass) {
            return response()->json([
                'success' => false,
                'message' => 'No active payment gateway available for this organizer.',
            ], 503);
        }
        // return response()->json([
        //     'success' => true,
        //     'message' => 'Payment gateway found',
        //     'gateway' => $gatewayControllerClass,
        // ], 200);
        $gatewayController = app()->make($gatewayControllerClass);
        return app()->call([$gatewayController, 'initiatePayment'], ['request' => $request]);
    }
}
