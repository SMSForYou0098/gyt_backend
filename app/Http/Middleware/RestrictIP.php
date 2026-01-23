<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictIP
{
    /**
     * List of allowed IPs.
     *
     * @var array
     */
    protected $allowedDomains = [
      'getyourticket.in',
      'www.getyourticket.in',
      'mercury-t2.phonepe.com',
      '192.168.0.126',
       '192.168.0.140',
      'www.cashfree.com',
      'api.cashfree.com',
      'razorpay.com',
      'api.razorpay.com',
      'getyourticket.in',
    ];
	protected $allowedIps = [
        '111.125.194.83',
    ];
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $referer = $request->headers->get('referer');
        $refererDomain = $this->parseDomainFromReferer($referer);

       // \Log::info("Request IP: $ip");
       // \Log::info("Webhook Referer: $referer");
       // \Log::info("Webhook Referer Domain: $refererDomain");
       // \Log::info("Allowed IPs: " . json_encode($this->allowedIps));
       // \Log::info("Allowed Domains: " . json_encode($this->allowedDomains));

        // Fix the syntax error and logic
        $ipAllowed = in_array($ip, $this->allowedIps);
        $domainAllowed = !empty($refererDomain) && in_array($refererDomain, $this->allowedDomains);

        if (!$ipAllowed && !$domainAllowed) {
            \Log::warning("Access denied - IP allowed: " . ($ipAllowed ? 'yes' : 'no') . ", Domain allowed: " . ($domainAllowed ? 'yes' : 'no'));
            return response()->json(['error' => 'Unauthorized Access'], 403);
        }

        return $next($request);
    }
  	    protected function parseDomainFromReferer(?string $referer): ?string
    {
        if (!$referer) return null;
        $parsed = parse_url($referer);
        return $parsed['host'] ?? null;
    }
}
