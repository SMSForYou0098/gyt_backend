<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\BlockedIpAddress;
class RestrictIP
{
    /**
     * List of allowed IPs.
     *
     * @var array
     */
    protected $refererDomain = [
    
        'testpay.easebuzz.in',
      	'www.getyourticket.in',
      	'https://www.instamojo.com',
      	'https://www.instamojo.com',
        'pay.easebuzz.in',
        'razorpay.com',
        'mercury-t2.phonepe.com',
        'api.razorpay.com',
        '192.168.0.119',
        '192.168.0.120',
        'getyourticket.in',
        'ssgarba.com',
        'gyt.tieconvadodara.com',
        'ticket.tieconvadodara.com',
        // '43.204.193.72',
        // '65.2.49.140',
    ];


    public function handle(Request $request, Closure $next)
    {

        // if (!in_array($request->ip(), $this->allowedIPs)) {
        //     return response()->json(['error' => 'Unauthorized Access'], 403);
        // }

        // return $next($request);

        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $referer = $request->headers->get('referer');
        $refererDomain = $this->parseDomainFromReferer($referer);

        //Log::info("Request IP : $ip");
        //Log::info("Webhook Referer Domain iP: $refererDomain");

        // Check for Postman
        if (stripos($userAgent, 'Postman') !== false) {
            $this->blockRequest($request, $refererDomain, 'Blocked due to Postman.');
        }

        // Validate domain
        if (!$refererDomain || !in_array($refererDomain, $this->refererDomain)) {
            $this->blockRequest($request, $refererDomain, 'Blocked due to invalid referer domain.');
        }

        return $next($request);
    }

    protected function parseDomainFromReferer(?string $referer): ?string
    {
        if (!$referer) return null;
        $parsed = parse_url($referer);
        return $parsed['host'] ?? null;
    }

    protected function blockRequest(Request $request, ?string $domain, string $reason)
    {
        BlockedIpAddress::firstOrCreate(
            ['ip_address' => $request->ip()],
            [
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'domain' => $domain ?? 'unknown',
            ]
        );

        // If logged in somehow
        if (Auth::check()) {
            Auth::user()->update(['status' => 0]);
        }

        abort(403, $reason);
    }
}
