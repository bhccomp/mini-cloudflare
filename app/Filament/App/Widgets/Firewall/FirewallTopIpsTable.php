<?php

namespace App\Filament\App\Widgets\Firewall;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\Firewall\FirewallInsightsPresenter;
use Illuminate\Support\Facades\Blade;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FirewallTopIpsTable extends TableWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Top IPs';

    public function table(Table $table): Table
    {
        return $table
            ->description('Most active client IP addresses.')
            ->records(fn (): array => $this->records())
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->formatStateUsing(function (string $state, array $record): string {
                        $country = strtoupper((string) ($record['country'] ?? ''));
                        $flag = $this->countryFlag($country);
                        $label = $this->countryLabel($country);

                        return Blade::render(
                            '<div class="flex items-center gap-2"><span>{{ $flag }}</span><div class="min-w-0"><div class="font-medium text-gray-950">{{ $ip }}</div><div class="text-xs text-gray-500">{{ $label }}</div></div></div>',
                            [
                                'flag' => $flag,
                                'ip' => $state,
                                'label' => $label,
                            ]
                        );
                    })
                    ->html()
                    ->copyable()
                    ->extraAttributes(['class' => 'py-1 text-xs min-w-[220px]']),
                Tables\Columns\TextColumn::make('requests')
                    ->label('Requests')
                    ->numeric()
                    ->extraAttributes(['class' => 'py-1 text-xs']),
                Tables\Columns\TextColumn::make('blocked')
                    ->label('Blocked')
                    ->numeric()
                    ->extraAttributes(['class' => 'py-1 text-xs']),
            ])
            ->emptyStateHeading('No telemetry yet')
            ->emptyStateDescription('Top IPs require detailed request logs. Traffic totals can still appear from edge analytics.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return [];
        }

        $insights = app(FirewallInsightsPresenter::class)->insights($site);

        return collect((array) data_get($insights, 'top_ips', []))
            ->values()
            ->all();
    }

    private function countryFlag(string $country): string
    {
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            return '🌐';
        }

        $first = 0x1F1E6 + (ord($country[0]) - 65);
        $second = 0x1F1E6 + (ord($country[1]) - 65);

        return html_entity_decode('&#' . $first . ';&#' . $second . ';', ENT_NOQUOTES, 'UTF-8');
    }

    private function countryLabel(string $country): string
    {
        if ($country === '' || $country === '??') {
            return 'Unknown region';
        }

        $name = \Locale::getDisplayRegion('-' . $country, app()->getLocale());

        return $name !== '' ? $name . ' (' . $country . ')' : $country;
    }
}
