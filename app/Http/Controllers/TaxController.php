<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index($id)
    {
        $taxes = Tax::where('user_id', $id)->firstOrFail();
        return response()->json([
            'message' => 'Successfully retrieved tax records',
            'taxes' => $taxes
        ], 200);
    }
    public function store(Request $request)
    {

        $tax = Tax::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'status' => $request->status,
                'tax_title' => $request->tax_title,
                'rate_type' => $request->rate_type,
                'rate' => $request->rate,
                'tax_type' => $request->tax_type,
            ]
        );

        $message = $tax->wasRecentlyCreated ? 'Tax created successfully' : 'Tax updated successfully';

        // Return a JSON response
        return response()->json([
            'message' => $message,
            'tax' => $tax
        ], $tax->wasRecentlyCreated ? 201 : 200);
    }
}
