<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WordPressRepoSyncHashResource\Pages;
use App\Models\WordPressRepoSyncHash;
use App\Services\WordPress\WordPressRepoSyncHashService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class WordPressRepoSyncHashResource extends Resource
{
    protected static ?string $model = WordPressRepoSyncHash::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static string|\UnitEnum|null $navigationGroup = 'Signatures';

    protected static ?string $navigationLabel = 'Romainmarcoux Repo Sync Hashes';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'repo sync hash';

    protected static ?string $pluralModelLabel = 'repo sync hashes';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('algorithm')
            ->columns([
                Tables\Columns\TextColumn::make('algorithm')->badge(),
                Tables\Columns\TextColumn::make('hash_value')->searchable()->copyable()->fontFamily('mono'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('source')->badge(),
                Tables\Columns\TextColumn::make('last_synced_at')->since(),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('algorithm')->options([
                    'md5' => 'MD5',
                    'sha1' => 'SHA1',
                    'sha256' => 'SHA256',
                ]),
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'disabled' => 'Disabled',
                ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWordPressRepoSyncHashes::route('/'),
        ];
    }
}
