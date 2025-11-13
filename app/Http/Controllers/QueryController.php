<?php

namespace App\Http\Controllers;

use App\Models\Query;
use Illuminate\Http\Request;

class QueryController extends Controller
{
    public function index(Request $request)
    {
      
         $type = $request->query('type');
        $queryData = Query::where('type', $type)->get();
        if ($queryData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'queryData not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $queryData,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
            'type'  => 'required|string|in:signature_type,aggrement,faq,contact_us',
        ]);

            $queryData = new Query();
            $queryData->title = $request->title;
            $queryData->type = $request->type;
           
            $queryData->save();

            return response()->json(['status' => true, 'message' => 'queryData craete successfully', 'data' => $queryData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to queryData '], 404);
        }
    }

    public function show($id) 
    {
        $queryData = Query::find($id);

        if (!$queryData) {
            return response()->json(['status' => false, 'message' => 'queryData not found'], 200);
        }

        return response()->json(['status' => true, 'data' => $queryData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $queryData = Query::findOrFail($id); // existing record fetch

            $queryData->title = $request->title ?? $queryData->title;
            $queryData->type = $request->type ?? $queryData->type;
          

            $queryData->save();

            return response()->json(['status' => true, 'message' => 'queryData updated successfully', 'data' => $queryData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update queryData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $queryData = Query::where('id', $id)->firstOrFail();
        if (!$queryData) {
            return response()->json(['status' => false, 'message' => 'queryData not found'], 404);
        }

        $queryData->delete();
        return response()->json(['status' => true, 'message' => 'queryData deleted successfully'], 200);
    }
}
