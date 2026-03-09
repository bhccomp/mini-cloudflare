<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\PluginSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PluginPerformanceSummaryController extends Controller
{
    public function __invoke(Request $request, PluginSiteService $service): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $connection = $service->authenticate($request, $validated['site_id']);
            $payload = $service->performanceSummary($connection);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        }

        return response()->json($payload);
    }
}
