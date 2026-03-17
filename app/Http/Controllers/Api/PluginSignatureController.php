<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressMalwareSignatureService;
use App\Services\WordPress\WordPressSubscriberService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

#[Group('WordPress Signature & Checksum Feed', 'Public endpoints used by the WordPress plugin to fetch package checksums and FirePhage malware signature updates.', 20)]
class PluginSignatureController extends Controller
{
    #[Endpoint(
        operationId: 'pluginSignatures',
        title: 'Get the FirePhage malware signature manifest',
        description: 'Returns the current FirePhage malware signature manifest for verified free-token subscribers. The plugin merges this remote manifest with its bundled fallback snapshot.'
    )]
    #[Header('Authorization', 'Bearer free signature token returned after verification.', type: 'string', required: true, example: 'Bearer fpf_signature_token_here')]
    #[Response(200, 'The signature manifest was returned.')]
    #[Response(401, 'The FirePhage signature token was missing or invalid.')]
    public function __invoke(Request $request, WordPressMalwareSignatureService $service, WordPressSubscriberService $subscriberService): JsonResponse
    {
        try {
            $subscriberService->authenticate($request);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        }

        return response()
            ->json($service->manifest())
            ->header('Cache-Control', 'private, max-age=21600');
    }
}
