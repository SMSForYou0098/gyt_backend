<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{

    public function index($type)
    {
        $Banner = Banner::where('type', $type)->get();
        if ($Banner->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Banner  not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'BannerData' => $Banner,
        ], 200);
    }

    public function store(Request $request, $type)
    {
        try {
            // return response()->json($request->all());
            $maxSrNo = Banner::max('sr_no');
            $srNo = $maxSrNo ? $maxSrNo + 1 : 1;

            $bannerData = new Banner();
            $bannerData->sr_no = $srNo;
            $bannerData->type = $type;
            $bannerData->category = $request->category;
            $bannerData->title = $request->title;
            $bannerData->description = $request->description;
            $bannerData->sub_description = $request->sub_description;
            $bannerData->button_link = $request->button_link;
            $bannerData->button_text = $request->button_text;
            $bannerData->external_url = $request->external_url;
            $bannerData->event_id = $request->event_id;
            $bannerData->event_key = $request->event_key;

            if ($request->hasFile('images') && $request->file('images')->isValid()) {
                $file = $request->file('images');
                $fileName = 'get-your-ticket-' . uniqid() . '-' . $file->getClientOriginalName();
                $folder = 'uploads/banners';
                $file->move(public_path($folder), $fileName);
                $imagePath = url($folder . '/' . $fileName);

                $bannerData->images = $imagePath;
            }
            if ($request->hasFile('sm_image') && $request->file('sm_image')->isValid()) {
                $file = $request->file('sm_image');
                $fileName = 'get-your-ticket-' . uniqid() . '-' . $file->getClientOriginalName();
                $folder = 'uploads/banners/sm_image';
                $file->move(public_path($folder), $fileName);
                $imagePath = url($folder . '/' . $fileName);

                $bannerData->sm_image = $imagePath;
            }
            if ($request->hasFile('md_image') && $request->file('md_image')->isValid()) {
                $file = $request->file('md_image');
                $fileName = 'get-your-ticket-' . uniqid() . '-' . $file->getClientOriginalName();
                $folder = 'uploads/banners/md_image';
                $file->move(public_path($folder), $fileName);
                $imagePath = url($folder . '/' . $fileName);

                $bannerData->md_image = $imagePath;
            }

            $bannerData->save();
            return response()->json(['status' => true, 'message' => 'bannerData craete successfully', 'bannerData' => $bannerData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to bannerData '], 404);
        }
    }

    public function show(string $id)
    {
        $bannerData = Banner::find($id);

        if (!$bannerData) {
            return response()->json(['status' => false, 'message' => 'bannerData not found'], 200);
        }

        return response()->json(['status' => true, 'bannerData' => $bannerData], 200);
    }

    public function update(Request $request, string $type, string $id)
    {
        try {
            // Fetch banner based on type and ID
            $bannerData = Banner::where('type', $type)->findOrFail($id);

            // Update data fields
            $bannerData->category = $request->category;
            $bannerData->title = $request->title;
            $bannerData->description = $request->description;
            $bannerData->sub_description = $request->sub_description;
            $bannerData->button_link = $request->button_link;
            $bannerData->button_text = $request->button_text;
            $bannerData->external_url = $request->external_url;
            $bannerData->event_id = $request->event_id;
            $bannerData->event_key = $request->event_key;

            // Function to delete old image
            function deleteOldImage($imageUrl)
            {
                if (!empty($imageUrl)) {
                    $oldImagePath = str_replace(url('/') . '/', '', $imageUrl);
                    if (file_exists(public_path($oldImagePath))) {
                        unlink(public_path($oldImagePath));
                    }
                }
            }

            // Handle image updates
            if ($request->hasFile('images') && $request->file('images')->isValid()) {
                deleteOldImage($bannerData->images);
                $file = $request->file('images');
                $fileName = 'get-your-ticket-' . uniqid() . '-' . $file->getClientOriginalName();
                $folder = 'uploads/banners';
                $file->move(public_path($folder), $fileName);
                $bannerData->images = url($folder . '/' . $fileName);
            }

            if ($request->hasFile('sm_image') && $request->file('sm_image')->isValid()) {
                deleteOldImage($bannerData->sm_image);
                $file = $request->file('sm_image');
                $fileName = 'get-your-ticket-' . uniqid() . '-' . $file->getClientOriginalName();
                $folder = 'uploads/banners/sm_image';
                $file->move(public_path($folder), $fileName);
                $bannerData->sm_image = url($folder . '/' . $fileName);
            }

            if ($request->hasFile('md_image') && $request->file('md_image')->isValid()) {
                deleteOldImage($bannerData->md_image);
                $file = $request->file('md_image');
                $fileName = 'get-your-ticket-' . uniqid() . '-' . $file->getClientOriginalName();
                $folder = 'uploads/banners/md_image';
                $file->move(public_path($folder), $fileName);
                $bannerData->md_image = url($folder . '/' . $fileName);
            }

            // Save the updated banner
            $bannerData->save();

            return response()->json([
                'status' => true,
                'message' => 'Banner updated successfully',
                'bannerData' => $bannerData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update banner',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function destroy(string $id)
    {
        $bannerData = Banner::where('id', $id)->firstOrFail();
        if (!$bannerData) {
            return response()->json(['status' => false, 'message' => 'bannerData not found'], 200);
        }

        $bannerData->delete();
        return response()->json(['status' => true, 'message' => 'bannerData deleted successfully'], 200);
    }

    public function rearrangeBanner(Request $request, $type)
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
                $bannerData = Banner::where('type', $type)->findOrFail($item['id']);
                $bannerData->sr_no = $item['sr_no'];
                $bannerData->save();
            }

            $updatedBannerData = Banner::where('type', $type)->orderBy('sr_no')->get();

            return response()->json([
                'status' => true,
                'message' => 'Banner data rearranged successfully',
                'data' => $updatedBannerData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to rearrange banner data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function storeFile($file, $folder, $disk = 'public')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
        return Storage::disk($disk)->url($path);
    }
}
