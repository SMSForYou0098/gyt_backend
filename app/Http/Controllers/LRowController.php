<?php

namespace App\Http\Controllers;

use App\Models\LRow;
use Illuminate\Http\Request;

class LRowController extends Controller
{
    public function index()
    {
        $rowData = LRow::get();
        if ($rowData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'rowData not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $rowData,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $rowData = new LRow();
            $rowData->section_id = $request->section_id;
            $rowData->label = $request->label;
            $rowData->seats = $request->seats;
            $rowData->is_blocked = $request->is_blocked;

            $rowData->save();
            return response()->json(['status' => true, 'message' => 'rowData craete successfully', 'data' => $rowData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to rowData '], 404);
        }
    }

    public function show($id)
    {
        $rowData = LRow::find($id);

        if (!$rowData) {
            return response()->json(['status' => false, 'message' => 'rowData not found'], 200);
        }

        return response()->json(['status' => true, 'data' => $rowData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $rowData = LRow::findOrFail($id);

            $rowData->section_id = $request->section_id;
            $rowData->label = $request->label;
            $rowData->seats = $request->seats;
            $rowData->is_blocked = $request->is_blocked;
            $rowData->save();

            return response()->json(['status' => true, 'message' => 'rowData updated successfully', 'data' => $rowData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update rowData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $rowData = LRow::where('id', $id)->firstOrFail();
        if (!$rowData) {
            return response()->json(['status' => false, 'message' => 'rowData not found'], 200);
        }

        $rowData->delete();
        return response()->json(['status' => true, 'message' => 'rowData deleted successfully'], 200);
    }
}
