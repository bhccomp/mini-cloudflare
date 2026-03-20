<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\WordPressSubscriberResource;
use App\Models\WordPressSubscriber;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentWordPressSubscribersTable extends TableWidget
{
    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'WordPress Subscribers';

    public function table(Table $table): Table
    {
        return $table
            ->description('Latest free-token and signature subscribers from the plugin.')
            ->query(WordPressSubscriber::query()->latest('created_at')->limit(5))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->limit(28)
                    ->tooltip(fn (WordPressSubscriber $record): string => (string) $record->email),
                Tables\Columns\TextColumn::make('site_host')
                    ->label('Domain')
                    ->limit(24)
                    ->tooltip(fn (WordPressSubscriber $record): string => (string) $record->site_host),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\IconColumn::make('marketing_opt_in')
                    ->label('Promo')
                    ->boolean(),
            ])
            ->headerActions([
                Action::make('viewSubscribers')
                    ->label('View Subscribers')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(WordPressSubscriberResource::getUrl()),
            ])
            ->emptyStateHeading('No WordPress subscribers yet')
            ->emptyStateDescription('Verified plugin subscribers will appear here once token registration starts.');
    }
}
