<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use App\Models\LiveUserCount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LiveUserController extends Controller
{

    public function getLiveUserCount()
    {
        try {
            $count = LiveUserCount::count();

            return response()->json([
                'status' => true,
                'count' =>  $count,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving live user count',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // $ip = "117.96.122.44";
            $ip = $request->ip();

            $existingLiveUser = LiveUserCount::where('ip_address', $ip)->first();

            if ($existingLiveUser) {
                return response()->json([
                    'status' => true,
                    'message' => 'IP address already exists',
                    'data' => $existingLiveUser,
                ], 200);
            }
            $isPrivateIP = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

            if ($isPrivateIP) {
                $data = [
                    'city' => 'Private Network',
                    'regionName' => 'Private Network',
                    'country' => 'Private Network',
                    'lat' => 0.0,
                    'lon' => 0.0,
                ];
            } else {
                $response = Http::get("http://ip-api.com/json/{$ip}");
                Log::info('IP-API Response', ['response' => $response->json()]);

                if ($response->failed() || $response->json('status') !== 'success') {
                    return response()->json(['message' => 'Failed to fetch location data'], 500);
                }

                $data = $response->json();
            }

            $liveUser = LiveUserCount::create([
                'ip_address' => $ip,
                'location' => $data['city'] . ', ' . $data['regionName'] . ', ' . $data['country'],
                'latitude' => $data['lat'],
                'longitude' => $data['lon'],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'User location stored successfully',
                'data' => $liveUser,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while storing location data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            // $ip = "49.34.64.193";
            $ip = $request->ip();

            $liveUser = LiveUserCount::where('ip_address', $ip)->first();

            if (!$liveUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'IP address not found',
                ], 404);
            }

            $liveUser->delete();

            return response()->json([
                'status' => true,
                'message' => 'Live User deleted successfully',
                'liveUser' => $liveUser
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting the location data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
