<?php

namespace App\Http\Controllers;

use App\Models\EventGate;
use Illuminate\Http\Request;

class EventGetController extends Controller
{
    public function index($eventId)
    {
        $eventGates = EventGate::where('event_id', $eventId)->get();
        if ($eventGates->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Event Gate not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $eventGates,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $getData = new EventGate();
            $getData->user_id = $request->user_id;
            $getData->event_id = $request->event_id;
            $getData->title = $request->title;
            $getData->batch_id = $this->generateBatchId();
            $getData->description = $request->description;

            $getData->save();
            return response()->json(['status' => true, 'message' => 'Event Gate craete successfully', 'data' => $getData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to getData '], 404);
        }
    }

    public function show($id)
    {
        $getData = EventGate::find($id);

        if (!$getData) {
            return response()->json(['status' => false, 'message' => 'Event Gate not found'], 200);
        }

        return response()->json(['status' => true, 'data' => $getData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $getData = EventGate::findOrFail($id);

            $getData->user_id = $request->user_id;
            $getData->event_id = $request->event_id;
            $getData->title = $request->title;
            $getData->description = $request->description;

            $getData->save();

            return response()->json(['status' => true, 'message' => 'Event Gate updated successfully', 'data' => $getData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update getData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $getData = EventGate::where('id', $id)->firstOrFail();
        if (!$getData) {
            return response()->json(['status' => false, 'message' => 'Event Gate not found'], 404);
        }

        $getData->delete();
        return response()->json(['status' => true, 'message' => 'Event Gate deleted successfully'], 200);
    }

    private function generateBatchId()
    {
        $date = now()->format('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        return "BATCH{$date}-{$random}";
    }
}
