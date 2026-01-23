<?php

namespace App\Http\Controllers;

use App\Models\AccessArea;
use App\Models\AccreditationBooking;
use App\Models\AccreditationMasterBooking;
use App\Models\Agent;
use App\Models\AgentMaster;
use App\Models\AmusementAgentBooking;
use App\Models\AmusementAgentMasterBooking;
use App\Models\AmusementBooking;
use App\Models\AmusementMasterBooking;
use App\Models\AmusementPosBooking;
use App\Models\Attndy;
use App\Models\Booking;
use App\Models\Category;
use App\Models\ComplimentaryBookings;
use App\Models\CorporateBooking;
use App\Models\CorporateUser;
use App\Models\ExhibitionBooking;
use App\Models\MasterBooking;
use App\Models\PosBooking;
use App\Models\ScanHistory;
use App\Models\SponsorBooking;
use App\Models\SponsorMasterBooking;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Auth;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function verifyTicket(Request $request, $orderId)
    {
        try {
            // Try to find each type of booking
            $loggedInUser = Auth::user();  // This fetches the authenticated user via Laravel's Auth system
            $isAdmin = $loggedInUser->hasRole('Admin');
            $booking = Booking::where('token', $orderId)->with(['ticket.event.user', 'attendee'])->first();
            $agentBooking = Agent::where('token', $orderId)->with('ticket.event.user', 'attendee')->first();
            $AccreditationBooking = AccreditationBooking::where('token', $orderId)->with('ticket.event.user', 'attendee')->first();
            $SponsorBooking = SponsorBooking::where('token', $orderId)->with('ticket.event.user', 'attendee')->first();
            $amusementAgentBooking = AmusementAgentBooking::where('token', $orderId)->with('ticket.event.user', 'attendee')->first();
            $ExhibitionBooking = ExhibitionBooking::where('token', $orderId)->with('ticket.event.user', 'attendee')->first();
            $amusementBooking = AmusementBooking::where('token', $orderId)->with(['ticket.event.user', 'attendee'])->first();
            $complimentaryBookings = ComplimentaryBookings::where('token', $orderId)->with('ticket.event.user')->first();
            $posBooking = PosBooking::where('token', $orderId)->with('ticket.event.user', 'ticket.event.Category')->first();
            $corporateBooking = CorporateBooking::where('token', $orderId)->with('ticket.event.user', 'ticket.event.Category')->first();
            $amusementPosBooking = AmusementPosBooking::where('token', $orderId)->with('ticket.event.user', 'ticket.event.Category')->first();
            $masterBookings = MasterBooking::where('order_id', $orderId)->first();
            $amusementMasterBookings = AmusementMasterBooking::where('order_id', $orderId)->first();
            $agentMasterBookings = AgentMaster::where('order_id', $orderId)->first();
            $AccreditationMasterBooking = AccreditationMasterBooking::where('order_id', $orderId)->first();
            $SponsorMasterBooking = SponsorMasterBooking::where('order_id', $orderId)->first();
            $amusementAgentMasterBookings = AmusementAgentMasterBooking::where('order_id', $orderId)->first();

            // return response()->json($amusementAgentBooking);
          $eventData = $this->eventCheck($booking, $agentBooking, $posBooking, $corporateBooking, $complimentaryBookings, $masterBookings, $agentMasterBookings, $ExhibitionBooking, $amusementBooking, $amusementMasterBookings, $amusementAgentBooking, $amusementAgentMasterBookings, $amusementPosBooking, $AccreditationBooking, $AccreditationMasterBooking, $SponsorBooking, $SponsorMasterBooking);
            $organizer = $eventData['organizer'];
            $relatedBookings = $eventData['relatedBookings'];
            $event = $eventData['event'];
            $category = Category::find($event['category']);

            if ($event) {
                $dateRange = array_map('trim', explode(',', $event->date_range));
                $timezone = new DateTimeZone('Asia/Kolkata');
                if (count($dateRange) === 1) {
                    $startDate = new DateTime($dateRange[0], $timezone);
                    $startDate->setTime(0, 0);
                    $endDate = clone $startDate;
                    $endDate->setTime(23, 59, 59);
                } elseif (count($dateRange) === 2) {
                    $startDate = new DateTime($dateRange[0], $timezone);
                    $startDate->setTime(0, 0);
                    $endDate = new DateTime($dateRange[1], $timezone);
                    $endDate->setTime(23, 59, 59);
                } else {
                    return response()->json([
                        'status' => 'false',
                        'message' => 'Invalid date range',
                    ], 400);
                }
                $currentDate = new DateTime('now', $timezone);

                // Additional validation to ensure dates are valid
                if (!$startDate || !$endDate) {
                    return response()->json([
                        'status' => 'false',
                        'message' => 'Invalid dates provided',
                    ], 400);
                }
                if (!($currentDate >= $startDate && $currentDate <= $endDate)) {
                    return response()->json([
                        'status' => 'false',
                        'message' => 'This event is not currently active',
                    ], 400);
                }

                if ($category->title == 'Amusement') {
                    // Check which booking is available and assign $bookingDate accordingly
                    if (!empty($amusementBooking?->booking_date)) {
                        $bookingDate = new DateTime($amusementBooking->booking_date, $timezone);
                    } elseif (!empty($amusementAgentBooking?->booking_date)) {
                        $bookingDate = new DateTime($amusementAgentBooking->booking_date, $timezone);
                    } elseif (!empty($posBooking?->booking_date)) {
                        $bookingDate = new DateTime($posBooking->booking_date, $timezone);
                    } elseif (!empty($amusementPosBooking?->booking_date)) {
                        $bookingDate = new DateTime($amusementPosBooking->booking_date, $timezone);
                    } else {
                        return response()->json([
                            'status' => false,
                            'message' => 'No valid booking found.',
                        ], 400);
                    }

                    // Compare booking date with the current date
                    if ($bookingDate->format('Y-m-d') !== $currentDate->format('Y-m-d')) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Booking is only valid on ' . $bookingDate->format('Y-m-d'),
                        ], 400);
                    }
                }

                try {
                    if (empty($event->entry_time)) {
                        return response()->json([
                            'status' => 'false',
                            'message' => 'Event Entry Time Not Provided',
                        ], 400);
                    }
                    $startTimeWithDate = clone $currentDate;
                    $startTime = DateTime::createFromFormat('H:i', $event->entry_time, $timezone);
                    // $startTime = DateTime::createFromFormat('H:i', $event->start_time, $timezone);
                    $startTimeWithDate->setTime($startTime->format('H'), $startTime->format('i'));

                    // Check if the event has started
                    if ($currentDate < $startTimeWithDate) {
                        return response()->json([
                            'status' => 'false',
                            'message' => 'This event has not started yet',
                        ], 400);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'false',
                        'message' => 'Invalid start time provided',
                    ], 400);
                }
            }

            // Check if the organizer ID is valid and matches the request ID
            if (
                !$organizer ||
                (!$isAdmin && (
                    intval($organizer) !== intval($loggedInUser->id) &&
                    intval($loggedInUser->id) !== intval($organizer) &&
                    intval($organizer) !== intval($loggedInUser->reporting_user)
                ))
            ) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'This Ticket Is Not Recognized',
                    'organizer' => $isAdmin,
                ], 404);
            }

            // Handle the specific booking type logic
            if ($posBooking) {
                if ($posBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $posBooking,
                        'attendee_required' => false,
                        'event' => $event,
                        'type' => "POS"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $posBooking->updated_at
                    ], 404);
                }
            }
            if ($corporateBooking) {
                if ($corporateBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $corporateBooking,
                        'attendee_required' => false,
                        'event' => $event,
                        'type' => "CorporateBooking"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $corporateBooking->updated_at
                    ], 404);
                }
            } elseif ($amusementPosBooking) {
                if ($amusementPosBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $amusementPosBooking,
                        'attendee_required' => false,
                        'event' => $event,
                        'type' => "POS"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $amusementPosBooking->updated_at
                    ], 404);
                }
            } elseif ($booking) {
                if ($booking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $booking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Online"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $booking->updated_at
                    ], 404);
                }
            } elseif ($amusementBooking) {

                if ($amusementBooking->status == '0') {

                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $amusementBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Online"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $amusementBooking->updated_at
                    ], 404);
                }
            } elseif ($agentBooking) {
                if ($agentBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $agentBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Agent"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $agentBooking->updated_at
                    ], 404);
                }
            } elseif ($AccreditationBooking) {
                if ($AccreditationBooking->status == '0') {
                    //     $accessAreaIds = is_array($AccreditationBooking->access_area)
                    //     ? $AccreditationBooking->access_area
                    //     : explode(',', $AccreditationBooking->access_area);

                    // // Fetch names from AccessArea model
                    // $accessAreaNames = AccessArea::whereIn('id', $accessAreaIds)->pluck('title');
                    // $AccreditationBooking->access_area = $accessAreaNames;

                    $bookingAccess = is_array($AccreditationBooking->access_area)
                        ? $AccreditationBooking->access_area
                        : explode(',', $AccreditationBooking->access_area);

                    $ticketAccess = is_array($AccreditationBooking->ticket->access_area ?? null)
                        ? $AccreditationBooking->ticket->access_area
                        : explode(',', $AccreditationBooking->ticket->access_area ?? '');

                    // Merge and get unique access area IDs
                    $mergedAccessIds = collect($bookingAccess)
                        ->merge($ticketAccess)
                        ->map(fn($id) => (int) trim($id))
                        ->unique()
                        ->values()
                        ->all();
                    $accessAreaNames = AccessArea::whereIn('id', $mergedAccessIds)->pluck('title');
                    $AccreditationBooking->access_area = $accessAreaNames;

                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $AccreditationBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        // 'access_area' => $accessAreaNames ?? [],
                        'event' => $event,
                        'type' => "Accreditation"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $AccreditationBooking->updated_at
                    ], 404);
                }
            } elseif ($SponsorBooking) {
                if ($SponsorBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $SponsorBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Agent"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $SponsorBooking->updated_at
                    ], 404);
                }
            } elseif ($amusementAgentBooking) {
                if ($amusementAgentBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $amusementAgentBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Agent"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $amusementAgentBooking->updated_at
                    ], 404);
                }
            } elseif ($ExhibitionBooking) {
                if ($ExhibitionBooking->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $ExhibitionBooking,
                        'attendee_required' => $event->Category->attendy_required ?? false,
                        'event' => $event,
                        'type' => "Agent"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $ExhibitionBooking->updated_at
                    ], 404);
                }
            } elseif ($complimentaryBookings) {
                if ($complimentaryBookings->status == '0') {
                    return response()->json([
                        'status' => true,
                        'is_master' => false,
                        'bookings' => $complimentaryBookings,
                        'attendee_required' => false,
                        'event' => $event,
                        'type' => "Complimentary"
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Already Scanned',
                        'time' => $complimentaryBookings->updated_at
                    ], 404);
                }
            } else if ($masterBookings) {
              
            $allStatusZero = $relatedBookings->every(function ($relatedBooking) {
                return $relatedBooking->status == "0";
            });

            if ($allStatusZero) {
                $masterBookings->bookings = $relatedBookings;
                return response()->json([
                    'status' => true,
                    'is_master' => true,
                    'bookings' => $masterBookings,
                    'attendee_required' => $event->Category->attendy_required ?? false,
                    'event' => $event,
                    'type' => "Online"
                ], 200);
            } else {
                // Check if any individual ticket was scanned
                $scannedBooking = $relatedBookings->where('status', '!=', '0')->sortByDesc('updated_at')->first();
                $allScanned = $relatedBookings->every(function ($relatedBooking) {
                    return $relatedBooking->status != "0";
                });

                return response()->json([
                    'status' => false,
                    'message' => $allScanned ? 'Already Scanned' : 'Already scanned individually',
                    'time' => $scannedBooking->updated_at ?? $masterBookings->updated_at
                ], 400);
            }
        } elseif ($amusementMasterBookings) {
            $allStatusZero = $relatedBookings->every(function ($relatedBooking) {
                return $relatedBooking->status == "0";
            });

            if ($allStatusZero) {
                $amusementMasterBookings->bookings = $relatedBookings;
                return response()->json([
                    'status' => true,
                    'is_master' => true,
                    'bookings' => $amusementMasterBookings,
                    'attendee_required' => $event->Category->attendy_required ?? false,
                    'event' => $event,
                    'type' => "Online"
                ], 200);
            } else {
                $scannedBooking = $relatedBookings->where('status', '!=', '0')->sortByDesc('updated_at')->first();
                $allScanned = $relatedBookings->every(function ($relatedBooking) {
                    return $relatedBooking->status != "0";
                });

                return response()->json([
                    'status' => false,
                    'message' => $allScanned ? 'Already Scanned' : 'Already scanned individually',
                    'time' => $scannedBooking->updated_at ?? $amusementMasterBookings->updated_at
                ], 400);
            }
        } elseif ($agentMasterBookings) {
            $allStatusZero = $relatedBookings->every(function ($relatedBooking) {
                return $relatedBooking->status == "0";
            });

            if ($allStatusZero) {
                $agentMasterBookings->bookings = $relatedBookings;
                return response()->json([
                    'status' => true,
                    'is_master' => true,
                    'bookings' => $agentMasterBookings,
                    'attendee_required' => $event->Category->attendy_required ?? false,
                    'event' => $event,
                    'type' => "Agent"
                ], 200);
            } else {
                $scannedBooking = $relatedBookings->where('status', '!=', '0')->sortByDesc('updated_at')->first();
                $allScanned = $relatedBookings->every(function ($relatedBooking) {
                    return $relatedBooking->status != "0";
                });

                return response()->json([
                    'status' => false,
                    'message' => $allScanned ? 'Already Scanned' : 'Already scanned individually',
                    'time' => $scannedBooking->updated_at ?? $agentMasterBookings->updated_at
                ], 400);
            }
        } elseif ($AccreditationMasterBooking) {
            $allStatusZero = $relatedBookings->every(function ($relatedBooking) {
                return $relatedBooking->status == "0";
            });

            if ($allStatusZero) {
                $AccreditationMasterBooking->bookings = $relatedBookings;
                return response()->json([
                    'status' => true,
                    'is_master' => true,
                    'bookings' => $AccreditationMasterBooking,
                    'attendee_required' => $event->Category->attendy_required ?? false,
                    'event' => $event,
                    'type' => "Agent"
                ], 200);
            } else {
                $scannedBooking = $relatedBookings->where('status', '!=', '0')->sortByDesc('updated_at')->first();
                $allScanned = $relatedBookings->every(function ($relatedBooking) {
                    return $relatedBooking->status != "0";
                });

                return response()->json([
                    'status' => false,
                    'message' => $allScanned ? 'Already Scanned' : 'Already scanned individually',
                    'time' => $scannedBooking->updated_at ?? $AccreditationMasterBooking->updated_at
                ], 400);
            }
        } elseif ($SponsorMasterBooking) {
            $allStatusZero = $relatedBookings->every(function ($relatedBooking) {
                return $relatedBooking->status == "0";
            });

            if ($allStatusZero) {
                $SponsorMasterBooking->bookings = $relatedBookings;
                return response()->json([
                    'status' => true,
                    'is_master' => true,
                    'bookings' => $SponsorMasterBooking,
                    'attendee_required' => $event->Category->attendy_required ?? false,
                    'event' => $event,
                    'type' => "Agent"
                ], 200);
            } else {
                $scannedBooking = $relatedBookings->where('status', '!=', '0')->sortByDesc('updated_at')->first();
                $allScanned = $relatedBookings->every(function ($relatedBooking) {
                    return $relatedBooking->status != "0";
                });

                return response()->json([
                    'status' => false,
                    'message' => $allScanned ? 'Already Scanned' : 'Already scanned individually',
                    'time' => $scannedBooking->updated_at ?? $SponsorMasterBooking->updated_at
                ], 400);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No booking found'
            ], 404);
        }
        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function ChekIn($orderId)
    {
        $booking = Booking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $agentBooking = Agent::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $AccreditationBooking = AccreditationBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $SponsorBooking = SponsorBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $amusementAgentBooking = AmusementAgentBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $ExhibitionBooking = ExhibitionBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $amusementBooking = AmusementBooking::where('token', $orderId)->with(['ticket.event.user', 'attendee'])->first();
        $posBooking = PosBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $corporateBooking = CorporateBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $amusementPosBooking = AmusementPosBooking::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $complimentaryBookings = ComplimentaryBookings::where('token', $orderId)->where('status', 0)->with('ticket.event.user')->first();
        $masterBookings = MasterBooking::where('order_id', $orderId)->first();
        $amusementMasterBookings = AmusementMasterBooking::where('order_id', $orderId)->first();
        $agentMasterBookings = AgentMaster::where('order_id', $orderId)->first();
        $AccreditationMasterBooking = AccreditationMasterBooking::where('order_id', $orderId)->first();
        $SponsorMasterBooking = SponsorMasterBooking::where('order_id', $orderId)->first();
        $amusementAgentMasterBookings = AmusementAgentMasterBooking::where('order_id', $orderId)->first();
        $today = Carbon::now()->toDateTimeString();

        $eventData = $this->eventCheck($booking, $agentBooking, $posBooking, $corporateBooking, $complimentaryBookings, $masterBookings, $agentMasterBookings, $ExhibitionBooking, $amusementBooking, $amusementMasterBookings, $amusementAgentBooking, $amusementAgentMasterBookings, $amusementPosBooking, $AccreditationBooking, $AccreditationMasterBooking, $SponsorBooking,$SponsorMasterBooking);
        $organizer = $eventData['organizer'];
        $relatedBookings = $eventData['relatedBookings'];
        $event = $eventData['event'];
        // return response()->json($posBooking);
        if ($booking) {
            if ($event->multi_scan) {
                $booking->status = false;
            } else {
                $booking->status = true;
            }
            $booking->is_scaned = true;
            $booking->save();
           // $history = $this->logScanHistory($booking->user_id, auth()->id(), $booking->token, 'online');
            return response()->json([
                'status' => true,
                'bookings' => $booking->status
            ], 200);
        } else if ($amusementBooking) {
            if ($event->multi_scan) {
                $amusementBooking->status = false;
            } else {
                $amusementBooking->status = true;
            }
            $amusementBooking->is_scaned = true;
            $amusementBooking->save();
           // $history = $this->logScanHistory($booking->user_id, auth()->id(), $booking->token, 'amusementBooking');
            return response()->json([
                'status' => true,
                'bookings' => $amusementBooking->status
            ], 200);
        } else if ($agentBooking) {
            if ($event->multi_scan) {
                $agentBooking->status = false;
            } else {
                $agentBooking->status = true;
            }
            // $agentBooking->status = true;
            $agentBooking->is_scaned = true;
            $agentBooking->save();
            //$history = $this->logScanHistory($agentBooking->user_id, auth()->id(), $agentBooking->token, 'agentBooking');
            return response()->json([
                'status' => true,
                'bookings' => $agentBooking->status
            ], 200);
        } else if ($AccreditationBooking) {
            if ($event->multi_scan) {
                $AccreditationBooking->status = false;
            } else {
                $AccreditationBooking->status = true;
            }
            // $agentBooking->status = true;
            $AccreditationBooking->is_scaned = true;
            $AccreditationBooking->save();
           // $history = $this->logScanHistory($AccreditationBooking->user_id, auth()->id(), $AccreditationBooking->token, 'AccreditationBooking');
            return response()->json([
                'status' => true,
                'bookings' => $AccreditationBooking->status
            ], 200);
        } else if ($SponsorBooking) {
            if ($event->multi_scan) {
                $SponsorBooking->status = false;
            } else {
                $SponsorBooking->status = true;
            }
            // $agentBooking->status = true;
            $SponsorBooking->is_scaned = true;
            $SponsorBooking->save();
            //$history = $this->logScanHistory($SponsorBooking->user_id, auth()->id(), $SponsorBooking->token, 'SponsorBooking');

            return response()->json([
                'status' => true,
                'bookings' => $SponsorBooking->status
            ], 200);
        } else if ($amusementAgentBooking) {
            if ($event->multi_scan) {
                $amusementAgentBooking->status = false;
            } else {
                $amusementAgentBooking->status = true;
            }
            // $amusementAgentBooking->status = true;
            $amusementAgentBooking->is_scaned = true;
            $amusementAgentBooking->save();
            //$history = $this->logScanHistory($amusementAgentBooking->user_id, auth()->id(), $amusementAgentBooking->token, 'amusementAgentBooking');

            return response()->json([
                'status' => true,
                'bookings' => $amusementAgentBooking->status
            ], 200);
        } else if ($ExhibitionBooking) {
            if ($event->multi_scan) {
                $ExhibitionBooking->status = false;
            } else {
                $ExhibitionBooking->status = true;
            }
            // $ExhibitionBooking->status = true;
            $ExhibitionBooking->is_scaned = true;
            $ExhibitionBooking->save();
            //$history = $this->logScanHistory($ExhibitionBooking->user_id, auth()->id(), $ExhibitionBooking->token, 'ExhibitionBooking');

            return response()->json([
                'status' => true,
                'bookings' => $ExhibitionBooking->status
            ], 200);
        } else if ($posBooking) {
            if ($event->multi_scan) {
                $posBooking->status = false;
            } else {
                $posBooking->status = true;
            }
            $posBooking->is_scaned = true;
            $posBooking->status = true;
            $posBooking->save();
            //$history = $this->logScanHistory($posBooking->user_id, auth()->id(), $posBooking->token, 'posBooking');

            return response()->json([
                'status' => true,
                'bookings' => $posBooking->status
            ], 200);
        } else if ($corporateBooking) {
            if ($event->multi_scan) {
                $corporateBooking->status = false;
            } else {
                $corporateBooking->status = true;
            }
            $corporateBooking->is_scaned = true;
            $corporateBooking->status = true;
            $corporateBooking->save();
           // $history = $this->logScanHistory($corporateBooking->user_id, auth()->id(), $corporateBooking->token, 'corporateBooking');

            return response()->json([
                'status' => true,
                'bookings' => $corporateBooking->status
            ], 200);
        } else if ($amusementPosBooking) {
            if ($event->multi_scan) {
                $amusementPosBooking->status = false;
            } else {
                $amusementPosBooking->status = true;
            }
            $amusementPosBooking->is_scaned = true;
            $amusementPosBooking->save();
            //$history = $this->logScanHistory($amusementPosBooking->user_id, auth()->id(), $amusementPosBooking->token, 'amusementPosBooking');

            return response()->json([
                'status' => true,
                'bookings' => $amusementPosBooking->status
            ], 200);
        } else if ($complimentaryBookings) {
            if ($event->multi_scan) {
                $complimentaryBookings->status = false;
            } else {
                $complimentaryBookings->status = true;
            }
            $complimentaryBookings->is_scaned = true;
            $complimentaryBookings->save();
            //$history = $this->logScanHistory($complimentaryBookings->user_id, auth()->id(), $complimentaryBookings->token, 'complimentaryBookings');

            return response()->json([
                'status' => true,
                'bookings' => $complimentaryBookings->status
            ], 200);
        } else if ($masterBookings) {
            $bookingIds = $masterBookings->booking_id;
            $relatedBookings = Booking::with('ticket.event.user')->where('status', 0)->whereIn('id', $bookingIds)->get();

            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
                //$history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $masterBookings->order_id, 'masterBookings');
            }
            return response()->json([
                'status' => true,
            ], 200);
        } else if ($amusementMasterBookings) {
            $bookingIds = $amusementMasterBookings->booking_id;
            $relatedBookings = AmusementBooking::with('ticket.event.user')->where('status', 0)->whereIn('id', $bookingIds)->get();

            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
               // $history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $amusementMasterBookings->order_id, 'amusementMasterBookings');
            }
            return response()->json([
                'status' => true,
            ], 200);
        } else if ($agentMasterBookings) {
            $agent = $agentMasterBookings->booking_id;
            $relatedBookings = Agent::with('ticket.event.user')->where('status', 0)->whereIn('id', $agent)->get();
            // return response()->json([
            //     'data' => $relatedBookings,
            // ], 200);
            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
               // $history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $agentMasterBookings->order_id, 'agentMasterBookings');
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        } else if ($AccreditationMasterBooking) {
            $agent = $AccreditationMasterBooking->booking_id;
            $relatedBookings = AccreditationBooking::with('ticket.event.user')->where('status', 0)->whereIn('id', $agent)->get();

            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
                //$history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $AccreditationMasterBooking->order_id, 'AccreditationMasterBooking');
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        } else if ($SponsorMasterBooking) {
            $agent = $SponsorMasterBooking->booking_id;
            $relatedBookings = SponsorBooking::with('ticket.event.user')->where('status', 0)->whereIn('id', $agent)->get();

            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
                //$history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $SponsorMasterBooking->order_id, 'SponsorMasterBooking');
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        } else if ($amusementAgentMasterBookings) {
            $agent = $amusementAgentMasterBookings->booking_id;
            $relatedBookings = AmusementAgentBooking::with('ticket.event.user')->where('status', 0)->whereIn('id', $agent)->get();

            foreach ($relatedBookings as $relatedBooking) {
                if ($event->multi_scan) {
                    $relatedBooking->status = false;
                } else {
                    $relatedBooking->status = true;
                }
                if ($relatedBooking->type == "season") {
                    $checkInDates = $relatedBooking->dates ? json_decode($relatedBooking->dates, true) : [];
                    $checkInDates[] = $today;
                    $relatedBooking->dates = json_encode($checkInDates);
                }
                $relatedBooking->is_scaned = true;
                $relatedBooking->save();
              //  $history = $this->logScanHistory($relatedBooking->user_id, auth()->id(), $amusementAgentMasterBookings->order_idn, 'amusementAgentMasterBookings');
            }
            return response()->json([
                'status' => 'true',
            ], 200);
        }
    }

    private function eventCheck($booking, $agentBooking, $posBooking, $corporateBooking, $complimentaryBookings, $masterBookings, $agentMasterBookings, $ExhibitionBooking, $amusementBooking, $amusementMasterBookings, $amusementAgentBooking, $amusementAgentMasterBookings, $amusementPosBooking, $AccreditationBooking, $AccreditationMasterBooking, $SponsorBooking, $SponsorMasterBooking)
    {
        $organizer = null;
        $relatedBookings = collect();
        $event = null;

        if ($booking) {
            $organizer = $booking->ticket->event->user->id;
            $relatedBookings = collect([$booking]);
            $event = $booking->ticket->event;
        } elseif ($amusementBooking) {
            $organizer = $amusementBooking->ticket->event->user->id;
            $relatedBookings = collect([$amusementBooking]);
            $event = $amusementBooking->ticket->event;
            $event->load('category');
        } elseif ($agentBooking) {
            $organizer = $agentBooking->ticket->event->user->id;
            $relatedBookings = collect([$agentBooking]);
            $event = $agentBooking->ticket->event;
        } elseif ($AccreditationBooking) {
            $organizer = $AccreditationBooking->ticket->event->user->id;
            $relatedBookings = collect([$AccreditationBooking]);
            $event = $AccreditationBooking->ticket->event;
        } elseif ($SponsorBooking) {
            $organizer = $SponsorBooking->ticket->event->user->id;
            $relatedBookings = collect([$SponsorBooking]);
            $event = $SponsorBooking->ticket->event;
        } elseif ($amusementAgentBooking) {
            $organizer = $amusementAgentBooking->ticket->event->user->id;
            $relatedBookings = collect([$amusementAgentBooking]);
            $event = $amusementAgentBooking->ticket->event;
        } elseif ($ExhibitionBooking) {
            $organizer = $ExhibitionBooking->ticket->event->user->id;
            $relatedBookings = collect([$ExhibitionBooking]);
            $event = $ExhibitionBooking->ticket->event;
        } elseif ($posBooking) {
            $organizer = $posBooking->ticket->event->user->id;
            $relatedBookings = collect([$posBooking]);
            $event = $posBooking->ticket->event;
        } elseif ($corporateBooking) {
            $organizer = $corporateBooking->ticket->event->user->id;
            $relatedBookings = collect([$corporateBooking]);
            $event = $corporateBooking->ticket->event;
        } elseif ($amusementPosBooking) {
            $organizer = $amusementPosBooking->ticket->event->user->id;
            $relatedBookings = collect([$amusementPosBooking]);
            $event = $amusementPosBooking->ticket->event;
        } elseif ($complimentaryBookings) {
            $organizer = $complimentaryBookings->ticket->event->user->id;
            $relatedBookings = collect([$complimentaryBookings]);
            $event = $complimentaryBookings->ticket->event;
        } elseif ($masterBookings) {
            $bookingIds = $masterBookings->booking_id;
            $relatedBookings = Booking::with('ticket.event.user', 'attendee')->whereIn('id', $bookingIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        } elseif ($amusementMasterBookings) {
            $bookingIds = $amusementMasterBookings->booking_id;
            $relatedBookings = AmusementMasterBooking::with('ticket.event.user', 'attendee')->whereIn('id', $bookingIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        } elseif ($agentMasterBookings) {
            $agentIds = $agentMasterBookings->booking_id;
            $relatedBookings = Agent::with('ticket.event.user', 'attendee')->whereIn('id', $agentIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        } elseif ($AccreditationMasterBooking) {
            $agentIds = $AccreditationMasterBooking->booking_id;
            $relatedBookings = AccreditationBooking::with('ticket.event.user', 'attendee')->whereIn('id', $agentIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        } elseif ($SponsorMasterBooking) {
            $agentIds = $SponsorMasterBooking->booking_id;       
            $relatedBookings = SponsorBooking::with('ticket.event.user', 'attendee')->whereIn('id', $agentIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        } elseif ($amusementAgentMasterBookings) {
            $agentIds = $amusementAgentMasterBookings->booking_id;
            $relatedBookings = AmusementAgentBooking::with('ticket.event.user', 'attendee')->whereIn('id', $agentIds)->get();
            if ($relatedBookings->isNotEmpty()) {
                $organizer = $relatedBookings->first()->ticket->event->user->id;
                $event = $relatedBookings->first()->ticket->event;
            }
        }

        return compact('organizer', 'relatedBookings', 'event');
    }

    private function logScanHistory($userId, $scannerId, $tokenId, $bookingSource = null)
    {
        $now = now()->toDateTimeString();
    
        $query = ScanHistory::where('user_id', $userId)
            ->where('scanner_id', $scannerId);
    
        if ($bookingSource !== null) {
            $query->where('booking_source', $bookingSource);
        }
    
        $history = $query->first();
    
        if ($history) {
            // Append scan time
            $times = json_decode($history->scan_time ?? '[]', true) ?: [];
            $times[] = $now;
            $history->scan_time = json_encode($times);
    
            // Ensure token is always an array
            $tokens = json_decode($history->token ?? '[]', true) ?: [];
            if (!in_array($tokenId, $tokens)) {
                $tokens[] = $tokenId;
            }
            $history->token = json_encode($tokens);
    
            $history->count += 1;
            $history->save();
        } else {
            // Create new entry with token array
            $history = ScanHistory::create([
                'user_id' => $userId,
                'scanner_id' => $scannerId,
                'token' => json_encode([$tokenId]), // Store as JSON array
                'booking_source' => $bookingSource,
                'scan_time' => json_encode([$now]),
                'count' => 1,
            ]);
        }
    
        return $history;
    }
    
    // private function logScanHistory($userId, $scannerId, $tokenId, $bookingSource = null)
    // {
    //     $now = now()->toDateTimeString();

    //     $query = ScanHistory::where('user_id', $userId)
    //         ->where('scanner_id', $scannerId);

    //     if ($bookingSource !== null) {
    //         $query->where('booking_source', $bookingSource);
    //     }

    //     $history = $query->first();


    //     if ($history) {
    //         $times = json_decode($history->scan_time ?? '[]', true);
    //         $times[] = $now;


    //         $history->scan_time = json_encode($times);
    //         $history->count += 1;
    //         $history->token = $tokenId;
    //         $history->save();
    //     } else {
    //         $history = ScanHistory::create([
    //             'user_id' => $userId,
    //             'scanner_id' => $scannerId,
    //             'token' => $tokenId,
    //             'booking_source' => $bookingSource,
    //             'scan_time' => json_encode([$now]),
    //             'count' => 1,
    //         ]);
    //     }

    //     return $history;
    // }

    // public function attendeesChekIn($orderId)
    // {
    //     try {
    //         $attendee = Attndy::where('token', $orderId)->with('event.user')->first();
    //         if (!$attendee) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No attendee found with this token'
    //             ], 404);
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'bookings' => $attendee
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'An error occurred: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function attendeesChekIn($orderId)
    {
        try {
            // First: Try to fetch from Attndy table
            $attendee = Attndy::where('token', $orderId)->with('event.user')->first();

            // If not found in Attndy, try in CorporateBooking
            if (!$attendee) {
                $corporate = CorporateUser::where('token', $orderId)->first();

                if (!$corporate) {
                    return response()->json([
                        'status' => false,
                        'message' => 'No attendee found with this token'
                    ], 404);
                }

                return response()->json([
                    'status' => true,
                    'bookings' => $corporate,
                    'source' => 'corporate'
                ], 200);
            }

            return response()->json([
                'status' => true,
                'bookings' => $attendee,
                'source' => 'attndy'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function attendeesVerify($orderId)
    {
        try {
            // First try in Attndy table
            $attendee = Attndy::where('token', $orderId)->with('event.user')->first();

            if ($attendee) {
                $attendee->status = true;
                $attendee->save();

                //$this->logScanHistory($attendee->user_id, auth()->id(), $attendee->token, 'attendee');

                return response()->json([
                    'status' => true,
                    'bookings' => $attendee,
                    'source' => 'attndy'
                ], 200);
            }

            // If not found in Attndy, try CorporateBooking table
            $corporate = CorporateUser::where('token', $orderId)->with('event.user')->first();

            if ($corporate) {
                $corporate->status = true;
                $corporate->save();

                // $this->logScanHistory($corporate->user_id, auth()->id(), $corporate->token, 'corporate');

                return response()->json([
                    'status' => true,
                    'bookings' => $corporate,
                    'source' => 'corporate'
                ], 200);
            }

            // If not found in both
            return response()->json([
                'status' => false,
                'message' => 'No attendee found with this token'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }


    // public function attendeesVerify($orderId)
    // {
    //     try {
    //         $attendee = Attndy::where('token', $orderId)->with('event.user')->first();
    //         if (!$attendee) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No attendee found with this token'
    //             ], 404);
    //         }
    //         $attendee->status = true;

    //         $attendee->save();

    //         $history = $this->logScanHistory($attendee->user_id, auth()->id(), $attendee->token, 'attendee');


    //         return response()->json([
    //             'status' => true,
    //             'bookings' => $attendee
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'An error occurred: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }
  
      public function getScanHistories(Request $request)
    {
        try {
            if ($request->has('date')) {
                $dates = explode(',', $request->date);

                if (count($dates) === 1 || ($dates[0] === $dates[1])) {
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[0])->endOfDay();
                } elseif (count($dates) === 2) {
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[1])->endOfDay();
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid date format'
                    ], 400);
                }
            } else {
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
            }

            $user = auth()->user();

            $histories = ScanHistory::with(['user:id,name', 'scanner:id,name'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $histories
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
