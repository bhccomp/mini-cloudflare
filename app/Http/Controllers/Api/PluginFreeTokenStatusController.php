<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressSubscriberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PluginFreeTokenStatusController extends Controller
{
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
