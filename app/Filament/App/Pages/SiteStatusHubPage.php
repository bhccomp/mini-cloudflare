<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Resources\SiteResource;
use App\Jobs\CheckAcmDnsValidationJob;
use App\Models\Site;
use Filament\Actions\Action;

class SiteStatusHubPage extends BaseProtectionPage
{
    protected static ?string $slug = 'status-hub';

    protected static ?int $navigationSort = -3;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Status Hub';

    protected static ?string $title = 'Site Status Hub';

    protected string $view = 'filament.app.pages.site-status-hub';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addSite')
                ->label('Add Site')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(SiteResource::getUrl('create')),
        ];
    }

    public function isBunnyFlow(): bool
    {
        return ($this->site?->provider ?? '') === Site::PROVIDER_BUNNY;
    }

    public function steps(): array
    {
        if ($this->isBunnyFlow()) {
            return [
                1 => 'Create site',
                2 => 'Provision edge',
                3 => 'Update DNS',
                4 => 'Verify cutover',
                5 => 'Protection active',
            ];
        }

        return [
            1 => 'Create',
            2 => 'Validate domain',
            3 => 'Deploy edge',
            4 => 'Cutover DNS',
        ];
    }

    public function currentStep(): int
    {
        if ($this->isBunnyFlow()) {
            return match ($this->site?->onboarding_status) {
                Site::ONBOARDING_DRAFT => 1,
                Site::ONBOARDING_PROVISIONING_EDGE => 2,
                Site::ONBOARDING_PENDING_DNS_CUTOVER => 3,
                Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING => 4,
                Site::ONBOARDING_LIVE => 5,
                Site::ONBOARDING_FAILED => 2,
                default => 1,
            };
        }

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

    public function trafficDnsRecords(): array
    {
        $records = (array) data_get($this->site?->required_dns_records, 'traffic', []);

        if ($records !== []) {
            return $records;
        }

        return $this->cutoverRecords();
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
                'note' => 'Point www directly to the edge domain.',
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

    public function onboardingLabel(): string
    {
        $status = $this->site?->onboarding_status;

        return Site::onboardingStatuses()[$status] ?? 'Draft';
    }

    public function autoCheckBunnyCutover(): void
    {
        $this->refreshSite();

        if (! $this->site || ! $this->isBunnyFlow()) {
            return;
        }

        if (! in_array($this->site->onboarding_status, [
            Site::ONBOARDING_PENDING_DNS_CUTOVER,
            Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING,
        ], true)) {
            return;
        }

        if ($this->site->last_checked_at && $this->site->last_checked_at->gt(now()->subSeconds(10))) {
            return;
        }

        CheckAcmDnsValidationJob::dispatch($this->site->id, auth()->id());
    }

    public function pollStatus(): void
    {
        parent::pollStatus();

        if ($this->isBunnyFlow()) {
            $this->autoCheckBunnyCutover();
        }
    }
}
