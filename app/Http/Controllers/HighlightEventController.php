<?php

namespace App\Http\Controllers;

use App\Models\HighlightEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HighlightEventController extends Controller
{

    public function index()
    {
        $HighlightEvent = HighlightEvent::get();
        if ($HighlightEvent->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'HighlightEvent  not found'
            ], 404);
        }
        return response()->json([
            'status' => true,
            'HighlightEventData' => $HighlightEvent,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            // return response()->json($request->all());
            $maxSrNo = HighlightEvent::max('sr_no');
            $srNo = $maxSrNo ? $maxSrNo + 1 : 1;

            $HighlightEventData = new HighlightEvent();
            $HighlightEventData->sr_no = $srNo;
            $HighlightEventData->category = $request->category;
            $HighlightEventData->title = $request->title;
            $HighlightEventData->description = $request->description;
            $HighlightEventData->sub_description = $request->sub_description;
            $HighlightEventData->button_link = $request->button_link;
            $HighlightEventData->button_text = $request->button_text;
            $HighlightEventData->external_url = $request->external_url;

            if ($request->hasFile('images') && $request->file('images')->isValid()) {
                $image = $request->file('images');
                $fileName = 'get-your-ticket-' . uniqid() . '_' . $image->getClientOriginalName();
                $folder = 'HighlightEvent';
                $imagePath = $image->storeAs($folder, $fileName, 'public');
                $HighlightEventData->images = asset("/$imagePath");
            }


            $HighlightEventData->save();
            return response()->json(['status' => true, 'message' => 'HighlightEvent craete successfully', 'HighlightEvent' => $HighlightEventData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to HighlightEvent '], 404);
        }
    }

    public function show(string $id)
    {
        $HighlightEventData = HighlightEvent::find($id);

        if (!$HighlightEventData) {
            return response()->json(['status' => false, 'message' => 'HighlightEvent not found'], 404);
        }

        return response()->json(['status' => true, 'HighlightEventData' => $HighlightEventData], 200);
    }

    public function update(Request $request, string $id)
    {
        try {
            $HighlightEventData = HighlightEvent::findOrFail($id);

            $HighlightEventData->category = $request->category;
            $HighlightEventData->title = $request->title;
            $HighlightEventData->description = $request->description;
            $HighlightEventData->sub_description = $request->sub_description;
            $HighlightEventData->button_link = $request->button_link;
            $HighlightEventData->button_text = $request->button_text;
            $HighlightEventData->external_url = $request->external_url;

            if ($request->hasFile('images') && $request->file('images')->isValid()) {
                $image = $request->file('images');
                $fileName = 'get-your-ticket-' . uniqid() . '_' . $image->getClientOriginalName();
                $folder = 'HighlightEvent';
                $imagePath = $image->storeAs($folder, $fileName, 'public');
                $HighlightEventData->images = asset("$imagePath");
            }

            $HighlightEventData->save();

            return response()->json(['status' => true, 'message' => 'HighlightEvent updated successfully', 'HighlightEvent' => $HighlightEventData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update HighlightEvent'], 404);
        }
    }

    public function destroy(string $id)
    {
        $HighlightEventData = HighlightEvent::where('id', $id)->firstOrFail();
        if (!$HighlightEventData) {
            return response()->json(['status' => false, 'message' => 'HighlightEvent not found'], 404);
        }

        $HighlightEventData->delete();
        return response()->json(['status' => true, 'message' => 'HighlightEventData deleted successfully'], 200);
    }

    public function rearrangeHighlightEvent(Request $request)
    {
        try {
            $srNoCount = [];

            foreach ($request->data as $item) {
                if (isset($srNoCount[$item['sr_no']])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Duplicate sr_no values detected: ' . $item['sr_no'],
                    ], 400);
                }
                $srNoCount[$item['sr_no']] = true;
            }

            foreach ($request->data as $item) {
                $HighlightEventData = HighlightEvent::findOrFail($item['id']);
                $HighlightEventData->sr_no = $item['sr_no'];
                $HighlightEventData->save();
            }

            $updatedHighlightEventData = HighlightEvent::orderBy('sr_no')->get();

            return response()->json([
                'status' => true,
                'message' => 'HighlightEventData rearranged successfully',
                'data' => $updatedHighlightEventData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to rearrange HighlightEventData',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    private function storeFile($file, $folder, $disk = 'public')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
        return Storage::disk($disk)->url($path);
    }

}
