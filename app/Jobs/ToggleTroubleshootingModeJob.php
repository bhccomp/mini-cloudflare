<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Edge\EdgeProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ToggleTroubleshootingModeJob implements ShouldQueue
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

        if (! method_exists($provider, 'setTroubleshootingMode')) {
            throw new \RuntimeException('Troubleshooting mode is not supported for this edge provider.');
        }

        $result = $provider->setTroubleshootingMode($site, $this->enabled);

        $site->update([
            'troubleshooting_mode' => $this->enabled,
            'development_mode' => (bool) ($result['development_mode'] ?? $site->development_mode),
        ]);

        AuditLog::create([
            'actor_id' => $this->actorId,
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'action' => 'edge.troubleshooting_mode',
            'status' => ($result['changed'] ?? false) ? 'success' : 'info',
            'message' => $result['message'] ?? 'Troubleshooting mode updated.',
            'meta' => ['enabled' => $this->enabled, 'provider' => $provider->key()] + $result,
        ]);
    }
}
