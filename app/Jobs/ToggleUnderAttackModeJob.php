<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ToggleUnderAttackModeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $siteId, public bool $enabled, public ?int $actorId = null)
    {
        $this->onQueue('default');
    }

    public function handle(AwsEdgeService $aws): void
    {
        $site = Site::query()->findOrFail($this->siteId);
        $result = $aws->setUnderAttackMode($site, $this->enabled);

        if ($result['changed'] ?? false) {
            $site->update(['under_attack' => $this->enabled]);
        }

        AuditLog::create([
            'actor_id' => $this->actorId,
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'action' => 'waf.under_attack',
            'status' => 'success',
            'message' => $result['message'] ?? 'Under-attack updated.',
            'meta' => ['enabled' => $this->enabled] + $result,
        ]);
    }
}
