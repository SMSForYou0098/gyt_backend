<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\BlockedIpAddress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class RestrictPaymentResponse
{
    protected $allowedDomains = [
        'testpay.easebuzz.in',
      	'https://www.instamojo.com',
      	'https://www.instamojo.com',
        'pay.easebuzz.in',
        'razorpay.com',
        'mercury-t2.phonepe.com',
      'api.razorpay.com',
      'api.cashfree.com',
      'www.cashfree.com',
      'getyourticket.in',
    ];

    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $referer = $request->headers->get('referer');
        $refererDomain = $this->parseDomainFromReferer($referer);

        Log::info("Request IP : $ip");
        Log::info("Webhook Referer Domain: $refererDomain");

        // Check for Postman
        if (stripos($userAgent, 'Postman') !== false) {
            $this->blockRequest($request, $refererDomain, 'Blocked due to Postman.');
        }

        // Validate domain
        if (!$refererDomain || !in_array($refererDomain, $this->allowedDomains)) {
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
