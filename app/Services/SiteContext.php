<?php

namespace App\Services;

use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;

class SiteContext
{
    public function getSelectedSiteId(User $user, ?Request $request = null): ?int
    {
        $request ??= request();

        if ($request->has('site_id')) {
            $raw = $request->query('site_id');

            if ($raw === 'all' || $raw === '0' || $raw === 0 || $raw === null || $raw === '') {
                return $this->setSelectedSiteId($user, null);
            }

            $candidate = (int) $raw;

            if ($candidate > 0) {
                return $this->setSelectedSiteId($user, $candidate);
            }
        }

        $sessionSiteId = session('selected_site_id');

        if (is_numeric($sessionSiteId)) {
            $sessionSiteId = (int) $sessionSiteId;

            if ($sessionSiteId > 0 && $this->userOwnsSite($user, $sessionSiteId)) {
                return $sessionSiteId;
            }
        }

        if ($user->selected_site_id && $this->userOwnsSite($user, $user->selected_site_id)) {
            session(['selected_site_id' => $user->selected_site_id]);

            return $user->selected_site_id;
        }

        return $this->setSelectedSiteId($user, null);
    }

    public function setSelectedSiteId(User $user, int|string|null $siteId): ?int
    {
        $normalized = null;

        if (is_numeric($siteId) && (int) $siteId > 0) {
            $candidate = (int) $siteId;

            if ($this->userOwnsSite($user, $candidate)) {
                $normalized = $candidate;
            }
        }

        if ($normalized === null) {
            session()->forget('selected_site_id');
        } else {
            session(['selected_site_id' => $normalized]);
        }

        if ($user->selected_site_id !== $normalized) {
            $user->forceFill(['selected_site_id' => $normalized])->save();
        }

        return $normalized;
    }

    public function getSelectedSite(User $user, ?Request $request = null): ?Site
    {
        $selectedSiteId = $this->getSelectedSiteId($user, $request);

        if (! $selectedSiteId) {
            return null;
        }

        return Site::query()
            ->whereKey($selectedSiteId)
            ->whereIn('organization_id', $user->organizations()->select('organizations.id'))
            ->first();
    }

    protected function userOwnsSite(User $user, int $siteId): bool
    {
        return Site::query()
            ->whereKey($siteId)
            ->whereIn('organization_id', $user->organizations()->select('organizations.id'))
            ->exists();
    }
}
