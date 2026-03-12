<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WordPressSubscriberResource\Pages;
use App\Models\WordPressSubscriber;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WordPressSubscriberResource extends Resource
{
    protected static ?string $model = WordPressSubscriber::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'WordPress Subscribers';

    protected static ?string $modelLabel = 'WordPress subscriber';

    protected static ?string $pluralModelLabel = 'WordPress subscribers';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_token_issued_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('site_host')->label('Domain')->searchable()->copyable(),
                Tables\Columns\IconColumn::make('marketing_opt_in')->label('Promos')->boolean(),
                Tables\Columns\TextColumn::make('plugin_version')->label('Plugin')->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('last_token_issued_at')->label('Token Issued')->since(),
                Tables\Columns\TextColumn::make('last_seen_at')->label('Last Seen')->since(),
                Tables\Columns\TextColumn::make('created_at')->label('Subscribed')->since(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('marketing_opt_in')->label('Promo Opt-In'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWordPressSubscribers::route('/'),
        ];
    }
}
