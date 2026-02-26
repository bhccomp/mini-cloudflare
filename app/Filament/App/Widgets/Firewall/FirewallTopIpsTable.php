<?php

namespace App\Filament\App\Widgets\Firewall;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\Firewall\FirewallInsightsPresenter;
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
                    ->copyable(),
                Tables\Columns\TextColumn::make('requests')
                    ->label('Requests')
                    ->numeric(),
                Tables\Columns\TextColumn::make('blocked')
                    ->label('Blocked')
                    ->numeric(),
            ])
            ->emptyStateHeading('No telemetry yet')
            ->emptyStateDescription('Traffic must flow through protection before IP statistics appear.');
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
}
