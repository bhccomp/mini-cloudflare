<?php

namespace App\Filament\App\Pages;

use App\Services\Analytics\AnalyticsSyncManager;

class CdnPage extends BaseProtectionPage
{
    protected static ?string $slug = 'cdn';

    protected static ?int $navigationSort = 0;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'CDN';

    protected static ?string $title = 'CDN';

    protected string $view = 'filament.app.pages.protection.cdn';

    public function refreshCdnMetrics(): void
    {
        if (! $this->site) {
            return;
        }

        app(AnalyticsSyncManager::class)->syncSiteMetrics($this->site);
        $this->refreshSite();
        $this->notify('CDN metrics refreshed');
    }

    public function cdnActionPrefix(): string
    {
        return $this->site?->provider === \App\Models\Site::PROVIDER_BUNNY ? 'edge.' : 'cloudfront.';
    }
}
