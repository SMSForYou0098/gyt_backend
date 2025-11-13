<?php

namespace App\Http\Controllers;

use App\Exports\PromoCodeExport;
use App\Models\PromoCode;
use App\Models\Ticket;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class PromoCodeController extends Controller
{

    public function list($id)
    {
        $user = Auth::user();
        if ($isAdmin = $user->hasRole('Admin')) {
            $promoCodes = PromoCode::all();
        } else {
            $promoCodes = PromoCode::where('user_id', $user->id)->get();
        }
        if ($promoCodes->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Promo codes not found'
            ], 404);
        }
        return response()->json([
            'status' => true,
            'promoCodes' => $promoCodes,
        ], 200);
    }


    public function store(Request $request)
    {
        try {
            $Details = $request->all();
            $masterDataToInsert = [
                'user_id' => Auth::id(),
                'code' => $Details['code'],
                'description' => $Details['description'],
                'discount_type' => $Details['discount_type'],
                'discount_value' => $Details['discount_value'] ?? '',
                'minimum_spend' => $Details['minimum_spend'] ?? '',
                'usage_limit' => $Details['usage_limit'] ?? '',
                'remaining_count' => $Details['usage_limit'] ?? '',
                'usage_per_user' => $Details['usage_per_user'],
                'status' => isset($validatedData['status']) ? (int) $validatedData['status'] : 1, // Default to 1 if not provided
               
            ];

            $promoCodes = PromoCode::create($masterDataToInsert);

            return response()->json(['status' => true, 'message' => 'Promo code craete successfully', 'promoCodes' => $promoCodes,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to Promo code', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id)
    {
        $promoCode = PromoCode::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        // $promoCode = PromoCode::find($id);
        if (!$promoCode) {
            return response()->json(['status' => false, 'message' => 'Promo code not found'], 404);
        }
        return response()->json([
            'status' => true,
            'promoCode' => $promoCode,
        ], 200);
    }

    public function update(Request $request)
    {
        // dd($request->id);
        try {
            $details = $request->all();
            $promoCode = PromoCode::findorFail($request->id);
            $masterDataToUpdate = [
                'code' => $details['code'],
                'description' => $details['description'],
                'discount_type' => $details['discount_type'],
                'discount_value' => $details['discount_value'],
                'minimum_spend' => $details['minimum_spend'],
                'usage_limit' => $details['usage_limit'],
                'remaining_count' => $details['usage_limit'],
                'usage_per_user' => $details['usage_per_user'],
                'status' => isset($details['status']) ? (int) $details['status'] : (int) $promoCode->status,
                // 'status' => isset($details['status']) ? (bool) $details['status'] : true, // Cast to boolean
                // 'start_date' => $details['start_date'],
                // 'end_date' => $details['end_date'],
            ];

            $promoCode->update($masterDataToUpdate);
            return response()->json(['status' => true, 'message' => 'Promo code update successfully', 'promoCodes' => $promoCode,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to Promo code', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        $promoCode = PromoCode::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        if (!$promoCode) {
            return response()->json(['status' => false, 'message' => 'Promo code not found'], 404);
        }

        $promoCode->delete();
        return response()->json(['status' => true, 'message' => 'Promo code deleted successfully'], 200);
    }


 	public function checkPromoCode(Request $request, $id): JsonResponse
    {
        $request->validate([
            'promo_code' => 'required|string',
            'ticket_id' => 'required|integer',
            'amount' => 'required|numeric|min:0',
        ]);

        // Fetch the ticket first
        $ticketData = Ticket::find($request->ticket_id);

        if (!$ticketData) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid ticket ID.',
            ], 404);
        }

        // Decode the promo code list safely
        $allowedPromoIds = json_decode($ticketData->promocode_ids, true);
        if (!is_array($allowedPromoIds)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Promo Code.',
            ], 400);
        }

        // Find the promo code
        $promoCode = PromoCode::where('code', $request->promo_code)
            ->where('user_id', $id)
            ->first();

        if (!$promoCode) {
            return response()->json([
                'status' => false,
                'message' => 'Promo code not found for this user.',
            ], 404);
        }

        if (!in_array($promoCode->id, $allowedPromoIds)) {
            return response()->json([
                'status' => false,
                'message' => 'Promo code is not applicable for this ticket.',
            ], 400);
        }

        if ($request->amount < $promoCode->minimum_spend) {
            return response()->json([
                'status' => false,
                'message' => 'Amount does not meet the minimum spend requirement.',
            ], 400);
        }

        if ($promoCode->remaining_count <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'Promo code has already been used up.',
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'Promo code applied successfully.',
            'promo_data' => $promoCode,
        ], 200);
    }


    public function export(Request $request)
    {

        $status = $request->input('status');
        $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

        $query = PromoCode::query();

        if ($request->has('status')) {
            $query->where('status', $status);
        }

        if ($dates) {
            if (count($dates) === 1) {
                $singleDate = Carbon::parse($dates[0])->toDateString();
                $query->whereDate('created_at', $singleDate);
            } elseif (count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }
        }

        $PromoCode = $query->get();
        // return response()->json(['events' => $PromoCode]);
        return Excel::download(new PromoCodeExport($PromoCode), 'PromoCode_export.xlsx');
    }
}
