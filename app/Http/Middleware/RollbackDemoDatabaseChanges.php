<?php

namespace App\Http\Middleware;

use App\Services\DemoModeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RollbackDemoDatabaseChanges
{
    public function handle(Request $request, Closure $next): Response
    {
        $demoMode = app(DemoModeService::class);

        if (! $demoMode->active($request)) {
            return $next($request);
        }

        DB::beginTransaction();

        try {
            return $next($request);
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }
}
