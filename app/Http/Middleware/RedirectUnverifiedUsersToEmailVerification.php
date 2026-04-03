<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectUnverifiedUsersToEmailVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && method_exists($user, 'hasVerifiedEmail')
            && ! $user->hasVerifiedEmail()
            && ($request->is('app') || $request->is('app/*'))
            && ! $request->is('app/login')
            && ! $request->is('app/login/*')
            && ! $request->is('app/invitations/*/accept')
            && ! $request->is('app/invitations/*/setup')
        ) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
