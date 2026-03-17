<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressChecksumCacheService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

#[Group('WordPress Signature & Checksum Feed', 'Public endpoints used by the WordPress plugin to fetch package checksums and FirePhage malware signature updates.', 20)]
class PackageChecksumController extends Controller
{
    #[Endpoint(
        operationId: 'pluginChecksums',
        title: 'Get WordPress package checksums',
        description: 'Returns cached WordPress.org checksums for a plugin or theme version so the FirePhage plugin can verify package integrity without hammering WordPress.org directly.'
    )]
    #[QueryParameter('type', 'Package type to resolve.', required: true, type: 'string', example: 'plugin')]
    #[QueryParameter('slug', 'Plugin or theme slug.', required: true, type: 'string', example: 'woocommerce')]
    #[QueryParameter('version', 'Package version to fetch checksums for.', required: true, type: 'string', example: '9.8.1')]
    #[Response(200, 'Checksums were returned from the FirePhage cache or fetched from WordPress.org.')]
    #[Response(502, 'WordPress.org could not be reached and no usable cached checksum set was available.')]
    public function __invoke(Request $request, WordPressChecksumCacheService $service): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:plugin,theme'],
            'slug' => ['required', 'string', 'max:191'],
            'version' => ['required', 'string', 'max:64'],
        ]);

        try {
            $payload = $service->getChecksums(
                $validated['type'],
                $validated['slug'],
                $validated['version'],
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
