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
        $origin = $this->normalizeOriginInput((string) ($data['origin_url'] ?? ''));

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
        $data['origin_type'] = 'url';
        $data['www_enabled'] = (bool) ($data['www_enabled'] ?? true);
        $data['origin_url'] = $origin !== '' ? $origin : 'https://'.$domain;

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
                $body = 'Bunny edge provisioning started automatically. Continue DNS setup in the Site Status Hub.';
            } catch (\Throwable $e) {
                $body = 'Site created, but Bunny provisioning failed. Open Status Hub for details and retry.';
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

    protected function normalizeOriginInput(string $value): string
    {
        $input = trim($value);

        if ($input === '') {
            return '';
        }

        if (! str_contains($input, '://')) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);
        if (! is_array($parts) || blank($parts['host'] ?? null)) {
            return $input;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return sprintf('%s://%s%s%s%s%s', $scheme, $host, $port, $path, $query, $fragment);
    }
}
