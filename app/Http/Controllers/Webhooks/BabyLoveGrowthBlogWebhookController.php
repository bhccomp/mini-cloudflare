<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Marketing\BlogImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BabyLoveGrowthBlogWebhookController extends Controller
{
    public function __invoke(Request $request, BlogImportService $imports): JsonResponse
    {
        $configuredToken = (string) config('services.babylovegrowth.blog_webhook_token');
        $providedToken = trim((string) $request->bearerToken());

        abort_unless($configuredToken !== '' && hash_equals($configuredToken, $providedToken), 401);

        $payload = $request->validate([
            'id' => ['nullable'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'metaDescription' => ['nullable', 'string'],
            'content_markdown' => ['required_without:content_html', 'nullable', 'string'],
            'content_html' => ['required_without:content_markdown', 'nullable', 'string'],
            'heroImageUrl' => ['nullable', 'url', 'max:2048'],
            'languageCode' => ['nullable', 'string', 'max:10'],
            'publicUrl' => ['nullable', 'url', 'max:2048'],
            'createdAt' => ['nullable', 'date'],
            'jsonLd' => ['nullable', 'array'],
            'faqJsonLd' => ['nullable', 'array'],
        ]);

        $result = $imports->import($payload, [
            'title' => trim((string) $payload['title']),
            'slug' => Str::slug((string) ($payload['slug'] ?? $payload['title'])),
            'meta_description' => $payload['metaDescription'] ?? null,
            'content_markdown' => $payload['content_markdown'] ?? null,
            'content_html' => $payload['content_html'] ?? null,
            'hero_image_url' => $payload['heroImageUrl'] ?? null,
            'public_url' => $payload['publicUrl'] ?? null,
            'created_at' => $payload['createdAt'] ?? null,
            'generate_cover' => true,
        ]);

        return response()->json([
            'ok' => true,
            'post_id' => $result['post']->id,
            'slug' => $result['post']->slug,
            'created' => $result['created'],
        ]);
    }
}
