<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\PluginSiteService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

#[Group('WordPress Plugin Integration', 'Endpoints used by the FirePhage WordPress plugin to connect sites, upload reports, and fetch paid telemetry.', 10)]
class PluginFirewallSummaryController extends Controller
{
    #[Endpoint(
        operationId: 'pluginFirewallSummary',
        title: 'Get WordPress firewall summary',
        description: 'Returns firewall status, blocking metrics, and recent activity for a connected plugin site. Live telemetry is available only when the site is covered by an active or trialing paid plan.'
    )]
    #[Header('Authorization', 'Bearer site token returned by the plugin connect endpoint.', type: 'string', required: true, example: 'Bearer fp_site_token_here')]
    #[QueryParameter('site_id', 'Connected FirePhage site ID.', required: true, type: 'int', example: 12)]
    #[Response(200, 'Firewall summary was returned for the authenticated site.')]
    #[Response(401, 'Plugin authentication failed or the site token is invalid.')]
    public function __invoke(Request $request, PluginSiteService $service): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $connection = $service->authenticate($request, $validated['site_id']);
            $payload = $service->firewallSummary($connection);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        }

        return response()->json($payload);
    }
}
