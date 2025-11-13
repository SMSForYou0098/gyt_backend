<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Attndy;
use App\Models\SocialMedia;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Storage;


class SocialMediaController extends Controller
{

    public function index()
    {
        try {
            $mediaData = SocialMedia::first();

            return response()->json([
                'status' => true,
                'message' => 'SocialMediaData retrieved successfully',
                'SocialMediaData' => $mediaData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving SocialMediaData',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $event = SocialMedia::first();
            if (!$event) {
                $event = new SocialMedia();
            }

            // Update or create social media links
            $event->facebook = $request->facebook;
            $event->instagram = $request->instagram;
            $event->youtube = $request->youtube;
            $event->twitter = $request->twitter;
			$event->linkedin = $request->linkedin;
            $event->save();

            return response()->json(['status' => true, 'message' => 'Social Media Updated Successfully', 'data' => $event], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update Social Media', 'error' => $e->getMessage()], 500);
        }
    }


    public function show(string $id)
    {
        $socialData = SocialMedia::findOrFail($id);
        if (!$socialData) {
            return response()->json(['status' => false, 'message' => 'socialData not found'], 404);
        }
        return response()->json([
            'status' => true,
            'message' => 'socialData successfully',
            'socialData' => $socialData,
        ], 200);
    }


    public function update(Request $request, string $id)
    {
        try {
            $event = SocialMedia::findOrFail($id);
            $event->facebook = $request->facebook;
            $event->instagram = $request->instagram;
            $event->youtube = $request->youtube;
            $event->twitter = $request->twitter;
			$event->linkedin = $request->linkedin;
            $event->save();
            return response()->json(['status' => true, 'message' => 'Social Media Update Successfully', 'data' => $event], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create Social Media', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        $event = SocialMedia::findOrFail($id);
        if ($event) {
            $event->delete();
            return response()->json(['status' => true, 'message' => 'Social Media  deleted successfully'], 200);
        }
    }

     //dairect data base mathi data export
    public function importExcel(Request $request)
    {
        try {

            $data = Excel::toArray([], $request->file('file'));

            if (empty($data) || count($data[0]) == 0) {
                return response()->json(['status' => false, 'message' => 'Empty file or invalid format'], 400);
            }
            $userIds = collect($data[0])
                ->pluck(0)
                ->filter()
                ->values()
                ->toArray();

            $userNames = Attndy::whereIn('id', $userIds)
                ->pluck('name', 'id')
                ->toArray();

            $userNamesList = collect($userIds)->map(function ($id) use ($userNames) {
                return $userNames[$id] ?? null;
            })->toArray();

            return response()->json(['status' => true, 'data' => $userNamesList], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'error' => 'Failed to import file.', 'message' => $e->getMessage()], 500);
        }
    }
}
