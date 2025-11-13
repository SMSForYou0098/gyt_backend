<?php

namespace App\Http\Controllers;

use App\Models\LSection;
use Illuminate\Http\Request;

class LSectionController extends Controller
{
    public function index()
    {
        $sectionData = LSection::get();
        if ($sectionData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'sectionData not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $sectionData,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $sectionData = new LSection();
            $sectionData->tier_id = $request->tier_id;
            $sectionData->name = $request->name;
            $sectionData->is_blocked = $request->is_blocked;

            $sectionData->save();
            return response()->json(['status' => true, 'message' => 'sectionData craete successfully', 'data' => $sectionData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to sectionData '], 404);
        }
    }

    public function show($id)
    {
        $sectionData = LSection::find($id);

        if (!$sectionData) {
            return response()->json(['status' => false, 'message' => 'sectionData not found'], 200);
        }

        return response()->json(['status' => true, 'data' => $sectionData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $sectionData = LSection::findOrFail($id);

            $sectionData->tier_id = $request->tier_id;
            $sectionData->name = $request->name;
            $sectionData->is_blocked = $request->is_blocked;
            $sectionData->save();

            return response()->json(['status' => true, 'message' => 'sectionData updated successfully', 'data' => $sectionData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update sectionData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $sectionData = LSection::where('id', $id)->firstOrFail();
        if (!$sectionData) {
            return response()->json(['status' => false, 'message' => 'sectionData not found'], 200);
        }

        $sectionData->delete();
        return response()->json(['status' => true, 'message' => 'sectionData deleted successfully'], 200);
    }
}
