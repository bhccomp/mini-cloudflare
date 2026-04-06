<?php

namespace App\Services\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class AdminImpersonationService
{
    public const IMPERSONATOR_ID_SESSION_KEY = 'admin_impersonation.impersonator_id';

    public const IMPERSONATED_USER_ID_SESSION_KEY = 'admin_impersonation.user_id';

    public function start(User $admin, User $target): void
    {
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();
        Auth::login($target);
        Session::regenerate();
        Session::put(self::IMPERSONATOR_ID_SESSION_KEY, $admin->getKey());
        Session::put(self::IMPERSONATED_USER_ID_SESSION_KEY, $target->getKey());
    }

    public function stop(): ?User
    {
        $impersonator = $this->impersonator();

        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();

        if (! $impersonator) {
            return null;
        }

        Auth::login($impersonator);
        Session::regenerate();

        return $impersonator;
    }

    public function isImpersonating(): bool
    {
        return $this->impersonatorId() !== null && $this->impersonatedUserId() !== null;
    }

    public function impersonatorId(): ?int
    {
        $id = Session::get(self::IMPERSONATOR_ID_SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public function impersonatedUserId(): ?int
    {
        $id = Session::get(self::IMPERSONATED_USER_ID_SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public function impersonator(): ?User
    {
        $id = $this->impersonatorId();

        if (! $id) {
            return null;
        }

        return User::query()->find($id);
    }

    public function touchOrganizationNotificationSuppression(?Organization $organization, int $hours = 12): void
    {
        if (! $organization || ! $this->isImpersonating()) {
            return;
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $settings['admin_notification_suppressed_until'] = now()->addHours($hours)->toIso8601String();
        $settings['admin_notification_suppressed_by'] = $this->impersonatorId();
        $settings['admin_notification_suppressed_user_id'] = $this->impersonatedUserId();
        $settings['admin_notification_suppressed_reason'] = 'Admin impersonation session';

        $organization->forceFill(['settings' => $settings])->save();
    }
}
