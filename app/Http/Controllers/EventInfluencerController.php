<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Influencer;
use App\Models\EventInfluencer;
use App\Models\MasterBooking;
use App\Models\WhatsappApi;
use App\Services\SmsService;
use App\Services\WhatsappService;
use App\Jobs\SendBookingConfirmationJob;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class EventInfluencerController extends Controller
{
    protected $smsService, $whatsappService;

    public function __construct(SmsService $smsService, WhatsappService $whatsappService)
    {
        $this->smsService = $smsService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Get all influencers for a specific event.
     */
    public function getEventInfluencers($eventId)
    {
        try {
            $event = Event::findOrFail($eventId);
            $influencers = $event->influencers()
                ->select('influencers.id', 'influencers.name', 'influencers.email', 'influencers.phone', 'influencers.bio', 'influencers.social_media_handle', 'influencers.platform', 'influencers.followers', 'influencers.status')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Event influencers retrieved successfully',
                'data' => $influencers
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Event not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving event influencers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign a single influencer to an event.
     */
    public function assignInfluencer(Request $request, $eventId)
    {
        try {
            $validated = $request->validate([
                'influencer_id' => 'required|integer|exists:influencers,id',
            ]);

            $event = Event::findOrFail($eventId);

            // Check if already assigned
            $exists = EventInfluencer::where('event_id', $eventId)
                ->where('influencer_id', $validated['influencer_id'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Influencer already assigned to this event',
                ], 409);
            }

            $eventInfluencer = EventInfluencer::create([
                'event_id' => $eventId,
                'influencer_id' => $validated['influencer_id'],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Influencer assigned to event successfully',
                'data' => $eventInfluencer
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Event or Influencer not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error assigning influencer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk assign multiple influencers to an event (replaces existing).
     * Pass empty array to remove all influencers.
     */
    public function bulkAssignInfluencers(Request $request, $eventId)
    {
        try {
            $validated = $request->validate([
                'influencer_ids' => 'array', // No required, allows null/missing or empty array
                'influencer_ids.*' => 'integer|exists:influencers,id',
            ]);

            // If influencer_ids is not provided, set it to empty array
            $influencerIds = array_values(array_unique($validated['influencer_ids'] ?? []));

            // Delete ALL existing influencers for this event first
            EventInfluencer::where('event_id', $eventId)->forceDelete();

            // If influencer_ids is not empty, add new influencers
            if (!empty($influencerIds)) {
                $data = [];
                foreach ($influencerIds as $influencerId) {
                    $data[] = [
                        'event_id' => $eventId,
                        'influencer_id' => $influencerId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                
                // Bulk insert all new influencers
                EventInfluencer::insert($data);
            }

            // Fetch final influencers for this event (without circular relationships)
            $finalInfluencers = EventInfluencer::where('event_id', $eventId)
                ->with(['influencer' => function($query) {
                    $query->select('id', 'name', 'email', 'phone', 'bio', 'social_media_handle', 'platform', 'followers', 'status');
                }])
                ->get();

            return response()->json([
                'status' => true,
                'message' => empty($influencerIds) ? 'All influencers removed successfully' : 'Influencers assigned to event successfully',
                'assigned_count' => count($influencerIds),
                'data' => $finalInfluencers
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Event not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error assigning influencers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove an influencer from an event.
     */
    public function removeInfluencer($eventId, $influencerId)
    {
        try {
            $eventInfluencer = EventInfluencer::where('event_id', $eventId)
                ->where('influencer_id', $influencerId)
                ->firstOrFail();

            $eventInfluencer->delete();

            return response()->json([
                'status' => true,
                'message' => 'Influencer removed from event successfully',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Assignment not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error removing influencer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk remove influencers from an event.
     */
    public function bulkRemoveInfluencers(Request $request, $eventId)
    {
        try {
            $validated = $request->validate([
                'influencer_ids' => 'required|array|min:1',
                'influencer_ids.*' => 'integer',
            ]);

            $deleted = EventInfluencer::where('event_id', $eventId)
                ->whereIn('influencer_id', $validated['influencer_ids'])
                ->delete();

            if ($deleted == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No influencers found to remove',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Influencers removed from event successfully',
                'removed_count' => $deleted
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error removing influencers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get events for a specific influencer.
     */
    public function getInfluencerEvents($influencerId)
    {
        try {
            $influencer = Influencer::findOrFail($influencerId);
            $events = $influencer->events()->paginate(15);

            return response()->json([
                'status' => true,
                'message' => 'Influencer events retrieved successfully',
                'data' => $events
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Influencer not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving influencer events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update approval status for a booking.
     * Changes approval_status from 'pending' to 'confirmed'
     * Sends SMS/WhatsApp confirmation notifications
     * 
     * Request body:
     * {
     *     "id": 1,
     *     "is_master": true/false
     * }
     */
    public function updateApprovalStatus(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer',
                'is_master' => 'required|boolean',
            ]);

            $bookingId = $validated['id'];
            $isMaster = $validated['is_master'];

            if ($isMaster) {
                // Find master booking and update its approval_status
                $masterBooking = MasterBooking::findOrFail($bookingId);
                $masterBooking->update(['approval_status' => 'confirmed']);

                // Also update all inner bookings (from booking_id array) and dispatch jobs
                if (!empty($masterBooking->booking_id) && is_array($masterBooking->booking_id)) {
                    $bookings = Booking::whereIn('id', $masterBooking->booking_id)->get();
                    $innerBookingCount = count($masterBooking->booking_id);
                    
                    foreach ($bookings as $booking) {
                        $booking->update(['approval_status' => 'confirmed']);
                    }
                    SendBookingConfirmationJob::dispatch($bookings[0]->id,$masterBooking->order_id, true, $innerBookingCount);
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Master booking and all inner bookings approval status updated to confirmed. Confirmations queued for sending.',
                    'data' => [
                        'id' => $masterBooking->id,
                        'approval_status' => 'confirmed'
                    ]
                ], 200);
            } else {
                // Find normal booking and update its approval_status
                $booking = Booking::findOrFail($bookingId);
                $booking->update(['approval_status' => 'confirmed']);

                // Dispatch job for sending SMS/WhatsApp confirmation asynchronously
                // For regular booking, quantity is 1
                SendBookingConfirmationJob::dispatch($booking->id, false, 1);

                return response()->json([
                    'status' => true,
                    'message' => 'Booking approval status updated to confirmed. Confirmation queued for sending.',
                    'data' => [
                        'id' => $booking->id,
                        'approval_status' => 'confirmed'
                    ]
                ], 200);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error updating approval status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
