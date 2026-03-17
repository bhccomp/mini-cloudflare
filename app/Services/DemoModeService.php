<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;

class DemoModeService
{
    public function enabled(): bool
    {
        return (bool) config('demo.enabled', false);
    }

    public function host(?Request $request = null): string
    {
        return strtolower(trim((string) config('demo.host', 'demo.firephage.com')));
    }

    public function active(?Request $request = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $request ??= request();

        return strtolower((string) $request->getHost()) === $this->host($request);
    }

    public function demoEmail(): string
    {
        return (string) config('demo.account.email');
    }

    public function demoPassword(): string
    {
        return (string) config('demo.account.password');
    }

    public function isDemoUser(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User
            && strtolower((string) $user->email) === strtolower($this->demoEmail());
    }

    public function isDemoSite(?Site $site): bool
    {
        return $site instanceof Site && $site->isDemoSeeded();
    }

    public function isReadOnlyDemoSite(?Site $site): bool
    {
        return $this->active() && $this->isDemoSite($site);
    }

    public function shouldUseDemoData(?Site $site): bool
    {
        return $this->isDemoSite($site);
    }

    public function isDemoOrganization(?Organization $organization): bool
    {
        if (! $organization instanceof Organization) {
            return false;
        }

        return (string) $organization->slug === (string) config('demo.organization.slug');
    }

    public function shouldUseDemoBilling(?Organization $organization): bool
    {
        return $this->active() && $this->isDemoOrganization($organization);
    }

    public function isBlockedDashboardPath(Request $request): bool
    {
        foreach ((array) config('demo.blocked_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
