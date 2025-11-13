<?php

namespace App\Http\Controllers;

use App\Models\AccessArea;
use Illuminate\Http\Request;

class AccessAreaController extends Controller
{
    public function index($eventId)
    {
        $AccessArea = AccessArea::where('event_id', $eventId)->get();
        if ($AccessArea->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Access Area not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $AccessArea,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $accessAreaData = new AccessArea();
            $accessAreaData->title = $request->title;
            $accessAreaData->user_id = $request->user_id;
            $accessAreaData->event_id = $request->event_id;
            $accessAreaData->description = $request->description;

            $accessAreaData->save();
            return response()->json(['status' => true, 'message' => 'accessAreaData craete successfully', 'data' => $accessAreaData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to accessAreaData '], 404);
        }
    }

    public function show($id)
    {
        $accessAreaData = AccessArea::find($id);

        if (!$accessAreaData) {
            return response()->json(['status' => false, 'message' => 'accessAreaData not found'], 200);
        }

        return response()->json(['status' => true, 'data' => $accessAreaData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $accessAreaData = AccessArea::findOrFail($id);
            
            $accessAreaData->title = $request->title;
            $accessAreaData->user_id = $request->user_id;
            $accessAreaData->event_id = $request->event_id;
            $accessAreaData->description = $request->description;
            $accessAreaData->save();

            return response()->json(['status' => true, 'message' => 'accessAreaData updated successfully', 'data' => $accessAreaData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update accessAreaData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $accessAreaData = AccessArea::where('id', $id)->firstOrFail();
        if (!$accessAreaData) {
            return response()->json(['status' => false, 'message' => 'accessAreaData not found'], 404);
        }

        $accessAreaData->delete();
        return response()->json(['status' => true, 'message' => 'accessAreaData deleted successfully'], 200);
    }
}
