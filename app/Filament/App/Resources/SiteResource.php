<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\SiteResource\Pages;
use App\Jobs\InvalidateCacheJob;
use App\Jobs\ProvisionCloudFrontJob;
use App\Jobs\ProvisionWafJob;
use App\Jobs\ToggleUnderAttackModeJob;
use App\Models\Site;
use App\Rules\ApexDomainRule;
use App\Rules\SafeOriginUrlRule;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->whereIn('organization_id', $user?->organizations()->select('organizations.id') ?? []);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Hidden::make('organization_id')
                ->default(fn () => auth()->user()?->current_organization_id ?: auth()->user()?->organizations()->value('organizations.id')),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('apex_domain')->required()->rule(new ApexDomainRule),
            Forms\Components\Select::make('environment')
                ->options(['prod' => 'Production', 'stage' => 'Staging'])
                ->default('prod')
                ->required(),
            Forms\Components\TextInput::make('origin_url')->label('Origin URL')->rule(new SafeOriginUrlRule),
            Forms\Components\TextInput::make('origin_ip')
                ->label('Origin IP')
                ->ip()
                ->rule('not_regex:/^(10\.|127\.|169\.254\.|172\.(1[6-9]|2\d|3[0-1])\.|192\.168\.)/'),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('apex_domain')->searchable(),
                Tables\Columns\TextColumn::make('environment')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('provisioning_status')->badge(),
                Tables\Columns\IconColumn::make('under_attack_mode_enabled')->label('Under Attack')->boolean(),
                Tables\Columns\TextColumn::make('cloudfront_domain_name')->label('Edge Host')->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('provision')
                    ->label('Provision')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'provision');

                        $record->update(['provisioning_status' => 'queued']);
                        ProvisionCloudFrontJob::dispatch($record->id, auth()->id());
                        ProvisionWafJob::dispatch($record->id, auth()->id());

                        Notification::make()->title('Provisioning queued')->success()->send();
                    }),
                Tables\Actions\Action::make('underAttack')
                    ->label(fn (Site $record): string => $record->under_attack_mode_enabled ? 'Disable Under Attack' : 'Enable Under Attack')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'under-attack');

                        ToggleUnderAttackModeJob::dispatch($record->id, ! $record->under_attack_mode_enabled, auth()->id());
                        Notification::make()->title('Under-attack mode update queued')->success()->send();
                    }),
                Tables\Actions\Action::make('purgeCache')
                    ->label('Purge Cache')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        static::throttle($record, 'purge');

                        InvalidateCacheJob::dispatch($record->id, ['/*'], auth()->id());
                        Notification::make()->title('Cache purge queued')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'edit' => Pages\EditSite::route('/{record}/edit'),
        ];
    }

    protected static function throttle(Site $site, string $action): void
    {
        $key = sprintf('site-action:%s:%s:%s', auth()->id(), $site->id, $action);

        if (! RateLimiter::attempt($key, maxAttempts: 3, callback: static fn () => true, decaySeconds: 60)) {
            abort(429, 'Too many sensitive requests. Please retry in a minute.');
        }
    }
}
