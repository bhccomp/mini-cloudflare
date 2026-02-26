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
                    ->badge(),
                Tables\Columns\TextColumn::make('requests')
                    ->label('Requests')
                    ->numeric(),
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
}
