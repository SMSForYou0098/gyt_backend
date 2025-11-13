<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyPaymentGatewayRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Extract the server (protocol, host, and port)
        $server = $request->getSchemeAndHttpHost();
        Log::info('Extracted server', ['server' => $server]);

        // Log the full request URL
        $url = $request->url();
        Log::info('Full request URL', ['url' => $url]);

        // Example validation for server
        $expectedServer = 'http://192.168.0.140:8000'; // Replace with your expected server
        // $expectedServer = 'http://192.168.0.137:8000'; // Replace with your expected server
        if ($server !== $expectedServer) {
            Log::error('Unauthorized server access', ['server' => $server]);
            return response()->json(['error' => 'Unauthorized server'], 403);
        }
        $allowedIps = ['192.168.0.140'];
        // $allowedIps = ['192.168.0.137'];
        if (!in_array($request->ip(), $allowedIps)) {
            Log::error('Unauthorized IP address in payment gateway request', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized IP'], 403);
        }

        return $next($request);
    }

    /**
     * Validate the request.
     *
     * @param string $url
     * @param string|null $authHeader
     * @param string $ip
     * @return bool
     */
    protected function isValidRequest($url, $authHeader, $ip)
    {
        // Example: Validate IP address
        $allowedIps = ['192.168.0.140'];
        // $allowedIps = ['192.168.0.137'];
        if (!in_array($ip, $allowedIps)) {
            return false;
        }

        // Example: Validate URL
        if (!str_contains($url, 'payment/callback')) {
            return false;
        }

        // Example: Validate Authorization header
        if (is_null($authHeader) || $authHeader !== 'Bearer your-secret-token') {
            return false;
        }

        return true;
    }
}
