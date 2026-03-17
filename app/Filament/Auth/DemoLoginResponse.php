<?php

namespace App\Filament\Auth;

use App\Filament\App\Pages\SiteStatusHubPage;
use App\Models\Site;
use App\Services\DemoModeService;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class DemoLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $demoMode = app(DemoModeService::class);
        $user = auth()->user();

        if ($demoMode->active($request) && $demoMode->isDemoUser($user)) {
            $siteId = (int) ($user?->selected_site_id ?? 0);

            if ($siteId < 1) {
                $siteId = (int) Site::query()
                    ->where('organization_id', $user?->current_organization_id)
                    ->where('provider_meta->demo_seeded', true)
                    ->value('id');
            }

            if ($siteId > 0) {
                return redirect()->to(SiteStatusHubPage::getUrl(['site_id' => $siteId]));
            }
        }

        return redirect()->intended(Filament::getUrl());
    }
}
