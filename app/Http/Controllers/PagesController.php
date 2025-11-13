<?php

namespace App\Http\Controllers;

use App\Models\FooterGroup;
use App\Models\FooterMenu;
use App\Models\Page;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class PagesController extends Controller
{

    public function index()
    {
        $pagesData = Page::all();
        if ($pagesData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Pages  not found'
            ], 404);
        }
        return response()->json([
            'status' => true,
            'pagesData' => $pagesData,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $Details = $request->all();
            $masterDataToInsert = [
                'title' => $Details['title'] ?? '',
                'content' => $Details['content'] ?? '',
                'meta_title' => $Details['meta_title'] ?? '',
                'meta_tag' => $Details['meta_tag'] ?? '',
                'meta_description' => $Details['meta_description'] ?? '',
                'status' => isset($Details['status']) ? (int) $Details['status'] : 1, // Default to 1 if not provided
            ];

            $pagesData = Page::create($masterDataToInsert);

            return response()->json(['status' => true,'message' => 'Pages craete successfully','pagesData' => $pagesData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 404);

        }
    }

    public function show(string $id)
    {
        $pageData = Page::where('id', $id)->firstOrFail();
        if (!$pageData) {
            return response()->json(['status' => false, 'message' => 'pages not found'], 404);
        }
        return response()->json([
            'status' => true,
            'pageData' => $pageData,
        ], 200);
    }

    public function update(Request $request, string $id)
    {
         try {
            $details = $request->all();
            $pagesData = Page::findorFail($id);
            $masterDataToUpdate = [
                'title' => $details['title'] ?? '',
                'content' => $details['content'] ?? '',
                'meta_title' => $Details['meta_title'] ?? '',
                'meta_tag' => $Details['meta_tag'] ?? '',
                'meta_description' => $Details['meta_description'] ?? '',
                'status' => isset($details['status']) ? (int) $details['status'] : (int) $pagesData->status,
            ];

            $pagesData->update($masterDataToUpdate);
            return response()->json(['status' => true,'message' => 'pages update successfully','pages' => $pagesData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function destroy(string $id)
    {
        $paegsData = Page::where('id', $id)->firstOrFail();
        if (!$paegsData) {
            return response()->json(['status' => false, 'message' => 'Pages not found'], 404);
        }

        $paegsData->delete();
        return response()->json(['status' => true, 'message' => 'pages deleted successfully'], 200);
    }

    public function getTitle()
    {
        $pagesData = Page::select('id','title')->get();
        if ($pagesData->isEmpty()) {
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

    public function pageTitle(Request $request, string $title)
    {

        
        $pagesData = FooterMenu::with(['pages:id,title,content,meta_title,meta_tag,meta_description']) // Specify only the necessary fields
        ->where('title', $title)
        ->select('id', 'title', 'page_id') // Select necessary fields from FooterMenu
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
