<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WordPressMaliciousDomainResource\Pages;
use App\Models\WordPressMaliciousDomain;
use App\Services\WordPress\WordPressMaliciousDomainFeedService;
use App\Services\WordPress\WordPressMaliciousDomainService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class WordPressMaliciousDomainResource extends Resource
{
    protected static ?string $model = WordPressMaliciousDomain::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Signatures';

    protected static ?string $navigationLabel = 'Malicious Domains';

    protected static ?string $modelLabel = 'malicious domain';

    protected static ?string $pluralModelLabel = 'malicious domains';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('domain')
                ->required()
                ->maxLength(255)
                ->helperText('Normalized hostname only, without protocol or path.'),
            Forms\Components\Select::make('status')
                ->options([
                    'active' => 'Active',
                    'disabled' => 'Disabled',
                ])
                ->default('active')
                ->required(),
            Forms\Components\TextInput::make('source')
                ->default('manual')
                ->maxLength(64),
            Forms\Components\Textarea::make('notes')
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\Placeholder::make('last_test_summary')
                ->label('Last test result')
                ->content(function (?WordPressMaliciousDomain $record): string {
                    if (! $record || ! is_array($record->last_test_result)) {
                        return 'No test has been run yet.';
                    }

                    $summary = $record->last_test_result['summary'] ?? [];

                    return sprintf(
                        'Malware hits: %d, clean hits: %d, false-positive hits: %d.',
                        (int) ($summary['malware_hits'] ?? 0),
                        (int) ($summary['clean_hits'] ?? 0),
                        (int) ($summary['false_positive_hits'] ?? 0),
                    );
                })
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('domain')
            ->columns([
                Tables\Columns\TextColumn::make('domain')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('source')->badge(),
                Tables\Columns\TextColumn::make('last_test_result.summary.malware_hits')->label('Malware hits')->numeric()->default(0),
                Tables\Columns\TextColumn::make('last_test_result.summary.clean_hits')->label('Clean hits')->numeric()->default(0)->toggleable(),
                Tables\Columns\TextColumn::make('last_test_result.summary.false_positive_hits')->label('False-positive hits')->numeric()->default(0)->toggleable(),
                Tables\Columns\TextColumn::make('last_tested_at')->since()->label('Last test'),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'disabled' => 'Disabled',
                ]),
                Tables\Filters\SelectFilter::make('source')->options([
                    'external_feed' => 'External feed',
                    'manual' => 'Manual',
                ]),
            ])
            ->actions([
                Actions\Action::make('runTest')
                    ->label('Run Test')
                    ->icon('heroicon-o-play')
                    ->action(function (WordPressMaliciousDomain $record): void {
                        $result = app(WordPressMaliciousDomainService::class)->testDomain($record);

                        Notification::make()
                            ->title('Domain test completed')
                            ->body(sprintf(
                                'Malware hits: %d, clean hits: %d, false-positive hits: %d.',
                                (int) ($result['summary']['malware_hits'] ?? 0),
                                (int) ($result['summary']['clean_hits'] ?? 0),
                                (int) ($result['summary']['false_positive_hits'] ?? 0),
                            ))
                            ->persistent()
                            ->success()
                            ->send();
                    }),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkAction::make('runTestSelected')
                    ->label('Run Test Selected')
                    ->icon('heroicon-o-play')
                    ->action(function ($records): void {
                        $result = app(WordPressMaliciousDomainService::class)->testSelectedDomains($records);

                        Notification::make()
                            ->title('Selected domain tests completed')
                            ->body(sprintf(
                                'Tested %d domain(s). %d domain(s) matched at least one malware sample. Total malware hits across results: %d.',
                                (int) ($result['tested'] ?? 0),
                                (int) ($result['matched'] ?? 0),
                                (int) ($result['total_malware_hits'] ?? 0),
                            ))
                            ->persistent()
                            ->success()
                            ->send();
                    }),
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWordPressMaliciousDomains::route('/'),
            'create' => Pages\CreateWordPressMaliciousDomain::route('/create'),
            'edit' => Pages\EditWordPressMaliciousDomain::route('/{record}/edit'),
        ];
    }

    public static function bundledFeedPath(): string
    {
        return base_path(WordPressMaliciousDomainFeedService::RESOURCE_PATH);
    }
}
