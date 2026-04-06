<?php

namespace App\Services\Notifications;

use App\Models\Organization;
use Illuminate\Support\Carbon;

class OrganizationNotificationSuppressionService
{
    public function shouldSuppress(?Organization $organization): bool
    {
        if (! $organization) {
            return false;
        }

        $until = (string) data_get($organization->settings, 'admin_notification_suppressed_until', '');

        if ($until === '') {
            return false;
        }

        try {
            return Carbon::parse($until)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }
}
