<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\PluginSiteService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

#[Group('WordPress Plugin Integration', 'Endpoints used by the FirePhage WordPress plugin to connect sites, upload reports, and fetch paid telemetry.', 10)]
class PluginConnectController extends Controller
{
    #[Endpoint(
        operationId: 'pluginConnect',
        title: 'Connect a WordPress plugin site',
        description: 'Consumes a short-lived dashboard-generated connection token and returns the long-lived site bearer token that the plugin will use for future authenticated API calls.'
    )]
    #[BodyParameter('connection_token', 'Short-lived token generated from the FirePhage WordPress page for a specific site.', required: true, type: 'string', example: 'fps_connection_token_here')]
    #[BodyParameter('home_url', 'WordPress home URL.', type: 'string', example: 'https://store.example.com')]
    #[BodyParameter('site_url', 'WordPress site URL.', type: 'string', example: 'https://store.example.com')]
    #[BodyParameter('admin_email', 'WordPress admin email.', type: 'string', example: 'ops@example.com')]
    #[BodyParameter('plugin_version', 'Installed FirePhage plugin version.', type: 'string', example: '1.4.0')]
    #[Response(200, 'The plugin site was connected successfully and a site token was returned.')]
    #[Response(422, 'The connection token was invalid, expired, or already consumed.')]
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
