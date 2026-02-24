<?php

namespace App\Filament\App\Widgets\Concerns;

use App\Models\Site;
use App\Services\SiteContext;

trait ResolvesSelectedSite
{
    protected function getSelectedSite(): ?Site
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return app(SiteContext::class)->getSelectedSite($user, request());
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return app(SiteContext::class)->getSelectedSite($user, request()) !== null;
    }
}
