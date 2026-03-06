<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\Bunny\BunnyEdgeErrorPageService;
use App\Services\Edge\Providers\BunnyCdnProvider;
use Illuminate\Console\Command;

class BunnySyncEdgeErrorPagesCommand extends Command
{
    protected $signature = 'bunny:sync-edge-error-pages
        {--attach : Attach the shared script to all existing Bunny zones after syncing}
        {--site-id= : Attach only to a single site after syncing}';

    protected $description = 'Sync the shared Bunny edge middleware for branded error pages and optionally attach it to Bunny pull zones.';

    public function handle(BunnyEdgeErrorPageService $scripts, BunnyCdnProvider $provider): int
    {
        $result = $scripts->syncSharedScript();

        $this->info(sprintf(
            'Shared Bunny edge error script synced (%s, id %d).',
            $result['created'] ? 'created' : 'updated',
            $result['script_id'],
        ));

        $siteId = (int) $this->option('site-id');
        $attach = (bool) $this->option('attach') || $siteId > 0;

        if (! $attach) {
            return self::SUCCESS;
        }

        $query = Site::query()
            ->where('provider', Site::PROVIDER_BUNNY)
            ->whereNotNull('provider_resource_id')
            ->orderBy('id');

        if ($siteId > 0) {
            $query->whereKey($siteId);
        }

        $attached = 0;
        $failed = 0;

        $query->chunkById(100, function ($sites) use ($provider, &$attached, &$failed): void {
            foreach ($sites as $site) {
                try {
                    $result = $provider->syncEdgeErrorPages($site);

                    if ($result['changed'] ?? false) {
                        $attached++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn(sprintf('Site %d (%s): %s', $site->id, $site->apex_domain, $e->getMessage()));
                }
            }
        });

        $this->info("Attachment completed. Updated: {$attached}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
