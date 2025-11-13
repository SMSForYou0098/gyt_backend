<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request)
    {
      
        $faqData = Faq::with('categoryData')->get();
        if ($faqData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'faqData not found',
                'data' => [],
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $faqData,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $faqData = new Faq();
            $faqData->question = $request->question;
            $faqData->answer = $request->answer;
            $faqData->category = $request->category;
            $faqData->links = $request->links;
            $faqData->is_active = $request->is_active;
           
            $faqData->save();

            return response()->json(['status' => true, 'message' => 'faqData craete successfully', 'data' => $faqData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to faqData '], 404);
        }
    }

    public function show($id) 
    {
        $faqData = Faq::with('categoryData')->find($id);

        if (!$faqData) {
            return response()->json(['status' => false, 'message' => 'faqData not found'], 200);
        }

        return response()->json(['status' => true, 'data' => $faqData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $faqData = Faq::findOrFail($id); // existing record fetch

            $faqData->question = $request->question ?? $faqData->question;
            $faqData->answer = $request->answer ?? $faqData->answer;
            $faqData->category = $request->category ?? $faqData->category;
            $faqData->links = $request->links ?? $faqData->links;
            $faqData->is_active = $request->is_active ?? $faqData->is_active;
          

            $faqData->save();

            return response()->json(['status' => true, 'message' => 'faqData updated successfully', 'data' => $faqData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update faqData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $faqData = Faq::where('id', $id)->firstOrFail();
        if (!$faqData) {
            return response()->json(['status' => false, 'message' => 'faqData not found'], 404);
        }

        $faqData->delete();
        return response()->json(['status' => true, 'message' => 'faqData deleted successfully'], 200);
    }
}
