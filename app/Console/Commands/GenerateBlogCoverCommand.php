<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Services\Marketing\OpenAiBlogCoverImageService;
use Illuminate\Console\Command;

class GenerateBlogCoverCommand extends Command
{
    protected $signature = 'blog:generate-cover {slug* : One or more blog post slugs}';

    protected $description = 'Generate and save local OpenAI blog cover images for blog posts.';

    public function handle(OpenAiBlogCoverImageService $coverImages): int
    {
        $slugs = collect((array) $this->argument('slug'))
            ->map(fn ($slug) => trim((string) $slug))
            ->filter()
            ->values();

        if ($slugs->isEmpty()) {
            $this->error('Provide at least one blog post slug.');

            return self::FAILURE;
        }

        $posts = BlogPost::query()
            ->whereIn('slug', $slugs->all())
            ->get()
            ->keyBy('slug');

        foreach ($slugs as $slug) {
            $post = $posts->get($slug);

            if (! $post) {
                $this->warn("Skipped {$slug}: post not found.");

                continue;
            }

            try {
                $generated = $coverImages->generateForPost($post);
                $content = ltrim((string) preg_replace(
                    '/^\s*!\[[^\]]*\]\((?:https?:\/\/|\/)[^)]+\)\s*/iu',
                    '',
                    (string) $post->content_markdown
                ));

                $post->forceFill([
                    'cover_image_url' => $generated['public_url'],
                    'og_image_url' => $generated['public_url'],
                    'content_markdown' => sprintf("![%s](%s)\n\n%s", $post->title, $generated['public_url'], $content),
                ])->save();

                $this->info("Generated cover for {$slug}");
            } catch (\Throwable $exception) {
                $this->error("Failed for {$slug}: ".$exception->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
