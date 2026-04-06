<?php

namespace App\Http\Middleware;

use App\Services\Auth\AdminImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TouchImpersonationNotificationSuppression
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (! $user) {
            return $response;
        }

        $impersonation = app(AdminImpersonationService::class);
        if (! $impersonation->isImpersonating()) {
            return $response;
        }

        $impersonation->touchOrganizationNotificationSuppression($user->currentOrganization);

        return $response;
    }
}
