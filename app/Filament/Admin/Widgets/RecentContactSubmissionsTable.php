<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\ContactSubmissionResource;
use App\Models\ContactSubmission;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentContactSubmissionsTable extends TableWidget
{
    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Contact Requests';

    public function table(Table $table): Table
    {
        return $table
            ->description('Latest public contact submissions that may need a follow-up.')
            ->query(ContactSubmission::query()->latest('submitted_at')->limit(5))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('topic')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'resolved' => 'success',
                        'in_review' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->since(),
            ])
            ->recordUrl(fn (ContactSubmission $record): string => ContactSubmissionResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('viewInbox')
                    ->label('Open Inbox')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(ContactSubmissionResource::getUrl()),
            ])
            ->emptyStateHeading('No contact submissions yet')
            ->emptyStateDescription('New contact requests from the marketing site will show up here.');
    }
}
