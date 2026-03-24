<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\PluginAlertChannelService;
use App\Services\WordPress\PluginSiteService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

#[Group('WordPress Plugin Integration', 'Endpoints used by the FirePhage WordPress plugin to connect sites, upload reports, and fetch paid telemetry.', 10)]
class PluginAlertChannelsController extends Controller
{
    #[Endpoint(
        operationId: 'pluginAlertChannelsSync',
        title: 'Sync plugin alert channel settings',
        description: 'Stores site-scoped Slack and webhook destinations submitted from the connected WordPress plugin.'
    )]
    #[Header('Authorization', 'Bearer site token returned by the plugin connect endpoint.', type: 'string', required: true, example: 'Bearer fp_site_token_here')]
    #[BodyParameter('site_id', 'Connected FirePhage site ID.', required: true, type: 'int', example: 12)]
    #[BodyParameter('settings', 'Notification settings from the plugin, including email/webhook/slack routing preferences.', required: true, type: 'array')]
    #[Response(200, 'Plugin alert channel settings were stored for the connected site.')]
    #[Response(401, 'Plugin authentication failed or the site token is invalid.')]
    public function __invoke(Request $request, PluginSiteService $siteService, PluginAlertChannelService $alertChannelService): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'min:1'],
            'settings' => ['required', 'array'],
        ]);

        try {
            $connection = $siteService->authenticate($request, $validated['site_id']);
            $alertChannelService->syncChannels($connection, $validated['settings']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Plugin alert channel settings saved.',
        ]);
    }
}
