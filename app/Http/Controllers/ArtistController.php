<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use Illuminate\Http\Request;
use Storage;

class ArtistController extends Controller
{

    public function index()
    {
        $Artist = Artist::paginate(10);
        if ($Artist->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Artist  not found'
            ], 404);
        }
        return response()->json([
            'status' => true,
            'ArtistData' => $Artist,
        ], 200);
    }

    public function store(Request $request)
    {
        try {

            $ActressData = new Artist();
            $ActressData->name = $request->name;
            $ActressData->description = $request->description;
            $ActressData->category = $request->category;

            if ($request->hasFile('photo')) {
                $categoryFolder = str_replace(' ', '_', strtolower($request->category));
                $originalName = $request->file('photo')->getClientOriginalName();
                $fileName = 'get-your-ticket-' . time() . '-' . $originalName;
                $filePath = $request->file('photo')->storeFile("Artist/$categoryFolder", $fileName, 'public');
                $ActressData->photo = $filePath;
            }

            $ActressData->save();
            return response()->json(['status' => true, 'message' => 'ArtistData craete successfully', 'artistData' => $ActressData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to ArtistData '], 404);
        }
    }

    public function show($id)
    {
        $ActressData = Artist::find($id);

        if (!$ActressData) {
            return response()->json(['status' => false, 'message' => 'Artist not found'], 404);
        }

        return response()->json(['status' => true, 'artistData' => $ActressData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $ActressData = Artist::findOrFail($id);

            $ActressData->name = $request->name;
            $ActressData->description = $request->description;
            $ActressData->category = $request->category;

            if ($request->hasFile('photo')) {
                $categoryFolder = str_replace(' ', '_', strtolower($request->category));
                $fileName = 'get-your-ticket-' . time() . '-' . $request->file('photo')->getClientOriginalName();
                $filePath = $request->file('photo')->storeFile("Artist/$categoryFolder", $fileName, 'public');
                $ActressData->photo = $filePath;
            }

            $ActressData->save();

            return response()->json(['status' => true, 'message' => 'artistData updated successfully', 'artistData' => $ActressData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update artistData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $Actress = Artist::where('id', $id)->firstOrFail();
        if (!$Actress) {
            return response()->json(['status' => false, 'message' => 'Artist not found'], 404);
        }

        $Actress->delete();
        return response()->json(['status' => true, 'message' => 'Artist deleted successfully'], 200);
    }

    private function storeFile($file, $folder, $disk = 'public')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
        return Storage::disk($disk)->url($path);
    }
}
