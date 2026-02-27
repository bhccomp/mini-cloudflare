<?php

namespace App\Filament\App\Resources\SiteResource\Pages;

use App\Filament\App\Pages\SiteStatusHubPage;
use App\Filament\App\Resources\SiteResource;
use App\Jobs\MarkSiteReadyForCutoverJob;
use App\Jobs\ProvisionEdgeDeploymentJob;
use App\Models\Site;
use App\Services\Edge\EdgeProviderManager;
use App\Services\SiteContext;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $domain = $this->normalizeDomainInput((string) ($data['apex_domain'] ?? ''));
        $originIp = $this->normalizeOriginIpInput((string) ($data['origin_ip'] ?? ''));

        $data['apex_domain'] = $domain;
        $data['display_name'] = $domain;
        $data['name'] = $domain;
        $data['status'] = Site::STATUS_DRAFT;
        $provider = strtolower((string) ($data['provider'] ?? config('edge.default_provider', Site::PROVIDER_BUNNY)));
        if (! array_key_exists($provider, Site::providers())) {
            $provider = Site::PROVIDER_BUNNY;
        }

        $data['provider'] = $provider;
        $data['onboarding_status'] = Site::ONBOARDING_DRAFT;
        $data['origin_type'] = 'ip';
        $data['www_enabled'] = (bool) ($data['www_enabled'] ?? true);
        $data['origin_ip'] = $originIp;
        $data['origin_url'] = $originIp !== '' ? 'http://'.$originIp : null;
        $data['origin_host'] = $domain;

        return $data;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Create protection layer');
    }

    protected function getRedirectUrl(): string
    {
        return SiteStatusHubPage::getUrl(['site_id' => $this->record->id]);
    }

    protected function afterCreate(): void
    {
        app(SiteContext::class)->setSelectedSiteId(auth()->user(), $this->record->id);

        $body = 'Your site is selected. Continue setup in the Site Status Hub.';

        if ($this->record->provider === Site::PROVIDER_BUNNY) {
            try {
                (new ProvisionEdgeDeploymentJob($this->record->id, auth()->id()))
                    ->handle(app(EdgeProviderManager::class));
                (new MarkSiteReadyForCutoverJob($this->record->id, auth()->id()))
                    ->handle();
                $body = 'Edge provisioning started automatically. Continue DNS setup in the Site Status Hub.';
            } catch (\Throwable $e) {
                $body = 'Site created, but edge provisioning failed. Open Status Hub for details and retry.';
            }
        }

        Notification::make()
            ->title('Site created')
            ->body($body)
            ->success()
            ->send();
    }

    protected function normalizeDomainInput(string $value): string
    {
        $input = strtolower(trim($value));

        if ($input === '') {
            return '';
        }

        $candidate = str_contains($input, '://') ? $input : 'https://'.ltrim($input, '/');
        $host = parse_url($candidate, PHP_URL_HOST) ?: $input;
        $host = explode(':', $host)[0];
        $host = trim($host, '.');

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    protected function normalizeOriginIpInput(string $value): string
    {
        return trim($value);
    }
}
