<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Edge\EdgeProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ToggleUnderAttackModeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $siteId, public bool $enabled, public ?int $actorId = null)
    {
        $this->onQueue('default');
    }

    public function handle(EdgeProviderManager $providers): void
    {
        $site = Site::query()->findOrFail($this->siteId);
        $provider = $providers->forSite($site);
        $result = $provider->setUnderAttackMode($site, $this->enabled);

        if ($result['changed'] ?? false) {
            $site->update(['under_attack' => $this->enabled]);
        }

        AuditLog::create([
            'actor_id' => $this->actorId,
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'action' => 'waf.under_attack',
            'status' => 'success',
            'message' => $result['message'] ?? 'Under-attack mode updated.',
            'meta' => ['enabled' => $this->enabled, 'provider' => $provider->key()] + $result,
        ]);
    }
}
