<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectPublicHomeToEarlyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('marketing.early_access_enabled', true)) {
            return $next($request);
        }

        if ($request->user()) {
            return $next($request);
        }

        $requestIp = (string) $request->ip();
        $bypassIps = (array) config('marketing.early_access_bypass_ips', []);

        if (in_array($requestIp, $bypassIps, true)) {
            return $next($request);
        }

        return redirect()->route('early-access');
    }
}
