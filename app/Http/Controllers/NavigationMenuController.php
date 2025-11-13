<?php

namespace App\Http\Controllers;
use App\Models\NavigationMenu;
use Auth;
use Illuminate\Http\Request;


class NavigationMenuController extends Controller
{
    public function index()
    {
        try {
            $navData = NavigationMenu::with('Page', 'MenuGroup')->orderBy('sr_no')->get();

            if ($navData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'NavData not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'NavData retrieved successfully',
                'NavData' => $navData,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving NavData',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try
         {
            $maxSerialNo = NavigationMenu::where('menu_group_id', $request->menu_group_id)->max('sr_no');
            $newSerialNo = $maxSerialNo ? $maxSerialNo + 1 : 1;
            $navData = new NavigationMenu();
            $navData->title = $request->title;
            $navData->type = $request->type;
            $navData->sr_no = $newSerialNo;
            $navData->menu_group_id = $request->menu_group_id;
            if ($request->type == 0) {
                $navData->page_id = $request->page_id;
                $navData->external_url = null;
                $navData->new_tab = null;
            } elseif ($request->type == 1) {
                $navData->external_url = $request->external_url;
                $navData->new_tab = $request->new_tab;
                $navData->page_id = null;
            }
            $navData->save();
            $navData = NavigationMenu::with('Page')->find($navData->id);
            return response()->json(['status' => true,'message' => 'Nav Data craete successfully','NavData' => $navData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to navigation menus', 'error' => $e->getMessage()], 500);

        }
    }

    public function show(string $id)
    {
        $navData = NavigationMenu::with('Page')->findOrFail($id);
        if (!$navData) {
            return response()->json(['status' => false, 'message' => 'NavData navigation menus'], 404);
        }
        return response()->json([
            'status' => true,
            'message' => 'NavData successfully',
            'NavData' => $navData,
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        try {
            $navData = NavigationMenu::with('Page')->findOrFail($id);
            $navData->title = $request->title;
            $navData->type = $request->type;
           
            if ($request->type == 0) {
                $navData->page_id = $request->page_id;
                $navData->external_url = null; 
                $navData->new_tab = null; 
            } elseif ($request->type == 1) {
                $navData->external_url = $request->external_url;
                $navData->new_tab = $request->new_tab;
                $navData->page_id = null;
            }
            $navData->save();
            return response()->json(['status' => true, 'message' => 'Nav Menu updated successfully', 'NavData' => $navData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to navigation menus', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        $navData = NavigationMenu::where('id', $id)->firstOrFail();
        if (!$navData) {
            return response()->json(['status' => false, 'message' => 'NavData not found'], 404);
        }

        $navData->delete();
        return response()->json(['status' => true, 'message' => 'NavData deleted successfully'], 200);
    }

    public function rearrangeMenu(Request $request)
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
                $navData = NavigationMenu::findOrFail($item['id']);
                $navData->sr_no = $item['sr_no'];
                $navData->save();
            }

            $updatedNavData = NavigationMenu::orderBy('sr_no')->with('Page')->get();

            return response()->json([
                'status' => true,
                'message' => 'Navigation menus rearranged successfully',
                'data' => $updatedNavData,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to rearrange navigation menus',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

}
