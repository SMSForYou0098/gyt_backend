<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\SuccessfulEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SuccessfulEventController extends Controller
{

    public function index()
    {
        try {
            $eventData = SuccessfulEvent::all();
            return response()->json([
                'status' => true,
                'message' => 'Event  Successfully',
                'eventData' => $eventData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch event data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $event = new SuccessfulEvent();

            $eventDirectory = "system/SuccessfulEvent";

            if ($request->hasFile('thumbnail')) {
                $file = $request->file('thumbnail');
                $eventDirectory = 'event_thumbnails';
                $fileName = 'get-your-ticket-' . uniqid() . '_' . $file->getClientOriginalName();
                $storedPath = $file->storeAs("uploads/$eventDirectory", $fileName, 'public');
                $event->thumbnail = Storage::disk('public')->url($storedPath);
            }

            $event->user_id = Auth()->id();
            $event->url = $request->url;

            $event->save();

            return response()->json([
                'status' => true,
                'message' => 'Event Updated Successfully',
                'event' => $event
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update event',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function show(string $id)
    {
        //
    }


    public function edit(string $id)
    {
        //
    }


    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        $SuccessfulEvent = SuccessfulEvent::where('id', $id)->firstOrFail();
        if (!$SuccessfulEvent) {
            return response()->json(['status' => false, 'message' => 'SuccessfulEvent not found'], 404);
        }

        $SuccessfulEvent->delete();
        return response()->json(['status' => true, 'message' => 'SuccessfulEvent deleted successfully'], 200);
    }

    private function storeFile($file, $folder, $disk = 'public')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
        return Storage::disk($disk)->url($path);
    }

    // public function getExpiredEvents(Request $request)
    // {
    //     $today = Carbon::today()->toDateString();
    //     $eventsQuery = Event::query();

    //     // Fetch events
    //     $events = $eventsQuery
    //         ->select('id', 'name', 'thumbnail', 'date_range','user_id','event_key','city')
    //             ->where('status', 1)
    //             ->orderBy('id', 'desc')   // latest events
    //             ->limit(6)
    //         ->get();

    //     $pastEvents = [];

    //     foreach ($events as $event) {
    //         $dateRange = explode(',', $event->date_range);

    //         if (count($dateRange) == 1) {
    //             // Single-day event
    //             $eventDate = Carbon::parse(trim($dateRange[0]));
    //             $isPast = $today > $eventDate->toDateString();
    //         } else {
    //             // Multi-day event
    //             $endDate = Carbon::parse(trim($dateRange[1]));
    //             $isPast = $today > $endDate->toDateString();
    //         }

    //         if ($isPast) {
    //             $pastEvents[] = [
    //                 'id'         => $event->id,
    //                 'name'       => $event->name,
    //                 'thumbnail'  => $event->thumbnail,
    //                 'date_range' => $event->date_range,
    //                 'user_id' => $event->user_id,
    //                 'event_key' => $event->event_key,
    //                 'city' => $event->city,
    //             ];
    //         }
    //     }

    //     if (empty($pastEvents)) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'No past events found'
    //         ], 200);
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'events' => $pastEvents
    //     ], 200);
    // }

public function getExpiredEvents(Request $request)
{
    $today = Carbon::today()->toDateString();

    // Fetch all active events
    $events = Event::with(['user:id,name'])
        ->select('id', 'name', 'thumbnail', 'date_range', 'user_id', 'event_key', 'city')
        //->where('status', 1)
        ->orderBy('id', 'desc')
        ->get();

    $pastEvents = [];

    foreach ($events as $event) {
        $dateRange = explode(',', $event->date_range);

        if (count($dateRange) == 1) {
            // Single-day event
            $eventDate = Carbon::parse(trim($dateRange[0]));
            $isPast = $today > $eventDate->toDateString();
        } else {
            // Multi-day event
            $endDate = Carbon::parse(trim($dateRange[1]));
            $isPast = $today > $endDate->toDateString();
        }

        if ($isPast) {
            $pastEvents[] = [
                'id'         => $event->id,
                'name'       => $event->name,
                'thumbnail'  => $event->thumbnail,
                'date_range' => $event->date_range,
                'event_key'  => $event->event_key,
                'city'       => $event->city,
                'user'       => $event->user,
            ];
        }

        // Stop once we have 6 expired events
        if (count($pastEvents) >= 6) {
            break;
        }
    }

    if (empty($pastEvents)) {
        return response()->json([
            'status'  => false,
            'message' => 'No past events found'
        ], 200);
    }

    return response()->json([
        'status' => true,
        'events' => $pastEvents
    ], 200);
}

}
