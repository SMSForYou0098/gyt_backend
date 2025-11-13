<?php

namespace App\Http\Controllers;

use App\Models\LSeat;
use Illuminate\Http\Request;

class LSeatController extends Controller
{
    public function index()
    {
        $seatData = LSeat::get();
        if ($seatData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'seatData not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $seatData,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $seatData = new LSeat();
            $seatData->row_id = $request->row_id;
            $seatData->number = $request->number;
            $seatData->status = $request->status;
            $seatData->is_booked = $request->is_booked;
            $seatData->price = $request->price;

            $seatData->save();
            return response()->json(['status' => true, 'message' => 'seatData craete successfully', 'data' => $seatData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to seatData '], 404);
        }
    }

    public function show($id)
    {
        $seatData = LSeat::find($id);

        if (!$seatData) {
            return response()->json(['status' => false, 'message' => 'seatData not found'], 200);
        }

        return response()->json(['status' => true, 'data' => $seatData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $seatData = LSeat::findOrFail($id);

            $seatData->row_id = $request->row_id;
            $seatData->number = $request->number;
            $seatData->status = $request->status;
            $seatData->is_booked = $request->is_booked;
            $seatData->price = $request->price;
            $seatData->save();

            return response()->json(['status' => true, 'message' => 'seatData updated successfully', 'data' => $seatData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update seatData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $seatData = LSeat::where('id', $id)->firstOrFail();
        if (!$seatData) {
            return response()->json(['status' => false, 'message' => 'seatData not found'], 200);
        }

        $seatData->delete();
        return response()->json(['status' => true, 'message' => 'seatData deleted successfully'], 200);
    }
}
