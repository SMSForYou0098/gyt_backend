<?php

namespace App\Http\Controllers;
use App\Models\MenuGroup;
use Auth;
use Illuminate\Http\Request;

class MenuGroupController extends Controller
{

    public function index()
    {
        try {
            $menuData = MenuGroup::with('NavigationMenu.Page')->get();

            if ($menuData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'MenuGroupData not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'MenuGroupData retrieved successfully',
                'MenuGroupData' => $menuData,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving MenuGroupData',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try
         {
            if ($request->status == 1) {
                $existingGroup = MenuGroup::where('status', 1)->first();

                if ($existingGroup) {
                    return response()->json([
                        'error' => 'A menu group with status True already exists.',
                    ], 400);
                }
            }

            $MenuGroupData = new MenuGroup();
            $MenuGroupData->title = $request->title;
            $MenuGroupData->status = isset($request->status) ? (int) $request->status : 0;
            $MenuGroupData->save();
            return response()->json(['status' => true,'message' => 'Menu group created successfully.','MenuGroupData' => $MenuGroupData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to manu group', 'error' => $e->getMessage()], 500);

        }
    }

    public function show($id)
    {
        try {
            $menuGroup = MenuGroup::with('NavigationMenu.Page')->findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Menu group retrieved successfully.',
                'MenuGroupData' => $menuGroup,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to manu group', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $menuGroup = MenuGroup::findOrFail($id);

            if ($request->status == 1) {
                $existingGroup = MenuGroup::where('status', 1)->where('id', '!=', $id)->first();

                if ($existingGroup) {
                    return response()->json([
                        'error' => 'A menu group with status True already exists.',
                    ], 400);
                }
            }

            $menuGroup->title = $request->title;
            $menuGroup->status = isset($request->status) ? (int) $request->status : $menuGroup->status;
            $menuGroup->save();

            return response()->json([
                'status' => true,
                'message' => 'Menu group updated successfully.',
                'MenuGroupData' => $menuGroup,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to manu group', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        $menuGroup = MenuGroup::where('id', $id)->firstOrFail();
        if (!$menuGroup) {
            return response()->json(['status' => false, 'message' => 'menuGroup not found'], 404);
        }

        $menuGroup->delete();
        return response()->json(['status' => true, 'message' => 'Menu Group deleted successfully'], 200);
    }

    public function updateStatus(Request $request)
    {
        try {
            $id = $request->id;
            $status = $request->status;

            $menuGroup = MenuGroup::findOrFail($id);

            if ($status == 1) {
                MenuGroup::where('status', 1)->update(['status' => 0]);
            }

            $menuGroup->status = $status;
            $menuGroup->save();
            $updatedNavData = MenuGroup::with('NavigationMenu.Page')->findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Status updated successfully',
                'menuGroup' => $updatedNavData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed tomanu group', 'error' => $e->getMessage()], 500);
        }
    }

    public function activeStatus()
    {
        try {
            $updatedNavData = MenuGroup::with('NavigationMenu.Page')->where('status', 1)->first();
            return response()->json([
                'status' => true,
                'message' => 'Status active  successfully',
                'menu' => $updatedNavData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
               'message' => 'Failed to manu group', 'error' => $e->getMessage()], 500);
        }
    }

    public function menuTitle(Request $request, string $title)
    {
        $pagesData = MenuGroup::with(['NavigationMenu.Page']) // Specify only the necessary fields
        ->where('title', $title)
        ->first(); // Get the first matching record

        if (!$pagesData) {
            return response()->json([
                'status' => false,
                'message' => 'Pages Title not found'
            ], 404);
        }
        return response()->json([
            'status' => true,
            'pagesData' => $pagesData,
            'message' => 'Pages Title successfully'
        ], 200);
    }
}
