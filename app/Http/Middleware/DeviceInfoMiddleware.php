<?php

namespace App\Http\Middleware;

use App\Jobs\StoreDeviceInfoJob;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;
use Stevebauman\Location\Facades\Location;


class DeviceInfoMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $ip = $request->ip();
            $agent = new Agent();
            $location = Location::get($ip);
            // Log::info("userAgent  :".gettype($agent));
            // exit;
            dispatch(new StoreDeviceInfoJob($ip, $agent, $location));
        } catch (\Exception $e) {
            Log::error('Error dispatching device info job: ' . $e->getMessage());
        }
    
        return $next($request);
    }
}
    