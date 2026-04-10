<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Marketing\BlogImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OutrankBlogWebhookController extends Controller
{
    public function __invoke(Request $request, BlogImportService $imports): JsonResponse
    {
        $configuredToken = (string) config('services.outrank.blog_webhook_token');
        $providedToken = trim((string) $request->bearerToken());

        abort_unless($configuredToken !== '' && hash_equals($configuredToken, $providedToken), 401);

        $payload = $request->validate([
            'event_type' => ['required', 'string', 'max:100'],
            'timestamp' => ['nullable', 'date'],
            'data' => ['required', 'array'],
            'data.articles' => ['required', 'array', 'min:1'],
            'data.articles.*.id' => ['nullable'],
            'data.articles.*.title' => ['required', 'string', 'max:255'],
            'data.articles.*.slug' => ['nullable', 'string', 'max:255'],
            'data.articles.*.meta_description' => ['nullable', 'string'],
            'data.articles.*.content_markdown' => ['required_without:data.articles.*.content_html', 'nullable', 'string'],
            'data.articles.*.content_html' => ['required_without:data.articles.*.content_markdown', 'nullable', 'string'],
            'data.articles.*.image_url' => ['nullable', 'url', 'max:2048'],
            'data.articles.*.public_url' => ['nullable', 'url', 'max:2048'],
            'data.articles.*.created_at' => ['nullable', 'date'],
            'data.articles.*.tags' => ['nullable', 'array'],
        ]);

        $articles = collect($payload['data']['articles'] ?? []);
        abort_if($articles->isEmpty(), 422, 'The webhook payload must include at least one article.');

        $results = $articles->map(function (array $article) use ($imports, $payload): array {
            $title = trim((string) $article['title']);

            $result = $imports->import($payload, [
                'title' => $title,
                'slug' => Str::slug((string) ($article['slug'] ?? $title)),
                'meta_description' => $article['meta_description'] ?? null,
                'content_markdown' => $article['content_markdown'] ?? null,
                'content_html' => $article['content_html'] ?? null,
                'hero_image_url' => $article['image_url'] ?? null,
                'public_url' => $article['public_url'] ?? null,
                'created_at' => $article['created_at'] ?? ($payload['timestamp'] ?? null),
                'generate_cover' => false,
            ]);

            return [
                'post_id' => $result['post']->id,
                'slug' => $result['post']->slug,
                'created' => $result['created'],
            ];
        });

        return response()->json([
            'ok' => true,
            'event_type' => $payload['event_type'],
            'processed' => $results->count(),
            'created' => $this->countCreated($results),
            'updated' => $results->count() - $this->countCreated($results),
            'posts' => $results->all(),
        ]);
    }

    /**
     * @param  Collection<int, array{created:bool}>  $results
     */
    private function countCreated(Collection $results): int
    {
        return $results->where('created', true)->count();
    }
}
