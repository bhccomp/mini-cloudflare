<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectWwwToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $preferredHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (blank($preferredHost)) {
            return $next($request);
        }

        $wwwHost = 'www.'.$preferredHost;

        if ($request->getHost() !== $wwwHost) {
            return $next($request);
        }

        return redirect()->to(
            rtrim((string) config('app.url'), '/').'/'.ltrim($request->getRequestUri(), '/'),
            308
        );
    }
}
