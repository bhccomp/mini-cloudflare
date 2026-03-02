<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Models\SiteAvailabilityCheck;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AvailabilityRecentChecksTable extends TableWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Checks';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->defaultSort('checked_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('checked_at')
                    ->label('Time')
                    ->since(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'up' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('status_code')
                    ->label('HTTP'),
                Tables\Columns\TextColumn::make('latency_ms')
                    ->label('Latency')
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state} ms" : '-'),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(120)
                    ->wrap(),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No availability checks yet')
            ->emptyStateDescription('Run a check now, or wait for scheduled monitoring.');
    }

    protected function query()
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return SiteAvailabilityCheck::query()->whereRaw('1 = 0');
        }

        return SiteAvailabilityCheck::query()->where('site_id', $site->id);
    }
}
