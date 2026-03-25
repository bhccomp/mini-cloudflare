<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\PluginSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PluginPurgeCacheController extends Controller
{
    public function __invoke(Request $request, PluginSiteService $service): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'min:1'],
            'paths' => ['nullable', 'array'],
            'paths.*' => ['nullable', 'string'],
        ]);

        try {
            $connection = $service->authenticate($request, $validated['site_id']);
            $payload = $service->purgePluginCache($connection, $validated);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 400);
        }

        return response()->json($payload);
    }
}
