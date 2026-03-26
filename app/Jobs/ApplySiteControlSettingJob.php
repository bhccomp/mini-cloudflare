<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\Edge\EdgeProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ApplySiteControlSettingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $siteId,
        public string $setting,
        public mixed $value,
        public ?int $actorId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(EdgeProviderManager $providers): void
    {
        $site = Site::query()->findOrFail($this->siteId);
        $provider = $providers->forSite($site);
        $result = $provider->applySiteControlSetting($site, $this->setting, $this->value);

        AuditLog::create([
            'actor_id' => $this->actorId,
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'action' => 'site.control.'.$this->setting,
            'status' => ($result['changed'] ?? false) ? 'success' : 'info',
            'message' => (string) ($result['message'] ?? ('Control update processed for '.$this->setting.'.')),
            'meta' => [
                'setting' => $this->setting,
                'value' => $this->value,
                'provider' => $provider->key(),
            ] + $result,
        ]);
    }
}
