<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{

    public function index()
    {
        $Shop = Shop::paginate(10);
        if ($Shop->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Shop  not found'
            ], 404);
        }
        return response()->json([
            'status' => true,
            'ShopData' => $Shop,
        ], 200);
    }

    public function store(Request $request)
    {
        try {

            $shopData = new Shop();
            $shopData->user_id = $request->user_id;
            $shopData->shop_name = $request->shop_name;
            $shopData->shop_no = $request->shop_no;
            $shopData->gst_no = $request->gst_no;

            $shopData->save();
            return response()->json(['status' => true, 'message' => 'shopData craete successfully', 'shopData' => $shopData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to shopData '], 404);
        }
    }

    public function show($id)
    {
        $shopData = Shop::find($id);

        if (!$shopData) {
            return response()->json(['status' => false, 'message' => 'shop not found'], 404);
        }

        return response()->json(['status' => true, 'shopData' => $shopData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $shopData = shop::findOrFail($id);

            $shopData->user_id = $request->user_id;
            $shopData->shop_name = $request->shop_name;
            $shopData->shop_no = $request->shop_no;
            $shopData->gst_no = $request->gst_no;

            $shopData->save();

            return response()->json(['status' => true, 'message' => 'shopData updated successfully', 'shopData' => $shopData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update shopData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $shop = Shop::where('id', $id)->firstOrFail();
        if (!$shop) {
            return response()->json(['status' => false, 'message' => 'shop not found'], 404);
        }

        $shop->delete();
        return response()->json(['status' => true, 'message' => 'Shop deleted successfully'], 200);
    }

}
