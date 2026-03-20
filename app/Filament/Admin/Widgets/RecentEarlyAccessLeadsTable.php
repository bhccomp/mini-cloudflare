<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\EarlyAccessLeadResource;
use App\Models\EarlyAccessLead;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentEarlyAccessLeadsTable extends TableWidget
{
    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Early Access Leads';

    public function table(Table $table): Table
    {
        return $table
            ->description('Recent signups from the early-access form.')
            ->query(EarlyAccessLead::query()->latest('signed_up_at')->limit(5))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Company')
                    ->placeholder('Independent')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('signed_up_at')
                    ->label('Signed Up')
                    ->since(),
            ])
            ->recordUrl(fn (EarlyAccessLead $record): string => EarlyAccessLeadResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('viewLeads')
                    ->label('View Leads')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(EarlyAccessLeadResource::getUrl()),
            ])
            ->emptyStateHeading('No early-access leads yet')
            ->emptyStateDescription('New launch-interest signups will appear here.');
    }
}
