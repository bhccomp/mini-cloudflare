<?php

namespace App\Filament\App\Resources\SiteResource\Pages;

use App\Filament\App\Pages\SiteStatusHubPage;
use App\Filament\App\Resources\SiteResource;
use App\Models\Site;
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
        $data['provider'] = (string) config('edge.default_provider', Site::PROVIDER_AWS);
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

        Notification::make()
            ->title('Site created')
            ->body('Your site is selected. Continue setup in the Site Status Hub.')
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
