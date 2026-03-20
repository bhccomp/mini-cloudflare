<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressReferenceFileService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

#[Group('WordPress Signature & Checksum Feed', 'Public endpoints used by the WordPress plugin to fetch package checksums and official reference files for compare-on-demand review.', 20)]
class PackageReferenceFileController extends Controller
{
    #[Endpoint(
        operationId: 'pluginReferenceFile',
        title: 'Get WordPress package reference file',
        description: 'Returns the official WordPress.org package file contents for a specific core, plugin, or theme file so the FirePhage plugin can compare a modified local file against the trusted source on demand.'
    )]
    #[QueryParameter('type', 'Package type to resolve.', required: true, type: 'string', example: 'plugin')]
    #[QueryParameter('version', 'Package version to fetch.', required: true, type: 'string', example: '9.8.1')]
    #[QueryParameter('path', 'File path within the package.', required: true, type: 'string', example: 'includes/class-example.php')]
    #[QueryParameter('slug', 'Plugin or theme slug. Not required for core.', required: false, type: 'string', example: 'woocommerce')]
    #[Response(200, 'Reference file content was returned from cache or fetched from WordPress.org.')]
    #[Response(502, 'WordPress.org could not be reached or did not provide the requested file.')]
    public function __invoke(Request $request, WordPressReferenceFileService $service): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:core,plugin,theme'],
            'slug' => ['nullable', 'string', 'max:191'],
            'version' => ['required', 'string', 'max:64'],
            'path' => ['required', 'string', 'max:500'],
        ]);

        try {
            $payload = $service->getReferenceFile(
                $validated['type'],
                $validated['slug'] ?? null,
                $validated['version'],
                $validated['path'],
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
