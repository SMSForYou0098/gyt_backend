<?php

namespace App\Http\Controllers;

use App\Models\FooterMenu;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class FooterMenuController extends Controller
{
    public function index($id)
    {
        try {
            $menuData = FooterMenu::with(['pages' => function ($query) {
                $query->select('id', 'title');
            }])
            ->where('footer_group_id', $id)
            ->select('id', 'title', 'page_id')
            ->get();
            if ($menuData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'menuData  not found'
                ], 404);
            }
            return response()->json([
                'status' => true,
                'message' => 'MenuData  successfully',
                'MenuData' => $menuData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request)
    {
        try {

            $menuData = new FooterMenu();
            $menuData->title = $request->title;
            $menuData->footer_group_id = $request->footer_group_id;
            $menuData->page_id = $request->page_id;

            $menuData->save();
            $menuData = FooterMenu::with('pages')->find($menuData->id);
            return response()->json(['status' => true, 'message' => 'Footer Menu craete successfully', 'MenuData' => $menuData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false,'message' => $e->getMessage()], 404);
        }
    }

    public function show(string $id)
    {
        $menuData = FooterMenu::with('pages')->findOrFail($id);
        if (!$menuData) {
            return response()->json(['status' => false, 'message' => 'MenuData not found'], 404);
        }
        return response()->json([
            'status' => true,
            'message' => 'MenuData successfully',
            'MenuData' => $menuData,
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        try {
            $menuData = FooterMenu::with('pages')->findOrFail($id);
            $menuData->title = $request->title;
            $menuData->footer_group_id = $request->footer_group_id;
            $menuData->page_id = $request->page_id;

            $menuData->save();
            return response()->json(['status' => true, 'message' => 'Footer Menu updated successfully', 'MenuData' => $menuData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function destroy(string $id)
    {
        $menuData = FooterMenu::where('id', $id)->firstOrFail();
        if (!$menuData) {
            return response()->json(['status' => false, 'message' => 'MenuData not found'], 404);
        }

        $menuData->delete();
        return response()->json(['status' => true, 'message' => 'MenuData deleted successfully'], 200);
    }
}
