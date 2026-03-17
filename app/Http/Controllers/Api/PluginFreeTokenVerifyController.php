<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressSubscriberService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

#[Group('WordPress Signature & Checksum Feed', 'Public endpoints used by the WordPress plugin to fetch package checksums and FirePhage malware signature updates.', 20)]
class PluginFreeTokenVerifyController extends Controller
{
    #[Endpoint(
        operationId: 'pluginFreeTokenVerify',
        title: 'Verify a free signature token inside the plugin',
        description: 'Completes the free-token email verification flow from inside WordPress after the site owner clicks the emailed verification link.'
    )]
    #[BodyParameter('verification_token', 'Verification token extracted from the verification URL.', required: true, type: 'string', example: 'fpv_verification_token_here')]
    #[Response(200, 'Verification succeeded and the active signature token was returned.')]
    #[Response(422, 'The verification token was invalid or already used.')]
    public function __invoke(Request $request, WordPressSubscriberService $service): JsonResponse
    {
        $validated = $request->validate([
            'verification_token' => ['required', 'string', 'max:255'],
        ]);

        try {
            $payload = $service->verifyForPlugin((string) $validated['verification_token']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($payload);
    }
}
