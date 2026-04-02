<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BlogPostResource\Pages;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'Blog post';

    protected static ?string $pluralModelLabel = 'Blog posts';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('Blog post')
                ->tabs([
                    Tabs\Tab::make('Article')
                        ->schema([
                            Section::make('Article')
                                ->schema([
                                    Forms\Components\Select::make('blog_category_id')
                                        ->label('Category')
                                        ->options(BlogCategory::query()->orderBy('name')->pluck('name', 'id'))
                                        ->searchable(),
                                    Forms\Components\TextInput::make('title')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug((string) $state))),
                                    Forms\Components\TextInput::make('slug')
                                        ->required()
                                        ->unique(ignoreRecord: true),
                                    Forms\Components\Textarea::make('excerpt')
                                        ->rows(3),
                                    Forms\Components\MarkdownEditor::make('content_markdown')
                                        ->label('Article content')
                                        ->required(),
                                    Forms\Components\TextInput::make('cover_image_url')
                                        ->url()
                                        ->label('Cover image URL'),
                                ])->columns(1),
                        ]),
                    Tabs\Tab::make('SEO & Publishing')
                        ->schema([
                            Section::make('SEO')
                                ->schema([
                                    Forms\Components\TextInput::make('seo_title')
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('seo_description')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('canonical_url')
                                        ->url(),
                                    Forms\Components\TextInput::make('og_image_url')
                                        ->url(),
                                ])
                                ->columnSpan(1)
                                ->columns(1),
                            Section::make('Publishing')
                                ->schema([
                                    Forms\Components\Toggle::make('is_featured')->default(false),
                                    Forms\Components\Toggle::make('is_published')->default(false),
                                    Forms\Components\DateTimePicker::make('published_at'),
                                ])
                                ->columnSpan(1)
                                ->columns(1),
                        ])->columns(2),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->badge()->toggleable(),
                Tables\Columns\IconColumn::make('is_featured')->boolean()->label('Featured'),
                Tables\Columns\IconColumn::make('is_published')->boolean()->label('Published'),
                Tables\Columns\TextColumn::make('published_at')->since()->label('Published at'),
                Tables\Columns\TextColumn::make('updated_at')->since()->toggleable(),
            ])
            ->actions([
                Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->url(fn (BlogPost $record): string => route('blog.show', $record))
                    ->openUrlInNewTab(),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }
}
