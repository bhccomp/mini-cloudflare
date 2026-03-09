<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressMalwareSignatureService;
use App\Services\WordPress\WordPressSubscriberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PluginSignatureController extends Controller
{
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
