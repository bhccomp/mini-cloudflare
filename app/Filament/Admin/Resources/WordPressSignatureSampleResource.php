<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WordPressSignatureSampleResource\Pages;
use App\Models\WordPressMalwareSignature;
use App\Models\WordPressSignatureSample;
use App\Services\WordPress\OpenAiSignatureSuggestionService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class WordPressSignatureSampleResource extends Resource
{
    protected static ?string $model = WordPressSignatureSample::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string|\UnitEnum|null $navigationGroup = 'WordPress';

    protected static ?string $navigationLabel = 'Signature Samples';

    protected static ?string $modelLabel = 'signature sample';

    protected static ?string $pluralModelLabel = 'signature samples';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')
                ->maxLength(255)
                ->helperText('Optional. FirePhage can suggest this after upload or fill it automatically from the file content.'),
            Forms\Components\Select::make('sample_type')
                ->options([
                    'malware' => 'Confirmed malware',
                    'clean' => 'Clean file',
                    'false_positive' => 'False positive',
                ])
                ->default('malware'),
            Forms\Components\TextInput::make('family')->maxLength(255),
            Forms\Components\TextInput::make('language')->disabled()->dehydrated(),
            Forms\Components\TextInput::make('original_filename')
                ->label('Original filename')
                ->maxLength(255)
                ->helperText('Optional. This is inferred automatically from uploaded files.'),
            Forms\Components\FileUpload::make('file_path')
                ->label('Upload sample file')
                ->disk('local')
                ->directory('wordpress-signature-samples')
                ->preserveFilenames()
                ->helperText('Uploaded sample files are stored directly under `wordpress-signature-samples/` and renamed from the sample name when possible.'),
            Forms\Components\Textarea::make('content')
                ->rows(16)
                ->columnSpanFull()
                ->helperText('You can upload a file, paste content, or both. Uploaded file content is used for test execution.'),
            Forms\Components\Textarea::make('notes')->rows(4)->columnSpanFull(),
            Forms\Components\Placeholder::make('signals_preview')
                ->label('Detected signals')
                ->content(fn (?WordPressSignatureSample $record): string => $record && is_array($record->signals) && $record->signals !== []
                    ? implode(', ', $record->signals)
                    : 'No signals extracted yet.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('sample_type')->badge(),
                Tables\Columns\TextColumn::make('family')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('language')->badge(),
                Tables\Columns\TextColumn::make('original_filename')->label('File')->toggleable(),
                Tables\Columns\TextColumn::make('size_bytes')->label('Size')->numeric(),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sample_type')->options([
                    'malware' => 'Confirmed malware',
                    'clean' => 'Clean file',
                    'false_positive' => 'False positive',
                ]),
            ])
            ->actions([
                Actions\Action::make('suggestDetails')
                    ->label('Suggest Details')
                    ->icon('heroicon-o-cpu-chip')
                    ->action(function (WordPressSignatureSample $record): void {
                        try {
                            $details = app(OpenAiSignatureSuggestionService::class)->suggestSampleDetails($record);
                            $record->update($details);

                            Notification::make()
                                ->title('Sample details updated')
                                ->body('AI suggested the sample name, family, type, and notes.')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Unable to suggest details')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Actions\Action::make('suggestSignature')
                    ->label('Suggest Signature')
                    ->icon('heroicon-o-sparkles')
                    ->action(function (WordPressSignatureSample $record): void {
                        try {
                            $result = app(OpenAiSignatureSuggestionService::class)->suggestForSample($record);

                            /** @var WordPressMalwareSignature $signature */
                            $signature = $result['signature'];

                            Notification::make()
                                ->title('Draft signature created')
                                ->body(sprintf(
                                    'Created draft "%s" with %s false-positive risk.',
                                    $signature->name,
                                    ucfirst((string) ($result['risk'] ?? 'unknown'))
                                ))
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Unable to generate suggestion')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWordPressSignatureSamples::route('/'),
            'create' => Pages\CreateWordPressSignatureSample::route('/create'),
            'edit' => Pages\EditWordPressSignatureSample::route('/{record}/edit'),
        ];
    }
}
