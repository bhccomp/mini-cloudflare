<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\BlogPostResource;
use App\Models\BlogPost;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentBlogPostsTable extends TableWidget
{
    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Blog Publishing';

    public function table(Table $table): Table
    {
        return $table
            ->description('Recent blog posts, including drafts waiting to be published.')
            ->query(BlogPost::query()->with('category')->latest('updated_at')->limit(5))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->limit(34)
                    ->tooltip(fn (BlogPost $record): string => (string) $record->title),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->placeholder('Uncategorized'),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Live')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since(),
            ])
            ->recordUrl(fn (BlogPost $record): string => BlogPostResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('viewPosts')
                    ->label('Manage Blog')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(BlogPostResource::getUrl()),
            ])
            ->emptyStateHeading('No blog posts yet')
            ->emptyStateDescription('Drafts and published articles will appear here.');
    }
}
