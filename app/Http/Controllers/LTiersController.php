<?php

namespace App\Http\Controllers;

use App\Models\LTier;
use Illuminate\Http\Request;

class LTiersController extends Controller
{
    public function index()
    {
        $tierData = LTier::get();
        if ($tierData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'tierData not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $tierData,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $tierData = new LTier();
            $tierData->zone_id = $request->zone_id;
            $tierData->name = $request->name;
            $tierData->is_blocked = $request->is_blocked;
            $tierData->price = $request->price;

            $tierData->save();
            return response()->json(['status' => true, 'message' => 'tierData craete successfully', 'data' => $tierData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to tierData '], 404);
        }
    }

    public function show($id)
    {
        $tierData = LTier::find($id);

        if (!$tierData) {
            return response()->json(['status' => false, 'message' => 'tierData not found'], 200);
        }

        return response()->json(['status' => true, 'data' => $tierData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $tierData = LTier::findOrFail($id);

            $tierData->zone_id = $request->zone_id;
            $tierData->name = $request->name;
            $tierData->is_blocked = $request->is_blocked;
            $tierData->price = $request->price;
            $tierData->save();

            return response()->json(['status' => true, 'message' => 'tierData updated successfully', 'data' => $tierData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update tierData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $tierData = LTier::where('id', $id)->firstOrFail();
        if (!$tierData) {
            return response()->json(['status' => false, 'message' => 'tierData not found'], 200);
        }

        $tierData->delete();
        return response()->json(['status' => true, 'message' => 'tierData deleted successfully'], 200);
    }
}
