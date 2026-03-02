<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\AvailabilityRecentChecksTable;
use App\Models\Site;
use App\Services\AvailabilityMonitorService;
use Filament\Actions\Action;

class AvailabilityMonitorPage extends BaseProtectionPage
{
    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?string $slug = 'availability-monitor';

    protected static ?int $navigationSort = -20;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Availability Monitor';

    protected static ?string $title = 'Availability Monitor';

    protected string $view = 'filament.app.pages.protection.availability-monitor';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkNow')
                ->label('Check now')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->action('checkNow')
                ->disabled(fn (): bool => ! $this->site),
            Action::make('runDue')
                ->label('Run due checks')
                ->icon('heroicon-m-bolt')
                ->color('gray')
                ->action('runDueChecks')
                ->disabled(fn (): bool => ! auth()->user()?->currentOrganization),
        ];
    }

    protected function getFooterWidgets(): array
    {
        if (! $this->site) {
            return [];
        }

        return [
            AvailabilityRecentChecksTable::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    public function checkNow(): void
    {
        if (! $this->site instanceof Site) {
            return;
        }

        $check = app(AvailabilityMonitorService::class)->runCheck($this->site);
        $this->dispatch('refresh');

        $statusLabel = $check->status === 'up' ? 'UP' : 'DOWN';
        $latency = $check->latency_ms ? " ({$check->latency_ms} ms)" : '';

        $this->notify("Availability check: {$statusLabel}{$latency}");
    }

    public function runDueChecks(): void
    {
        $organization = auth()->user()?->currentOrganization;

        if (! $organization) {
            return;
        }

        $count = app(AvailabilityMonitorService::class)->runDueChecksForOrganization($organization);
        $this->dispatch('refresh');
        $this->notify("Queued and ran {$count} due availability checks.");
    }

    public function cadenceLabel(): string
    {
        if (! $this->site) {
            return '-';
        }

        return app(AvailabilityMonitorService::class)->intervalLabelForSite($this->site);
    }
}
