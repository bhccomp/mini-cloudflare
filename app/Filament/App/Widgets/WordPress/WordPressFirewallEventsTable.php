<?php

namespace App\Filament\App\Widgets\WordPress;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\WordPress\PluginSiteService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class WordPressFirewallEventsTable extends TableWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Firewall Activity';

    public function table(Table $table): Table
    {
        return $table
            ->description('Live firewall events available to WordPress sites covered by an active paid FirePhage plan.')
            ->records(fn (int|string $page = 1, int|string $recordsPerPage = 10): LengthAwarePaginator => $this->records($page, $recordsPerPage))
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                Tables\Columns\TextColumn::make('timestamp')
                    ->label('Time')
                    ->formatStateUsing(fn (mixed $state): string => Carbon::parse((string) $state)->diffForHumans()),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match (strtoupper($state)) {
                        'BLOCK', 'DENY' => 'danger',
                        'CHALLENGE', 'CAPTCHA' => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->badge(),
                Tables\Columns\TextColumn::make('path')
                    ->label('Path')
                    ->limit(50)
                    ->tooltip(fn (array $record): string => (string) ($record['path'] ?? '/')),
                Tables\Columns\TextColumn::make('status_code')
                    ->label('Status'),
                Tables\Columns\TextColumn::make('method')
                    ->label('Method')
                    ->badge(),
            ])
            ->emptyStateHeading($this->emptyHeading())
            ->emptyStateDescription($this->emptyDescription());
    }

    protected function records(int|string $page, int|string $recordsPerPage): LengthAwarePaginator
    {
        $site = $this->getSelectedSite();
        $records = collect();

        if ($site) {
            $access = app(PluginSiteService::class)->billingAccessSummaryForSite($site);

            if ($access['pro_enabled']) {
                $records = collect(app(PluginSiteService::class)->recentFirewallEventsForSite($site, 25));
            }
        }

        $perPage = is_numeric($recordsPerPage) ? (int) $recordsPerPage : 10;
        $currentPage = is_numeric($page) ? (int) $page : 1;
        $total = $records->count();
        $items = $records->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    protected function emptyHeading(): string
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return 'Select a site to load firewall activity';
        }

        $access = app(PluginSiteService::class)->billingAccessSummaryForSite($site);

        return $access['pro_enabled'] ? 'No recent firewall events yet' : 'Live firewall activity requires a paid site plan';
    }

    protected function emptyDescription(): string
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return 'Choose a site from the switcher to review WordPress security telemetry.';
        }

        $access = app(PluginSiteService::class)->billingAccessSummaryForSite($site);

        return $access['pro_enabled']
            ? 'Traffic decisions from the FirePhage edge will appear here after requests are processed.'
            : (string) $access['message'];
    }
}
