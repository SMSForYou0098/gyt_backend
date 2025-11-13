<?php

namespace App\Http\Controllers;

use App\Models\LRow;
use App\Models\LSeat;
use App\Models\LSection;
use App\Models\LTier;
use App\Models\LVenue;
use App\Models\LZone;
use Illuminate\Http\Request;

class LVenueController extends Controller
{
    public function index()
    {
        $venueData = LVenue::get();
        if ($venueData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'venueData not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $venueData,
        ], 200);
    }

    // public function store(Request $request)
    // {

    //     try {
    //         $venueData = new LVenue();
    //         $venueData->name = $request->name;
    //         $venueData->location = $request->location;
    //         $venueData->venue_type = $request->venue_type;
    //         $venueData->capacity = $request->capacity;

    //         $venueData->save();
    //         return response()->json(['status' => true, 'message' => 'venueData craete successfully', 'data' => $venueData], 200);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => false, 'message' => 'Failed to venueData '], 404);
    //     }
    // }]

    // public function show($id)
    // {
    //     $venueData = LVenue::find($id);

    //     if (!$venueData) {
    //         return response()->json(['status' => false, 'message' => 'venueData not found'], 200);
    //     }

    //     return response()->json(['status' => true, 'data' => $venueData], 200);
    // }

    // public function update(Request $request, $id)
    // {
    //     try {
    //         $venueData = LVenue::findOrFail($id);

    //         $venueData->name = $request->name;
    //         $venueData->location = $request->location;
    //         $venueData->venue_type = $request->venue_type;
    //         $venueData->capacity = $request->capacity;
    //         $venueData->save();

    //         return response()->json(['status' => true, 'message' => 'venueData updated successfully', 'data' => $venueData], 200);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => false, 'message' => 'Failed to update venueData'], 404);
    //     }
    // }

    public function destroy(string $id)
    {
        $venueData = LVenue::where('id', $id)->firstOrFail();
        if (!$venueData) {
            return response()->json(['status' => false, 'message' => 'venueData not found'], 200);
        }

        $venueData->delete();
        return response()->json(['status' => true, 'message' => 'venueData deleted successfully'], 200);
    }

    public function store(Request $request)
    {
        try {
            $stadiumConfig = $request->stadiumConfig;

            if (!$stadiumConfig || !is_array($stadiumConfig['stands'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid or missing stands data in stadiumConfig'
                ], 400);
            }

            // Create Venue
            $venue = new LVenue();
            $venue->name = $stadiumConfig['stadiumName'] ?? $request->stadiumName;
            $venue->location = $stadiumConfig['location'] ?? $request->location;
            $venue->venue_type = 'stadium';
            $venue->capacity = $stadiumConfig['stadiumCapacity'] ?? 0;
            $venue->save();

            // Save Stands
            foreach ($stadiumConfig['stands'] as $standData) {
                $stand = new LZone();
                $stand->venue_id = $venue->id;
                $stand->name = $standData['name'] ?? null;
                // $stand->capacity = $standData['capacity'] ?? null;
                $stand->type = $standData['type'] ?? 'stand';
                $stand->is_blocked = $standData['isBlocked'] ?? false;
                $stand->save();

                // Save Tiers
                if (!empty($standData['tiers']) && is_array($standData['tiers'])) {
                    foreach ($standData['tiers'] as $tierData) {
                        $tier = new LTier();
                        $tier->zone_id = $stand->id;
                        $tier->name = $tierData['name'] ?? null;
                        $tier->is_blocked = $tierData['isBlocked'] ?? false;
                        $tier->price = $tierData['price'] ?? null;
                        // $tier->capacity = $tierData['capacity'] ?? null;
                        $tier->save();

                        // Save Sections
                        if (!empty($tierData['sections']) && is_array($tierData['sections'])) {
                            foreach ($tierData['sections'] as $sectionData) {
                                $section = new LSection();
                                $section->tier_id = $tier->id;
                                $section->name = $sectionData['name'] ?? null;
                                // $section->capacity = $sectionData['capacity'] ?? null;
                                $section->is_blocked = $sectionData['isBlocked'] ?? false;
                                $section->save();

                                // Save Rows
                                if (!empty($sectionData['rows']) && is_array($sectionData['rows'])) {
                                    foreach ($sectionData['rows'] as $rowData) {
                                        $row = new LRow();
                                        $row->section_id = $section->id;
                                        $row->label = $rowData['label'] ?? null;
                                        $row->seats = $rowData['seats'] ?? 0;
                                        $row->is_blocked = $rowData['isBlocked'] ?? false;
                                        $row->save();

                                        // 🔁 Save Seats (based on number of seats in the row)
                                        $seatCount = $rowData['seats'] ?? 0;
                                        for ($i = 1; $i <= $seatCount; $i++) {
                                            $seat = new LSeat();
                                            $seat->row_id = $row->id;
                                            $seat->number = "S{$i}"; // Seat number like S1, S2, ...
                                            $seat->status = 'active'; // Or use custom logic
                                            $seat->is_booked = false;
                                            $seat->price = $tier->price ?? null;
                                            $seat->save();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Venue structure stored successfully',
                'data' => $venue
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to store venue structure',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $stadiumConfig = $request->stadiumConfig;

            if (!$stadiumConfig || !is_array($stadiumConfig['stands'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid or missing stands data in stadiumConfig'
                ], 400);
            }

            $venue = LVenue::with('zones.tiers.sections.rows.seats')->find($id);
            if (!$venue) {
                return response()->json([
                    'status' => false,
                    'message' => 'Venue not found'
                ], 404);
            }

            // Update venue info
            $venue->name = $stadiumConfig['stadiumName'] ?? $venue->name;
            $venue->location = $stadiumConfig['location'] ?? $venue->location;
            $venue->capacity = $stadiumConfig['stadiumCapacity'] ?? $venue->capacity;
            $venue->save();

            $standIdsFromRequest = collect($stadiumConfig['stands'])->pluck('id')->filter()->toArray();
            foreach ($venue->zones as $zone) {
                if (!in_array($zone->id, $standIdsFromRequest)) {
                    foreach ($zone->tiers as $tier) {
                        foreach ($tier->sections as $section) {
                            foreach ($section->rows as $row) {
                                LSeat::where('row_id', $row->id)->delete();
                            }
                            LRow::where('section_id', $section->id)->delete();
                        }
                        LSection::where('tier_id', $tier->id)->delete();
                    }
                    LTier::where('zone_id', $zone->id)->delete();
                    $zone->delete();
                }
            }

            // Handle stands
            foreach ($stadiumConfig['stands'] as $standData) {
                $stand = isset($standData['id'])
                    ? LZone::find($standData['id'])
                    : new LZone(['venue_id' => $venue->id]);

                $stand->venue_id = $venue->id;
                $stand->name = $standData['name'];
                $stand->type = $standData['type'] ?? 'stand';
                $stand->is_blocked = $standData['isBlocked'] ?? false;
                $stand->save();

                $tierIdsFromRequest = collect($standData['tiers'])->pluck('id')->filter()->toArray();
                foreach ($stand->tiers as $tier) {
                    if (!in_array($tier->id, $tierIdsFromRequest)) {
                        foreach ($tier->sections as $section) {
                            foreach ($section->rows as $row) {
                                LSeat::where('row_id', $row->id)->delete();
                            }
                            LRow::where('section_id', $section->id)->delete();
                        }
                        LSection::where('tier_id', $tier->id)->delete();
                        $tier->delete();
                    }
                }

                // Handle tiers
                foreach ($standData['tiers'] as $tierData) {
                    $tier = isset($tierData['id'])
                        ? LTier::find($tierData['id'])
                        : new LTier(['zone_id' => $stand->id]);

                    $tier->zone_id = $stand->id;
                    $tier->name = $tierData['name'];
                    $tier->is_blocked = $tierData['isBlocked'] ?? false;
                    $tier->price = $tierData['price'] ?? null;
                    $tier->save();

                    $sectionIdsFromRequest = collect($tierData['sections'])->pluck('id')->filter()->toArray();
                    foreach ($tier->sections as $section) {
                        if (!in_array($section->id, $sectionIdsFromRequest)) {
                            foreach ($section->rows as $row) {
                                LSeat::where('row_id', $row->id)->delete();
                                $row->delete();
                            }
                            $section->delete();
                        }
                    }

                    // Handle sections
                    foreach ($tierData['sections'] as $sectionData) {
                        $section = isset($sectionData['id'])
                            ? LSection::find($sectionData['id'])
                            : new LSection(['tier_id' => $tier->id]);

                        $section->tier_id = $tier->id;
                        $section->name = $sectionData['name'];
                        $section->is_blocked = $sectionData['isBlocked'] ?? false;
                        $section->save();

                        $rowIdsFromRequest = collect($sectionData['rows'])->pluck('id')->filter()->toArray();
                        foreach ($section->rows as $row) {
                            if (!in_array($row->id, $rowIdsFromRequest)) {
                                LSeat::where('row_id', $row->id)->delete();
                                $row->delete();
                            }
                        }

                        // Handle rows
                        foreach ($sectionData['rows'] as $rowData) {
                            $row = isset($rowData['id'])
                                ? LRow::find($rowData['id'])
                                : new LRow(['section_id' => $section->id]);

                            $row->section_id = $section->id;
                            $row->label = $rowData['label'];
                            $row->is_blocked = $rowData['isBlocked'] ?? false;
                            $row->save();

                            // Handle seats
                            $existingSeats = $row->seats()->orderBy('number')->get();
                            $currentCount = $existingSeats->count();
                            $desiredCount = $rowData['seats'] ?? 0;

                            if ($desiredCount < $currentCount) {
                                $seatsToDelete = $existingSeats->slice($desiredCount)->pluck('id');
                                LSeat::whereIn('id', $seatsToDelete)->delete();
                            } elseif ($desiredCount > $currentCount) {
                                for ($i = $currentCount + 1; $i <= $desiredCount; $i++) {
                                    $seat = new LSeat();
                                    $seat->row_id = $row->id;
                                    $seat->number = "S{$i}";
                                    $seat->status = 'active';
                                    $seat->is_booked = false;
                                    $seat->price = $tier->price ?? null;
                                    $seat->save();
                                }
                            }

                            $row->seats = $desiredCount;
                            $row->save();
                        }
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Venue structure updated successfully',
                'data' => $venue->load('zones.tiers.sections.rows.seats')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update venue structure',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show($id)
    {
        $venueData = LVenue::with([
            'zones.tiers.sections.rows.seats'
        ])->find($id);

        if (!$venueData) {
            return response()->json(['status' => false, 'message' => 'Venue not found'], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $venueData
        ], 200);
    }
}       
