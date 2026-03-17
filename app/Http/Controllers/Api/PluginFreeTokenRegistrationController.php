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
class PluginFreeTokenRegistrationController extends Controller
{
    #[Endpoint(
        operationId: 'pluginFreeTokenRegister',
        title: 'Register for a free signature token',
        description: 'Starts the email verification flow for the free FirePhage signature feed. The plugin receives a status token that it can poll until the email link has been confirmed.'
    )]
    #[BodyParameter('email', 'Address that should receive the verification email and future free-token notices.', required: true, type: 'string', example: 'owner@example.com')]
    #[BodyParameter('home_url', 'WordPress home URL.', required: true, type: 'string', example: 'https://store.example.com')]
    #[BodyParameter('site_url', 'WordPress site URL.', required: true, type: 'string', example: 'https://store.example.com')]
    #[BodyParameter('admin_email', 'WordPress admin email.', type: 'string', example: 'ops@example.com')]
    #[BodyParameter('plugin_version', 'Installed FirePhage plugin version.', type: 'string', example: '1.4.0')]
    #[BodyParameter('marketing_opt_in', 'Whether the site owner opted into future FirePhage marketing emails.', type: 'bool', example: false)]
    #[Response(200, 'Verification email was sent and the plugin can begin polling the returned status token.')]
    #[Response(422, 'The registration request was invalid or missing a valid site URL/email combination.')]
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
