<?php

namespace App\Services\Marketing;

use App\Models\BlogPost;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class OpenAiBlogCoverImageService
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @return array{public_url:string,relative_path:string,prompt:string}
     */
    public function generateForPost(BlogPost $post): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        $model = (string) config('services.openai.image_model', 'gpt-image-1');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $prompt = $this->buildPrompt($post);

        $response = $this->http
            ->baseUrl('https://api.openai.com/v1')
            ->withToken($apiKey)
            ->timeout(180)
            ->acceptJson()
            ->post('/images/generations', [
                'model' => $model,
                'prompt' => $prompt,
                'size' => '1536x1024',
                'output_format' => 'png',
                'quality' => 'high',
                'background' => 'opaque',
            ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        $imageBase64 = data_get($response->json(), 'data.0.b64_json');

        if (! is_string($imageBase64) || trim($imageBase64) === '') {
            throw new RuntimeException('OpenAI image generation did not return image bytes.');
        }

        $imageBytes = base64_decode($imageBase64, true);

        if ($imageBytes === false || $imageBytes === '') {
            throw new RuntimeException('OpenAI image generation returned invalid image data.');
        }

        $relativePath = 'images/blog-covers/'.$post->slug.'-cover.png';
        $absolutePath = public_path($relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        $this->writeCroppedBanner($imageBytes, $absolutePath);

        return [
            'public_url' => asset($relativePath),
            'relative_path' => $relativePath,
            'prompt' => $prompt,
        ];
    }

    private function buildPrompt(BlogPost $post): string
    {
        $title = trim($post->title);
        $excerpt = trim((string) $post->excerpt);
        $keywords = implode(', ', $this->keywordsForPost($post));
        $scene = $this->sceneForPost($post);

        return trim(implode("\n", array_filter([
            'Create a premium editorial cover illustration for a FirePhage blog article.',
            'Topic: '.$title,
            $excerpt !== '' ? 'Context: '.$excerpt : null,
            'Primary visual concept: '.$scene,
            $keywords !== '' ? 'Visual cues: '.$keywords : null,
            'Style: dark security SaaS aesthetic, deep navy background, subtle cyan highlights, modern edge-infrastructure mood, cinematic lighting, crisp composition, believable technical atmosphere.',
            'Composition: wide website article hero image, strong focal point, clean negative space, balanced layout, readable at thumbnail size.',
            'Use icons, layers, flows, shields, traffic paths, and infrastructure forms instead of labels or typography.',
            'Strictly no text, no letters, no numbers, no logos, no product UI screenshots, no watermarks, no fake dashboard labels.',
            'Do not render words anywhere in the image. If text would appear, replace it with abstract shapes or simple symbols.',
        ])));
    }

    /**
     * @return list<string>
     */
    private function keywordsForPost(BlogPost $post): array
    {
        $haystack = strtolower(implode(' ', array_filter([
            $post->title,
            $post->excerpt,
            Str::limit(strip_tags((string) $post->content_markdown), 600, ''),
        ])));

        $keywords = [];

        if (str_contains($haystack, 'api')) {
            $keywords[] = 'protected request flows';
            $keywords[] = 'endpoint traffic paths';
            $keywords[] = 'validated connections';
        }

        if (str_contains($haystack, 'edge')) {
            $keywords[] = 'edge network boundary';
            $keywords[] = 'protected origin layer';
            $keywords[] = 'traffic routing';
        }

        if (str_contains($haystack, 'waf') || str_contains($haystack, 'firewall')) {
            $keywords[] = 'layered inspection barriers';
            $keywords[] = 'filtered request paths';
            $keywords[] = 'threat screening';
        }

        if (str_contains($haystack, 'woocommerce')) {
            $keywords[] = 'store request flows';
            $keywords[] = 'protected checkout path';
        }

        if (str_contains($haystack, 'wordpress')) {
            $keywords[] = 'wordpress origin';
        }

        return array_values(array_unique($keywords));
    }

    private function sceneForPost(BlogPost $post): string
    {
        $haystack = strtolower(implode(' ', array_filter([
            $post->title,
            $post->excerpt,
            Str::limit(strip_tags((string) $post->content_markdown), 600, ''),
        ])));

        if (str_contains($haystack, 'api')) {
            return 'A protected request flow moving through secure API gateways toward a WordPress origin, with hostile traffic filtered out at the edge.';
        }

        if (str_contains($haystack, 'waf') || str_contains($haystack, 'firewall')) {
            return 'A managed firewall layer intercepting malicious web requests before they reach a WordPress or WooCommerce site, shown as structured security layers rather than abstract clipart.';
        }

        if (str_contains($haystack, 'edge')) {
            return 'A WordPress site protected at the edge, with traffic being inspected and filtered before it reaches the origin infrastructure.';
        }

        return 'A WordPress security illustration showing hostile traffic being filtered at the edge before reaching the origin server.';
    }

    private function writeCroppedBanner(string $imageBytes, string $destinationPath): void
    {
        $source = imagecreatefromstring($imageBytes);

        if ($source === false) {
            throw new RuntimeException('Generated image could not be decoded.');
        }

        try {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $targetWidth = 1500;
            $targetHeight = 680;

            $sourceRatio = $sourceWidth / max($sourceHeight, 1);
            $targetRatio = $targetWidth / $targetHeight;

            if ($sourceRatio > $targetRatio) {
                $cropHeight = $sourceHeight;
                $cropWidth = (int) round($cropHeight * $targetRatio);
                $srcX = (int) max(0, floor(($sourceWidth - $cropWidth) / 2));
                $srcY = 0;
            } else {
                $cropWidth = $sourceWidth;
                $cropHeight = (int) round($cropWidth / $targetRatio);
                $srcX = 0;
                $srcY = (int) max(0, floor(($sourceHeight - $cropHeight) * 0.18));
            }

            $target = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($target === false) {
                throw new RuntimeException('Generated image canvas could not be created.');
            }

            try {
                imagealphablending($target, true);
                imagesavealpha($target, true);

                if (! imagecopyresampled(
                    $target,
                    $source,
                    0,
                    0,
                    $srcX,
                    $srcY,
                    $targetWidth,
                    $targetHeight,
                    $cropWidth,
                    $cropHeight
                )) {
                    throw new RuntimeException('Generated image could not be resized.');
                }

                imagepng($target, $destinationPath, 9);
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }
}
