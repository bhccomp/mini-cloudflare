<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectUnauthenticatedAppToLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $isInvitationRoute = $request->is('app/invitations/*/accept')
            || $request->is('app/invitations/*/setup');

        if (
            ! $request->user()
            && ($request->is('app') || $request->is('app/*'))
            && ! $request->is('app/login')
            && ! $request->is('app/login/*')
            && ! $isInvitationRoute
        ) {
            return redirect('/login');
        }

        return $next($request);
    }
}
