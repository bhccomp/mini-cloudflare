<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressSubscriberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PluginFreeTokenVerifyController extends Controller
{
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
