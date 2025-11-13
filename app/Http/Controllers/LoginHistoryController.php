<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Auth;
use Carbon\Carbon;

class LoginHistoryController extends Controller
{

    public function index(Request $request)
    {
        $user = Auth::user();


        if ($request->has('date')) {
            $dates = explode(',', $request->date);
            if (count($dates) === 1 || ($dates[0] === $dates[1])) {

                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate   = Carbon::parse($dates[0])->endOfDay();
            } elseif (count($dates) === 2) {

                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate   = Carbon::parse($dates[1])->endOfDay();
            } else {
                return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
            }
        } else {
            $startDate = Carbon::today()->startOfDay();
            $endDate   = Carbon::today()->endOfDay();
        }

        if ($user->hasRole('Admin')) {

            $adminHistories = LoginHistory::with('user:id,name,number')
                ->where('user_id', $user->id)
                ->whereBetween('login_time', [$startDate, $endDate])
                ->orderBy('login_time', 'desc')
                ->get();


            $allHistories = LoginHistory::with('user:id,name,number')
                ->whereBetween('login_time', [$startDate, $endDate])
                ->orderBy('login_time', 'desc')
                ->get();

            return response()->json([
                'status'         => true,
                'admin_history'  => $adminHistories,
                'data'           => $allHistories,
            ], 200);
        } else {

            $histories = LoginHistory::with('user:id,name,number')
                ->where('user_id', $user->id)
                ->whereBetween('login_time', [$startDate, $endDate])
                ->orderBy('login_time', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data'   => $histories
            ], 200);
        }
    }
}
