<?php

namespace App\Filament\App\Widgets\Firewall;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\Firewall\FirewallInsightsPresenter;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FirewallTopCountriesTable extends TableWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Top Countries';

    public function table(Table $table): Table
    {
        return $table
            ->description('Highest traffic by country.')
            ->records(fn (): array => $this->records())
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $this->formatCountryLabel($state))
                    ->extraAttributes(['class' => 'py-1 text-xs']),
                Tables\Columns\TextColumn::make('requests')
                    ->label('Requests')
                    ->numeric()
                    ->extraAttributes(['class' => 'py-1 text-xs']),
            ])
            ->emptyStateHeading('No telemetry yet')
            ->emptyStateDescription('Traffic must flow through protection before country statistics appear.');
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

        return collect((array) data_get($insights, 'top_countries', []))
            ->values()
            ->all();
    }

    protected function formatCountryLabel(string $code): string
    {
        $country = strtoupper(trim($code));

        if ($country === '' || strlen($country) !== 2) {
            return $country;
        }

        $flag = $this->countryFlagEmoji($country);

        return trim($flag.' '.$country);
    }

    protected function countryFlagEmoji(string $country): string
    {
        if (strlen($country) !== 2 || ! function_exists('mb_chr')) {
            return '';
        }

        $first = ord($country[0]) - 65 + 127462;
        $second = ord($country[1]) - 65 + 127462;

        if ($first < 127462 || $second < 127462) {
            return '';
        }

        return mb_chr($first, 'UTF-8').mb_chr($second, 'UTF-8');
    }
}
