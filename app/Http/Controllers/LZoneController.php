<?php

namespace App\Http\Controllers;

use App\Models\LZone;
use Illuminate\Http\Request;

class LZoneController extends Controller
{
    public function index()
    {
        $zoneData = LZone::get();
        if ($zoneData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'zoneData not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $zoneData,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $zoneData = new LZone();
            $zoneData->venue_id = $request->venue_id;
            $zoneData->name = $request->name;
            $zoneData->type = $request->type;
            $zoneData->is_blocked = $request->is_blocked;

            $zoneData->save();
            return response()->json(['status' => true, 'message' => 'zoneData craete successfully', 'data' => $zoneData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to zoneData '], 404);
        }
    }

    public function show($id)
    {
        $zoneData = LZone::find($id);

        if (!$zoneData) {
            return response()->json(['status' => false, 'message' => 'zoneData not found'], 200);
        }

        return response()->json(['status' => true, 'data' => $zoneData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $zoneData = LZone::findOrFail($id);

            $zoneData->name = $request->name;
            $zoneData->location = $request->location;
            $zoneData->venue_type = $request->venue_type;
            $zoneData->capacity = $request->capacity;
            $zoneData->save();

            return response()->json(['status' => true, 'message' => 'zoneData updated successfully', 'data' => $zoneData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update zoneData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $zoneData = LZone::where('id', $id)->firstOrFail();
        if (!$zoneData) {
            return response()->json(['status' => false, 'message' => 'zoneData not found'], 200);
        }

        $zoneData->delete();
        return response()->json(['status' => true, 'message' => 'zoneData deleted successfully'], 200);
    }
}
