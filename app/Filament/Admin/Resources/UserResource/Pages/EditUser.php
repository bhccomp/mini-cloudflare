<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('loginAsUser')
                ->label('Login As User')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('gray')
                ->visible(fn (): bool => $this->record instanceof User && ! $this->record->is_super_admin && $this->record->organizations()->exists())
                ->requiresConfirmation()
                ->url(fn (): string => route('admin.impersonation.start', ['user' => $this->record])),
            Actions\DeleteAction::make(),
        ];
    }
}
