<?php

namespace App\Http\Controllers;

use App\Models\WelcomePopUp;
use Illuminate\Http\Request;
use Storage;

class PopUpController extends Controller
{

    public function index()
    {
        try {
            $WelcomePopUp = WelcomePopUp::first();

            if (!$WelcomePopUp) { // Check if no record is found
                return response()->json([
                    'status' => false,
                    'message' => 'WelcomePopUp not found'
                ], 404);
            }

            return response()->json([
                'status' => $WelcomePopUp->status == 1 ? true : false,
                'data' => $WelcomePopUp,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 404);
        }
    }


    public function store(Request $request)
    {
        try {

            $PopUpData = WelcomePopUp::firstOrNew([]);
            $PopUpData->url = $request->url;
            $PopUpData->sm_url = $request->sm_url;
            $PopUpData->text = $request->text;
            $PopUpData->sm_text = $request->sm_text;
            $PopUpData->status = $request->status;

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $file = $request->file('image');
                $fileName = 'get-your-ticket-' . uniqid() . '-' . $file->getClientOriginalName();
                $folder = 'uploads/PopUp';
                $file->move(public_path($folder), $fileName);
                $imagePath = url($folder . '/' . $fileName);

                $PopUpData->image = $imagePath;
            }
            if ($request->hasFile('sm_image') && $request->file('sm_image')->isValid()) {
                $file = $request->file('sm_image');
                $fileName = 'get-your-ticket-' . uniqid() . '-' . $file->getClientOriginalName();
                $folder = 'uploads/PopUp';
                $file->move(public_path($folder), $fileName);
                $imagePath = url($folder . '/' . $fileName);

                $PopUpData->sm_image = $imagePath;
            }

            $PopUpData->save();
            return response()->json(['status' => true, 'message' => 'WelcomePopUpData craete successfully', 'data' => $PopUpData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage(),], 404);
        }
    }


    public function show(string $id)
    {
        $PopUpData = WelcomePopUp::find($id);

        if (!$PopUpData) {
            return response()->json(['status' => false, 'message' => 'WelcomePopUp not found'], 404);
        }

        return response()->json(['status' => true, 'WelcomePopUpData' => $PopUpData], 200);
    }


    public function update(Request $request, string $id)
    {
        try {
            $PopUpData = WelcomePopUp::findOrFail($id);

            $PopUpData->url = $request->url;
            $PopUpData->sm_url = $request->sm_url;
            $PopUpData->text = $request->text;
            $PopUpData->sm_text = $request->sm_text;

            if ($request->hasFile('images')) {
                $fileName = 'get-your-ticket-' . time() . '-' . $request->file('images')->getClientOriginalName();
                $filePath = $request->file('images')->storeFile("PopUp", $fileName, 'public');
                $PopUpData->images = $filePath;
            }
            if ($request->hasFile('sm_images')) {
                $fileName = 'get-your-ticket-' . time() . '-' . $request->file('sm_images')->getClientOriginalName();
                $filePath = $request->file('sm_images')->storeFile("PopUp", $fileName, 'public');
                $PopUpData->sm_images = $filePath;
            }


            $PopUpData->save();

            return response()->json(['status' => true, 'message' => 'WelcomePopUp updated successfully', 'WelcomePopUp' => $PopUpData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update WelcomePopUp'], 404);
        }
    }

    public function destroy(string $id)
    {
        $PopUpData = WelcomePopUp::where('id', $id)->firstOrFail();
        if (!$PopUpData) {
            return response()->json(['status' => false, 'message' => 'WelcomePopUp not found'], 404);
        }

        $PopUpData->delete();
        return response()->json(['status' => true, 'message' => 'WelcomePopUp deleted successfully'], 200);
    }

    private function storeFile($file, $folder, $disk = 'public')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
        return Storage::disk($disk)->url($path);
    }
}
