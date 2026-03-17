<?php

namespace App\Http\Middleware;

use App\Services\DemoModeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictDemoHostToDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(DemoModeService::class)->active($request)) {
            return $next($request);
        }

        if ($request->is('login') || $request->is('login/*')) {
            return redirect('/app/login');
        }

        if (
            $request->is('app')
            || $request->is('app/*')
            || $request->is('livewire/*')
            || $request->is('livewire-*')
            || $request->is('livewire-*/*')
            || $request->is('logout')
            || $request->is('app/logout')
            || $request->is('up')
        ) {
            return $next($request);
        }

        return redirect('/app');
    }
}
