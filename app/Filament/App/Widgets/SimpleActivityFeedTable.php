<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Services\ActivityFeedService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class SimpleActivityFeedTable extends TableWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Activity';

    public function table(Table $table): Table
    {
        return $table
            ->description('Plain-language summary of what protection has been doing lately.')
            ->records(fn (): array => $this->records())
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('message')
                    ->label('Activity')
                    ->wrap(),
                Tables\Columns\TextColumn::make('at')
                    ->label('When')
                    ->since()
                    ->placeholder('Just now'),
            ])
            ->defaultSort('at', 'desc')
            ->emptyStateHeading('No activity available yet')
            ->emptyStateDescription('As traffic flows, this feed will summarize what happened.');
    }

    /**
     * @return array<int, array{message: string, at: \Illuminate\Support\Carbon|null}>
     */
    protected function records(): array
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return [];
        }

        return app(ActivityFeedService::class)->forSite($site, 6);
    }
}
