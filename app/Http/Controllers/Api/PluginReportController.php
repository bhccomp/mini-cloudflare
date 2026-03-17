<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
class PluginReportController extends Controller
{
    #[Endpoint(
        operationId: 'pluginReportUpload',
        title: 'Upload a WordPress plugin report',
        description: 'Accepts the plugin report payload for a connected site. The report is later used by the SaaS dashboard to show WordPress health, malware scan, and local protection data.'
    )]
    #[Header('Authorization', 'Bearer site token returned by the plugin connect endpoint.', type: 'string', required: true, example: 'Bearer fp_site_token_here')]
    #[BodyParameter('site_id', 'Connected FirePhage site ID.', required: true, type: 'int', example: 12)]
    #[BodyParameter('report', 'Plugin report payload containing site, health, malware scan, and local protection data.', required: true, type: 'array', example: ['generated_at' => '2026-03-17T10:00:00Z', 'site' => ['wp_version' => '6.8.1'], 'health' => ['summary' => ['good' => 8, 'warning' => 1, 'critical' => 0]]])]
    #[Response(200, 'Plugin report was accepted and stored for the connected site.')]
    #[Response(401, 'Plugin authentication failed or the site token is invalid.')]
    public function __invoke(Request $request, PluginSiteService $service): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'min:1'],
            'report' => ['required', 'array'],
        ]);

        try {
            $connection = $service->authenticate($request, $validated['site_id']);
            $payload = $service->storeReport($connection, $validated['report']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Plugin report received.',
        ] + $payload);
    }
}
