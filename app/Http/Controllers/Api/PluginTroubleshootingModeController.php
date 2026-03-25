<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\PluginSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PluginTroubleshootingModeController extends Controller
{
    public function __invoke(Request $request, PluginSiteService $service): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'min:1'],
            'enabled' => ['required', 'boolean'],
        ]);

        try {
            $connection = $service->authenticate($request, $validated['site_id']);
            $payload = $service->setPluginTroubleshootingMode($connection, (bool) $validated['enabled']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 400);
        }

        return response()->json($payload);
    }
}
