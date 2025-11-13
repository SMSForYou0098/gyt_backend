<?php

namespace App\Http\Controllers;

use App\Models\UserInfo;
use Carbon\Carbon;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stevebauman\Location\Facades\Location;

class UserInfoController extends Controller
{

    public function storeDeviceInfo(Request $request)
    {
        // return response()->json($request->all());
        try {
            $agent = new Agent();
            $device = $agent->device();
            $browser = $agent->browser();
            $platform = $agent->platform();
            $ip = $request->ip();
            $deviceIp = gethostbyname(gethostname());

            // Get location details from IP
            $locality = $request->locality;
            $country = $request->country;
            $city =$request->city;
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $state = $request->principalSubdivision;

            $userDevice = UserInfo::updateOrCreate(
                [
                    'ip_address' => $ip,
                    'device' => $device,
                ],
                [
                    'browser' => $browser,
                    'platform' => $platform,
                    'user_id' => auth()->id(),
                    'country' => $country,
                    'city' => $city,
                    'state' => $state,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'locality' => $locality,
                    'date' => now(),
                    'updated_at' => now(),
                ]
            );

            return response()->json(['status' => true, 'message' => 'Device info stored successfully', 'userDevice' => $userDevice], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 404);
        }
    }


    public function countUserDevices(Request $request)
    {
        try {
            $option = $request->option;
            $now = now();
            $todayStart = $now->copy()->startOfDay();
            $yesterdayStart = $now->copy()->subDay()->startOfDay();
            $yesterdayEnd = $now->copy()->subDay()->endOfDay();
            $last7DaysStart = $now->copy()->subDays(6)->startOfDay(); // today + last 6 days = 7 days
            $monthStart = $now->copy()->startOfMonth();
    
            $fiveMinutesAgo = $now->copy()->subMinutes(5);
    
            // Total Live Users
            $liveUsers = UserInfo::whereBetween('updated_at', [$fiveMinutesAgo, $now])->count();
    
            // Count users for different time frames
            $today = UserInfo::whereBetween('created_at', [$todayStart, $now])->count();
            $yesterday = UserInfo::whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
            $lastTwoDay = UserInfo::whereBetween('created_at', [$yesterdayStart, $now])->count();
            $lastWeek = UserInfo::whereBetween('created_at', [$last7DaysStart, $now])->count();
            $thisMonth = UserInfo::whereBetween('created_at', [$monthStart, $now])->count();
    
            return response()->json([
                'status' => true,
                'message' => "User device data fetched successfully",
                'data' => [
                    'live_users' => $liveUsers,
                    'today' => $today,
                    'yesterday' => $yesterday,
                    'last_2_days' => $lastTwoDay,
                    'last_7_days' => $lastWeek,
                    'this_month' => $thisMonth,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    

    // public function liveData(Request $request)
    // {
    //     try {
    //         $now = Carbon::now();
    //         $fiveMinutesAgo = $now->copy()->subMinutes(5);
    //         $formattedFiveMinutesAgo = $fiveMinutesAgo->format('Y-m-d H:i:s');

    //         // Fetch live users that were updated in the last 5 minutes
    //         $liveUsers = UserInfo::whereBetween('updated_at', [$fiveMinutesAgo, $now])->get()->count();

    //         // Fetch all user devices (optional, based on your requirement)
    //         $userDevices = UserInfo::get();

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Live data fetched successfully',
    //             'data' => $userDevices,
    //             'Live' => $liveUsers,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
    //     }
    // }

    public function liveData(Request $request)
    {
        try {
            $now = Carbon::now();
            $fiveMinutesAgo = $now->copy()->subMinutes(5);
    
            // Count live users (updated within the last 5 minutes)
            $liveUsers = UserInfo::whereBetween('updated_at', [$fiveMinutesAgo, $now])->count();
    
            // Handle date filter
            if ($request->has('date')) {
                $dates = explode(',', $request->date);
                if (count($dates) === 1 || ($dates[0] === $dates[1])) {
                    // Single date
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[0])->endOfDay();
                } elseif (count($dates) === 2) {
                    // Date range
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[1])->endOfDay();
                } else {
                    return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
                }
            } else {
                // Default: Today's data
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
            }
    
            // Fetch user devices based on date filter
            $userDevices = UserInfo::whereBetween('updated_at', [$startDate, $endDate])->get();
    
            return response()->json([
                'status' => true,
                'message' => 'Live data fetched successfully',
                'data' => $userDevices,
                'Live' => $liveUsers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function deleteDeviceInfo(Request $request)
    {
        try {
            // Get the IP from the request
            $ip = $request->ip();

            $userDevice = UserInfo::where('ip_address', $ip)->first();

            if (!$userDevice) {
                return response()->json(['status' => false, 'message' => 'Device not found'], 404);
            }

            $userDevice->delete();

            return response()->json(['status' => true, 'message' => 'Device info marked as deleted'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
