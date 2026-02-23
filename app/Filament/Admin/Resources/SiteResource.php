<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SiteResource\Pages;
use App\Jobs\CheckSiteDnsAndFinalizeProvisioningJob;
use App\Jobs\StartSiteProvisioningJob;
use App\Models\Organization;
use App\Models\Site;
use App\Rules\ApexDomainRule;
use App\Rules\SafeOriginUrlRule;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Site')
                ->schema([
                    Forms\Components\Select::make('organization_id')
                        ->required()
                        ->options(Organization::query()->pluck('name', 'id')),
                    Forms\Components\TextInput::make('display_name')->required(),
                    Forms\Components\TextInput::make('apex_domain')->required()->rule(new ApexDomainRule),
                    Forms\Components\TextInput::make('www_domain')->rule(new ApexDomainRule),
                    Forms\Components\Select::make('origin_type')->required()->options(['url' => 'URL', 'ip' => 'Host/IP']),
                    Forms\Components\TextInput::make('origin_url')->rule(new SafeOriginUrlRule),
                    Forms\Components\TextInput::make('origin_host'),
                    Forms\Components\Select::make('status')->options([
                        'draft' => 'Draft',
                        'pending_dns' => 'Pending DNS',
                        'provisioning' => 'Provisioning',
                        'active' => 'Active',
                        'failed' => 'Failed',
                    ])->required(),
                    Forms\Components\Toggle::make('under_attack_mode_enabled'),
                    Forms\Components\Textarea::make('last_error')->columnSpanFull(),
                ])->columns(2),
            Section::make('AWS State')
                ->schema([
                    Forms\Components\TextInput::make('acm_certificate_arn'),
                    Forms\Components\TextInput::make('cloudfront_distribution_id'),
                    Forms\Components\TextInput::make('cloudfront_domain_name'),
                    Forms\Components\TextInput::make('waf_web_acl_arn'),
                    Forms\Components\KeyValue::make('required_dns_records')->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')->label('Organization')->searchable(),
                Tables\Columns\TextColumn::make('display_name')->searchable(),
                Tables\Columns\TextColumn::make('apex_domain')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('last_error')->limit(80)->wrap(),
                Tables\Columns\TextColumn::make('cloudfront_distribution_id')->label('CF ID')->toggleable(),
                Tables\Columns\TextColumn::make('waf_web_acl_arn')->label('WAF')->limit(30)->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->actions([
                Actions\Action::make('retryProvisioning')
                    ->label('Retry Provisioning')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        StartSiteProvisioningJob::dispatch($record->id, auth()->id());
                        Notification::make()->title('Provisioning retry queued')->success()->send();
                    }),
                Actions\Action::make('forceCheckDns')
                    ->label('Check DNS + Finalize')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        CheckSiteDnsAndFinalizeProvisioningJob::dispatch($record->id, auth()->id());
                        Notification::make()->title('DNS finalize queued')->success()->send();
                    }),
                Actions\EditAction::make(),
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
}
