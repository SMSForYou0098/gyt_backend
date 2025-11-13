<?php

namespace App\Http\Controllers;

use App\Models\Commision;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index($id)
    {
        $commission = Commision::where('user_id', $id)->first();

        if ($commission) {
            return response()->json(['commission' => $commission]);
        } else {
            return response()->json(['message' => 'Commission not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $commission = Commision::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'commission_type' => $request->commission_type,
                'commission_rate' => $request->commission_rate,
                'status' => $request->status,
            ]
        );
        return response()->json(['message' => 'Commission updated successfully'], 200);
    }
}
