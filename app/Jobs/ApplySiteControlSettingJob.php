<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Site;
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

    public function handle(): void
    {
        $site = Site::query()->findOrFail($this->siteId);

        AuditLog::create([
            'actor_id' => $this->actorId,
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'action' => 'site.control.'.$this->setting,
            'status' => 'info',
            'message' => 'Control update queued for '.$this->setting.'.',
            'meta' => [
                'setting' => $this->setting,
                'value' => $this->value,
                'placeholder' => true,
            ],
        ]);
    }
}
