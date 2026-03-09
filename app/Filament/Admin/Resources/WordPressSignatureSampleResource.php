<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WordPressSignatureSampleResource\Pages;
use App\Models\WordPressSignatureSample;
use Filament\Actions;
use Filament\Forms;
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
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Select::make('sample_type')
                ->options([
                    'malware' => 'Confirmed malware',
                    'clean' => 'Clean file',
                    'false_positive' => 'False positive',
                ])
                ->required()
                ->default('malware'),
            Forms\Components\TextInput::make('family')->maxLength(255),
            Forms\Components\TextInput::make('language')->disabled()->dehydrated(),
            Forms\Components\TextInput::make('original_filename')->label('Original filename')->maxLength(255),
            Forms\Components\FileUpload::make('file_path')
                ->label('Upload sample file')
                ->disk('local')
                ->directory('wordpress-signature-samples')
                ->preserveFilenames(),
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
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
