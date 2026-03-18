<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ContactSubmissionResource\Pages;
use App\Models\ContactSubmission;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ContactSubmissionResource extends Resource
{
    protected static ?string $model = ContactSubmission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'Contact request';

    protected static ?string $pluralModelLabel = 'Contact requests';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->disabled()->dehydrated(false),
            Forms\Components\TextInput::make('email')->disabled()->dehydrated(false),
            Forms\Components\TextInput::make('company_name')->disabled()->dehydrated(false),
            Forms\Components\TextInput::make('website_url')->disabled()->dehydrated(false),
            Forms\Components\TextInput::make('topic')->disabled()->dehydrated(false),
            Forms\Components\Select::make('status')
                ->options([
                    'new' => 'New',
                    'in_review' => 'In review',
                    'resolved' => 'Resolved',
                ])
                ->required(),
            Forms\Components\DateTimePicker::make('submitted_at')->disabled()->dehydrated(false),
            Forms\Components\DateTimePicker::make('responded_at'),
            Forms\Components\Textarea::make('message')->rows(8)->disabled()->dehydrated(false)->columnSpanFull(),
            Forms\Components\TextInput::make('ip_address')->disabled()->dehydrated(false),
            Forms\Components\Textarea::make('user_agent')->disabled()->dehydrated(false)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('topic')->badge(),
                Tables\Columns\TextColumn::make('company_name')->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'new',
                        'warning' => 'in_review',
                        'success' => 'resolved',
                    ]),
                Tables\Columns\TextColumn::make('submitted_at')->since()->label('Submitted'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactSubmissions::route('/'),
            'edit' => Pages\EditContactSubmission::route('/{record}/edit'),
        ];
    }
}
