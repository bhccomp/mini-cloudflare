<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressSubscriberService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

#[Group('WordPress Signature & Checksum Feed', 'Public endpoints used by the WordPress plugin to fetch package checksums and FirePhage malware signature updates.', 20)]
class PluginFreeTokenStatusController extends Controller
{
    #[Endpoint(
        operationId: 'pluginFreeTokenStatus',
        title: 'Check free token verification status',
        description: 'Returns whether a free signature token request is still pending or has been verified. When verified, the active token is returned so the plugin can start using the signature feed.'
    )]
    #[QueryParameter('status_token', 'Status token returned by the free-token registration endpoint.', required: true, type: 'string', example: 'fps_status_token_here')]
    #[Response(200, 'Current verification status was returned.')]
    #[Response(422, 'The provided status token was invalid.')]
    public function __invoke(Request $request, WordPressSubscriberService $service): JsonResponse
    {
        $validated = $request->validate([
            'status_token' => ['required', 'string', 'max:255'],
        ]);

        try {
            $payload = $service->status((string) $validated['status_token']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($payload);
    }
}
