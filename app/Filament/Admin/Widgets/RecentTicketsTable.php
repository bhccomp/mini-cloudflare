<?php

namespace App\Filament\Admin\Widgets;

use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource;
use daacreators\CreatorsTicketing\Models\Ticket;

class RecentTicketsTable extends TableWidget
{
    protected int|string|array $columnSpan = 2;

    protected static ?string $heading = 'Recent Tickets';

    public function table(Table $table): Table
    {
        return $table
            ->description('Newest support requests and their current ownership.')
            ->query(
                Ticket::query()
                    ->with(['status', 'requester', 'department'])
                    ->latest('last_activity_at')
                    ->limit(7)
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('ticket_uid')
                    ->label('Ticket')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Subject')
                    ->limit(40)
                    ->tooltip(fn (Ticket $record): string => (string) $record->title),
                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Requester')
                    ->placeholder('Guest'),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Ticket $record): string => (string) ($record->status?->color ?? 'gray')),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Active')
                    ->since(),
            ])
            ->recordUrl(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('openTickets')
                    ->label('Open Tickets')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(TicketResource::getUrl()),
            ])
            ->emptyStateHeading('No tickets yet')
            ->emptyStateDescription('New support requests will show up here as soon as customers open them.');
    }
}
