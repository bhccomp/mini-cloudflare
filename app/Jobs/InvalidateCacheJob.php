<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class InvalidateCacheJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $siteId,
        public array $paths = ['/*'],
        public ?int $triggeredByUserId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(AwsEdgeService $aws): void
    {
        $site = Site::query()->findOrFail($this->siteId);

        $result = $aws->invalidateCache($site, $this->paths);

        AuditLog::create([
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'user_id' => $this->triggeredByUserId,
            'action' => 'cloudfront.invalidate',
            'status' => ($result['changed'] ?? false) ? 'success' : 'info',
            'message' => $result['message'] ?? 'Cache invalidation requested.',
            'context' => $result,
        ]);
    }
}
