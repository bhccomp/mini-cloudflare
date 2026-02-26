<?php

namespace App\Filament\App\Widgets\Firewall;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\Firewall\FirewallInsightsPresenter;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class FirewallRecentEventsTable extends TableWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Firewall Events';

    public function table(Table $table): Table
    {
        return $table
            ->description('Recent decisions from protection telemetry.')
            ->records(fn (?array $filters = null, int|string $page = 1, int|string $recordsPerPage = 10): LengthAwarePaginator => $this->records($filters, $page, $recordsPerPage))
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                Tables\Columns\TextColumn::make('timestamp')
                    ->label('Time')
                    ->formatStateUsing(fn (mixed $state): string => Carbon::parse($state)->diffForHumans()),
                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->badge(),
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->copyable(),
                Tables\Columns\TextColumn::make('method')
                    ->label('Method')
                    ->badge(),
                Tables\Columns\TextColumn::make('path')
                    ->label('Path')
                    ->limit(40)
                    ->tooltip(fn (array $record): string => (string) ($record['path'] ?? '/')),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match (strtoupper($state)) {
                        'BLOCK', 'DENY' => 'danger',
                        'CHALLENGE', 'CAPTCHA' => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('rule')
                    ->label('Rule')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(24),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(34)
                    ->tooltip(fn (array $record): string => (string) ($record['user_agent'] ?? 'n/a')),
            ])
            ->filters([
                SelectFilter::make('country')
                    ->label('Country')
                    ->options(fn (): array => $this->countryOptions()),
                SelectFilter::make('action')
                    ->label('Action')
                    ->options([
                        'ALLOW' => 'Allowed',
                        'BLOCK' => 'Blocked',
                        'CHALLENGE' => 'Challenged',
                        'CAPTCHA' => 'Captcha',
                        'DENY' => 'Denied',
                    ]),
                SelectFilter::make('time_range')
                    ->label('Time Range')
                    ->default('24h')
                    ->options([
                        '24h' => 'Last 24h',
                        '7d' => 'Last 7d',
                    ]),
            ])
            ->recordActions([
                Action::make('viewEvent')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->slideOver()
                    ->modalHeading('Firewall Event')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (array $record) => view('filament.app.widgets.firewall.event-details', ['record' => $record])),
            ])
            ->recordAction('viewEvent')
            ->emptyStateHeading('No telemetry yet')
            ->emptyStateDescription('Once traffic flows through protection, firewall events will be listed here.');
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    protected function records(?array $filters, int|string $page, int|string $recordsPerPage): LengthAwarePaginator
    {
        $events = collect($this->eventRecords());

        $selectedCountry = strtoupper($this->normalizeFilterValue(data_get($filters, 'country.value') ?? data_get($filters, 'country')));
        $selectedAction = strtoupper($this->normalizeFilterValue(data_get($filters, 'action.value') ?? data_get($filters, 'action')));
        $timeRange = $this->normalizeFilterValue(data_get($filters, 'time_range.value') ?? data_get($filters, 'time_range'), '24h');

        if ($selectedCountry !== '') {
            $events = $events->where('country', $selectedCountry);
        }

        if ($selectedAction !== '') {
            $events = $events->filter(fn (array $row): bool => strtoupper((string) ($row['action'] ?? '')) === $selectedAction);
        }

        $from = $timeRange === '7d' ? now()->subDays(7) : now()->subDay();
        $events = $events
            ->filter(fn (array $row): bool => Carbon::parse($row['timestamp'])->greaterThanOrEqualTo($from))
            ->sortByDesc(fn (array $row): int => Carbon::parse($row['timestamp'])->timestamp)
            ->values();

        $perPage = is_numeric($recordsPerPage) ? (int) $recordsPerPage : 10;
        $currentPage = is_numeric($page) ? (int) $page : 1;
        $total = $events->count();
        $items = $events->slice(($currentPage - 1) * $perPage, $perPage)->values();

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

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function eventRecords(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return [];
        }

        $insights = app(FirewallInsightsPresenter::class)->insights($site);

        return collect((array) data_get($insights, 'events', []))
            ->map(function (array $event): array {
                $uri = (string) ($event['uri'] ?? '/');
                $path = parse_url($uri, PHP_URL_PATH) ?: $uri;

                return [
                    'timestamp' => $event['timestamp'] ?? now(),
                    'country' => strtoupper((string) ($event['country'] ?? '??')),
                    'ip' => (string) ($event['ip'] ?? '-'),
                    'method' => strtoupper((string) ($event['method'] ?? 'GET')),
                    'path' => (string) $path,
                    'action' => strtoupper((string) ($event['action'] ?? 'ALLOW')),
                    'rule' => (string) ($event['rule'] ?? 'n/a'),
                    'status_code' => (int) ($event['status_code'] ?? 0),
                    'user_agent' => (string) ($event['user_agent'] ?? data_get($event, 'meta.user_agent', 'n/a')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function countryOptions(): array
    {
        return collect($this->eventRecords())
            ->pluck('country')
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $country): array => [$country => $country])
            ->all();
    }

    protected function normalizeFilterValue(mixed $value, string $default = ''): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $first = reset($value);

            if (is_string($first) || is_numeric($first)) {
                return (string) $first;
            }
        }

        return $default;
    }
}
