<?php

namespace App\Filament\App\Pages;

use App\Models\Site;

class SiteStatusHubPage extends BaseProtectionPage
{
    protected static ?string $slug = 'status-hub';

    protected static ?int $navigationSort = -3;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Status Hub';

    protected static ?string $title = 'Site Status Hub';

    protected string $view = 'filament.app.pages.site-status-hub';

    public function currentStep(): int
    {
        return match ($this->site?->status) {
            Site::STATUS_DRAFT => 1,
            Site::STATUS_PENDING_DNS_VALIDATION => 2,
            Site::STATUS_DEPLOYING => 3,
            Site::STATUS_READY_FOR_CUTOVER, Site::STATUS_ACTIVE => 4,
            Site::STATUS_FAILED => 1,
            default => 1,
        };
    }

    public function acmValidationRecords(): array
    {
        return (array) data_get($this->site?->required_dns_records, 'acm_validation', []);
    }

    public function cutoverRecords(): array
    {
        $target = (string) ($this->site?->cloudfront_domain_name ?? '');

        if ($target === '') {
            return [];
        }

        return [
            [
                'host' => 'www.'.$this->site->apex_domain,
                'type' => 'CNAME',
                'value' => $target,
                'ttl' => 'Auto',
                'note' => 'Point www directly to your CloudFront domain.',
            ],
            [
                'host' => $this->site->apex_domain,
                'type' => 'ALIAS / ANAME',
                'value' => $target,
                'ttl' => 'Auto',
                'note' => 'Use provider flattening/aliasing for apex records.',
            ],
        ];
    }
}
