<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\PluginSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PluginConnectController extends Controller
{
    public function __invoke(Request $request, PluginSiteService $service): JsonResponse
    {
        $validated = $request->validate([
            'connection_token' => ['required', 'string', 'max:255'],
            'home_url' => ['nullable', 'url', 'max:255'],
            'site_url' => ['nullable', 'url', 'max:255'],
            'admin_email' => ['nullable', 'email', 'max:255'],
            'plugin_version' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $payload = $service->connect((string) $validated['connection_token'], $validated);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($payload);
    }
}
