<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressChecksumCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PackageChecksumController extends Controller
{
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
