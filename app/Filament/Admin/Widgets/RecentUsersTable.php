<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentUsersTable extends TableWidget
{
    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Newest Users';

    public function table(Table $table): Table
    {
        return $table
            ->description('Recently created admin and customer accounts.')
            ->query(User::query()->with('currentOrganization')->latest('created_at')->limit(5))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('email')
                    ->limit(26)
                    ->tooltip(fn (User $record): string => (string) $record->email),
                Tables\Columns\IconColumn::make('is_super_admin')
                    ->label('Admin')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->since(),
            ])
            ->recordUrl(fn (User $record): string => UserResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('viewUsers')
                    ->label('Manage Users')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(UserResource::getUrl()),
            ])
            ->emptyStateHeading('No users yet')
            ->emptyStateDescription('New customer and admin accounts will appear here.');
    }
}
