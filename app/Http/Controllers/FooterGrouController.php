<?php

namespace App\Http\Controllers;
use App\Models\FooterGroup;
use App\Models\FooterMenu;
use App\Models\Setting;
use App\Models\SocialMedia;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class FooterGrouController extends Controller
{

    public function index()
    {
        // $groupData = FooterGroup::with('FooterMenu')->get();
        $groupData = FooterGroup::select('id', 'title')
        ->with(['FooterMenu' => function($query) {
            $query->select('id', 'title', 'footer_group_id', 'page_id');
        }])->get();
        $footer = Setting::select('footer_logo','footer_address','footer_contact','site_credit','nav_logo','footer_whatsapp_number','footer_email')->first();
        $mediaData = SocialMedia::latest()->first();
        if ($groupData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'groupData  not found'
            ], 404);
        }
        return response()->json([
            'status' => true,
            'message' => 'groupData  successfully',
            'GroupData' => $groupData,
            'FooterData' => $footer,
            'SocialLinks' => $mediaData,
        ], 200);
    }

    public function store(Request $request)
    {
        try {

            $groupData = new FooterGroup();
            $groupData->title = $request->title;

            $groupData->save();
            // $groupData = FooterMenu::with('FooterMenu')->find($groupData->id);
            return response()->json(['status' => true,'message' => 'Footer Group craete successfully','GroupData' => $groupData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to Group Data '], 404);

        }
    }

    public function show(string $id)
    {
        $groupData = FooterGroup::with('FooterMenu')->findOrFail($id);
        if (!$groupData) {
            return response()->json(['status' => false, 'message' => 'groupData not found'], 404);
        }
        return response()->json([
            'status' => true,
            'message' => 'GroupData successfully',
            'GroupData' => $groupData,
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        try {
            $groupData = FooterGroup::with('FooterMenu')->findOrFail($id);

            $groupData->title = $request->title;

            $groupData->save();

            return response()->json(['status' => true, 'message' => 'Footer Group updated successfully', 'GroupData' => $groupData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update GroupData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $groupData = FooterGroup::where('id', $id)->firstOrFail();
        if (!$groupData) {
            return response()->json(['status' => false, 'message' => 'groupData not found'], 404);
        }

        $groupData->delete();
        return response()->json(['status' => true, 'message' => 'groupData deleted successfully'], 200);
    }
}
