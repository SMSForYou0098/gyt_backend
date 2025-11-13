<?php

namespace App\Http\Controllers;

use App\Models\Balance;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BalanceController extends Controller
{

    protected $bookingService;
    public function __construct()
    {
        $this->bookingService = app('BookingService'); // Resolving from service container
    }

    public function index(Request $request, $id)
    {
        $historyQuery = Balance::where('user_id', $id);

        if ($request->has('date')) {
            $dates = $request->date ? explode(',', $request->date) : null;

            if (count($dates) === 1) {
                // Single date filtering
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[0])->endOfDay();
            } elseif (count($dates) === 2) {
                // Date range filtering
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            } else {
                return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
            }

            // Apply date range to query
            $historyQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $history = $historyQuery->get();
        $latest_balance = $historyQuery->latest()->first()->total_credits;
        if (Auth::check() && Auth::user()->hasRole('Admin')) {
            $today = Carbon::today()->toDateString();
            $allHistory = Balance::where('auto_deduction', null)->whereDate('created_at', $today)->with([
                'user' => function ($query) {
                    $query->select('id', 'name');
                }
            ])->get();
            return response()->json(['history' => $history, 'allHistory' => $allHistory, 'latest_balance' => $latest_balance]);
        } else {
            return response()->json(['history' => $history]);
        }
    }

    public function create(Request $request)
    {

        try {

            $reporting_user = User::where('id', $request->user_id)->pluck('reporting_user')->toArray();

            $existingBalance = Balance::where('user_id', $request->user_id)->latest()->first();

            // Calculate new total_credits
            $previousTotalCredits = $existingBalance ? $existingBalance->total_credits : 0;
            $newTotalCredits = $previousTotalCredits + $request->newCredit;

            $user = new Balance();
            $user->user_id = $request->user_id;
            $user->new_credit = $request->newCredit;
            $user->total_credits = $newTotalCredits;
            $user->payment_method = $request->payment_method;
            $user->payment_type = 'credit';
            $user->account_manager_id = $reporting_user[0];
            $user->assign_by = $request->assign_by;
            $user->transaction_id = $this->generateTransactionId();
            if ($request->deduction) {
                $user->manual_deduction = 'true';
            }
            $user->save();

            return response()->json(['status' => true, 'message' => 'Credit Updated Successfully', 'newTotalCredits' => $newTotalCredits], 201);
        } catch (\Exception $e) {
            \Log::error('Error creating user: ' . $e->getMessage());

            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
    public function CheckValidUser($id)
    {
        try {
            $user = Balance::where('user_id', $id)->with('user')->latest()->first();
            // $user = User::where('id', $id)->with('balance')->first();
            // if ($user) {
            //     $user->latest_balance = $user->balance()->latest()->first();
            //     unset($user->balance);
            // }
            // $user_balance = $user->latest_balance->total_credits ?? 00.00;
            return response()->json(['status' => true, 'balance' => $user]);
            if ($user_balance) {
                $user_balance = $user->latest_balance->total_credits ?? 0;
                return response()->json(['status' => true, 'balance' => $user]);
            } else {
                return response()->json(['status' => false, 'message' => 'insufficient credits']);
            }
        } catch (QueryException $e) {
            $errorMessage = $e->getMessage();
            return response()->json(['status' => false, 'message' => 'Query Exception: ' . $errorMessage]);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            return response()->json(['status' => false, 'message' => 'An error occurred while processing the request.' . $errorMessage]);
        }
    }

    public function walletUser(Request $request, $token)
    {
        try {
            $orderId = $token;

            if (!$orderId) {
                return response()->json(['error' => 'Invalid token provided'], 400);
            }

            // Fetch all bookings using the service provider
            $bookings = $this->bookingService->getBookingsByOrderId($orderId);


            if (!array_filter($bookings)) {
                return response()->json(['error' => 'Invalid token or no bookings found'], 404);
            }

            // Extract individual bookings
            $bookingTypes = [
                'Booking',
                'AgentBooking',
                'ExhibitionBooking',
                'AmusementBooking',
                'ComplimentaryBookings',
                'MasterBooking',
                'AmusementMasterBooking',
                'AgentMasterBooking'
            ];
            $filteredBooking = null;
            foreach ($bookingTypes as $type) {
                if (!empty($bookings[$type])) {
                    // $filteredBooking = [
                    //     'type' => $type,
                    //     'data' => $bookings[$type]
                    // ];
                    $filteredBooking =  $bookings[$type];
                    break;
                }
            }

            // If no valid booking data is found
            if (!$filteredBooking) {
                return response()->json(['error' => 'No valid booking data found'], 404);
            }

            // Generate a 20-character session_id
            $session_id = Str::random(20);
            Cache::put('session_' . $session_id, $filteredBooking, now()->addMinutes(5));

            $user = null;
            if (!empty($filteredBooking['user_id'])) {
                $balanceData = null;
                $userId = $filteredBooking['user_id'];
                $user = User::select('id', 'name', 'number')
                    ->with('balance')
                    ->find($userId);
                if ($user && $user->balance->isNotEmpty()) {
                    $latestBalance = $user->balance->last();
                    $balanceData =  $user->total_credits = $latestBalance->total_credits;
                    unset($user->balance);
                }
            }
            return response()->json([
                'status' => true,
                'message' => 'Booking data retrieved successfully',
                // 'data' => $filteredBooking,
                'user' => $user,
                'user_balance' => $balanceData,
                'session_id' => $session_id,

            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    private function generateTransactionId()
    {
        return strtoupper(bin2hex(random_bytes(10))); // Generates a 20-character alphanumeric ID
    }

    public function processTransaction(Request $request)
    {
        try {
            $tokenId = $request->token;
            $sessionId = $request->session_id;
            $userId  = $request->user_id;
            $shopKeeperId  = $request->shopKeeper_id;
            $amountToDeduct  = $request->amount;
            $description  = $request->description;
            $payment_method  = $request->payment_method;

            $reporting_user = User::where('id', $userId)->pluck('reporting_user')->toArray();
            $userdata = User::find($userId);
            $shopKeeperdata = User::with('shop')->find($shopKeeperId);

            $reporting_shopuser = User::where('id', $shopKeeperId)->pluck('reporting_user')->toArray();

            if (!Cache::has('session_' . $sessionId)) {
                return response()->json(['error' => 'Invalid or expired session'], 403);
            }

            $latestBalance = Balance::where('user_id', $userId)->latest()->first();
            if (!$latestBalance || $latestBalance->total_credits < $amountToDeduct) {
                return response()->json(['error' => 'Insufficient balance'], 400);
            }

            DB::beginTransaction();

            // Deduct amount from user's balance
            $newUserBalance = $latestBalance->total_credits - $amountToDeduct;
            $deduction = new Balance();
            $deduction->user_id = $userId;
            $deduction->new_credit = $amountToDeduct;
            $deduction->total_credits = $newUserBalance;
            $deduction->payment_method = $payment_method;
            $deduction->payment_type = 'debit';
            $deduction->transaction_id = $this->generateTransactionId();
            $deduction->assign_by = $shopKeeperId;
            $deduction->account_manager_id = $reporting_user[0];
            $deduction->session_id = $sessionId;
            $deduction->description = $description;
            $deduction->token = $tokenId;
            $deduction->status = 0;
            $deduction->save();

            $balanceData = $newUserBalance;

            // Add amount to shopkeeper
            $shopKeeperBalance = Balance::where('user_id', $shopKeeperId)->latest()->first();
            $newShopKeeperBalance = $shopKeeperBalance ? $shopKeeperBalance->total_credits + $amountToDeduct : $amountToDeduct;

            $shopKeeperTransaction = new Balance();
            $shopKeeperTransaction->user_id = $shopKeeperId;
            $shopKeeperTransaction->new_credit = $amountToDeduct;
            $shopKeeperTransaction->total_credits = $newShopKeeperBalance;
            $shopKeeperTransaction->payment_method = $payment_method;
            $shopKeeperTransaction->payment_type = 'credit';
            $shopKeeperTransaction->transaction_id = $this->generateTransactionId();
            $shopKeeperTransaction->assign_by = $userId;
            $shopKeeperTransaction->account_manager_id = $reporting_shopuser[0];
            $shopKeeperTransaction->session_id = $sessionId;
            $shopKeeperTransaction->description = $description;
            $shopKeeperTransaction->token = $tokenId;
            $shopKeeperTransaction->status = 0;
            $shopKeeperTransaction->save();

            // Update status of both transactions to completed after successful save
            $deduction->status = 1;
            $deduction->save();

            $shopKeeperTransaction->status = 1;
            $shopKeeperTransaction->save();

            DB::commit();


            return response()->json([
                'status' => true,
                'message' => 'Transaction successful',
                // 'UserBalance' => $balanceData,
                // 'ShopKeeperBalance' => $newShopKeeperBalance,
                'data' => [
                    'id' => $shopKeeperTransaction->id,
                    'user_name' => $userdata->name,
                    'user_number' => $userdata->number,
                    'credits' => $amountToDeduct,
                    'description' => $description,
                    'total_credits' => $newUserBalance,
                    'shop_name' => $shopKeeperdata->shop->shop_name ?? 'N/A',
                    'shop_user_name' => $shopKeeperdata->name,
                    'shop_user_number' => $shopKeeperdata->number,
                    'transaction_date' => Carbon::now()->toDateTimeString(),
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Transaction failed. Amount rollbacked.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function allBalance(Request $request, $id = null)
    {
        try {
            if (Auth::check()) {
                $balanceQuery = Balance::with([
                    'assignBy:id,name,number',
                    'shopData:id,user_id,shop_name',
                    'accountManager:id,name,number',
                    'user:id,name,number',
                ]);

                // If a specific user ID is provided, filter by that user
                if ($id && !Auth::user()->hasRole('Admin')) {
                    $balanceQuery->where('user_id', $id);
                }

                if ($request->has('date')) {
                    $dates = $request->date ? explode(',', $request->date) : null;

                    if (count($dates) === 1) {
                        // Single date filtering
                        $startDate = Carbon::parse($dates[0])->startOfDay();
                        $endDate = Carbon::parse($dates[0])->endOfDay();
                    } elseif (count($dates) === 2) {
                        // Date range filtering
                        $startDate = Carbon::parse($dates[0])->startOfDay();
                        $endDate = Carbon::parse($dates[1])->endOfDay();
                    } else {
                        return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
                    }

                    $balanceQuery->whereBetween('created_at', [$startDate, $endDate]);
                } else {
                    // Default to today's bookings
                    $startDate = Carbon::today()->startOfDay();
                    $endDate = Carbon::today()->endOfDay();
                    $balanceQuery->whereBetween('created_at', [$startDate, $endDate]);
                }

                $balanceHistory = $balanceQuery->orderBy('id', 'desc')->get();

                if ($balanceHistory->isEmpty()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'No balance records found'
                    ], 200);
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Balance history retrieved successfully',
                    'data' => $balanceHistory
                ], 200);
            }

            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }



    public function transactionsOverView(Request $request, $id)
    {
        try {

            $startDate = null;
            $endDate = null;

            if ($request->date) {
                $dates = explode(',', $request->date);

                if (count($dates) === 1) {
                    // Single date filtering
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[0])->endOfDay();
                } elseif (count($dates) === 2) {
                    // Date range filtering
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[1])->endOfDay();
                } else {
                    return response()->json(['status' => 'false', 'message' => 'Invalid date format'], 400);
                }
            } else {
                // Default to today's bookings
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
            }

            // Query balance history where user_id matches and payment_type is 'credit'
            $balanceHistory = Balance::where('user_id', $id)
                ->where('payment_type', 'credit')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            // Calculate the total sum of new_credit
            $totalCredits = $balanceHistory->sum('new_credit');

            // Check if balance history exists
            if ($balanceHistory->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No balance records found for this user'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Balance history retrieved successfully',
                'total_credits' => $totalCredits,
                'data' => $balanceHistory
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    public function shopKeeperDashbord($id)
    {
        try {
            $data = [];
            $todayTotal = 0;
            $yesterdayTotal = 0;
            $last7DaysTotal = 0;

            for ($i = 0; $i < 7; $i++) {
                $date = Carbon::today()->subDays($i);
                $totalNewCredit = Balance::where('user_id', $id)
                    ->whereDate('created_at', $date)
                    ->sum('new_credit');

                if ($i == 0) {
                    $todayTotal = $totalNewCredit; // Today's total
                } elseif ($i == 1) {
                    $yesterdayTotal = $totalNewCredit; // Yesterday's total
                }

                $last7DaysTotal += $totalNewCredit;
                $data[] = $totalNewCredit; // Store only numbers
            }

            return response()->json([
                'status' => true,
                'message' => 'Last 7 days new credit total',
                'today_total' => $todayTotal,
                'yesterday_total' => $yesterdayTotal,
                'last_7_days_total' => $last7DaysTotal,
                'data' => $data // Only numbers in the array
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }



    public function walletData(Request $request, $userId)
    {
        try {
            $blancedata = Balance::with(['assignBy', 'shopData.user'])->findOrFail($userId);
            // return response()->json($blancedata);
            return response()->json([
                'status' => true,
                'message' => 'wallet data successful',
                'data' => [
                    'user_name' => $blancedata->assignBy->name,
                    'user_number' => $blancedata->assignBy->number,
                    'credits' => $blancedata->new_credit ?? 0,
                    'description' => $blancedata->description,
                    'total_credits' => $blancedata->total_credits,
                    'shop_name' => $blancedata->shopData->shop_name ?? 'N/A',
                    'shop_user_name' => $blancedata->shopData->user->name,
                    'shop_user_number' => $blancedata->shopData->user->number,
                    'transaction_date' => $blancedata->created_at,
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Transaction failed. Amount rollbacked.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
