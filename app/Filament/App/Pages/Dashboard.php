<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Resources\SiteResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addSite')
                ->label('Add Site')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(SiteResource::getUrl('create')),
        ];
    }
}
