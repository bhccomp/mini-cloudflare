<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressSubscriberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PluginFreeTokenRegistrationController extends Controller
{
    public function __invoke(Request $request, WordPressSubscriberService $service): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'home_url' => ['required', 'url', 'max:255'],
            'site_url' => ['required', 'url', 'max:255'],
            'admin_email' => ['nullable', 'email', 'max:255'],
            'plugin_version' => ['nullable', 'string', 'max:32'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);

        try {
            $payload = $service->register($validated);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Verification email sent. FirePhage will activate signature updates after the email link is confirmed.',
            'status_token' => $payload['status_token'],
            'email' => $payload['email'],
            'site_host' => $payload['site_host'],
            'status' => $payload['status'],
        ]);
    }
}
