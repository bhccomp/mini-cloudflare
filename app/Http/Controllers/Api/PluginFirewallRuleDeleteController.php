<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\PluginSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PluginFirewallRuleDeleteController extends Controller
{
    public function __invoke(Request $request, PluginSiteService $service): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'min:1'],
            'rule_id' => ['required', 'integer', 'min:1'],
            'target' => ['nullable', 'string'],
        ]);

        try {
            $connection = $service->authenticate($request, $validated['site_id']);
            $payload = $service->removeFirewallRule(
                $connection,
                (int) $validated['rule_id'],
                isset($validated['target']) ? (string) $validated['target'] : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 400);
        }

        return response()->json($payload);
    }
}
