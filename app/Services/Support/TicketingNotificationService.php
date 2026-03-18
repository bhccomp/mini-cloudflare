<?php

namespace App\Services\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TicketingNotificationService
{
    public function shouldSendCreatedNotifications(object $ticket): bool
    {
        return Cache::add($this->createdKey($ticket), true, now()->addMinutes(2));
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function adminRecipientsForTicket(object $ticket, bool $preferAssignedUser = false): EloquentCollection
    {
        $recipients = collect();

        if ($preferAssignedUser && filled($ticket->assignee_id)) {
            $assignee = User::query()
                ->whereKey($ticket->assignee_id)
                ->whereNotNull('email')
                ->first();

            if ($assignee) {
                $recipients->push($assignee);
            }
        }

        $departmentUsers = $this->departmentAgentIds((int) ($ticket->department_id ?? 0));

        if ($departmentUsers->isNotEmpty()) {
            $recipients = $recipients->merge(
                User::query()
                    ->whereIn('id', $departmentUsers->all())
                    ->whereNotNull('email')
                    ->get()
            );
        }

        if ($recipients->isEmpty()) {
            $recipients = $recipients->merge($this->superAdmins());
        } else {
            $recipients = $recipients->merge(
                $this->superAdmins()->whereNotIn('id', $recipients->pluck('id')->all())
            );
        }

        return new EloquentCollection(
            $recipients
                ->filter(fn ($user): bool => $user instanceof User)
                ->unique('id')
                ->values()
                ->all()
        );
    }

    public function isCustomerReply(object $ticket, object $reply): bool
    {
        if ((bool) ($reply->is_internal_note ?? false)) {
            return false;
        }

        return (int) ($reply->user_id ?? 0) === (int) ($ticket->user_id ?? 0);
    }

    public function isAgentReply(object $ticket, object $reply): bool
    {
        if ((bool) ($reply->is_internal_note ?? false)) {
            return false;
        }

        $replyUserId = (int) ($reply->user_id ?? 0);

        if ($replyUserId === 0 || $replyUserId === (int) ($ticket->user_id ?? 0)) {
            return false;
        }

        $replyUser = User::query()->find($replyUserId);

        if (! $replyUser) {
            return false;
        }

        if ($replyUser->is_super_admin) {
            return true;
        }

        return $this->departmentAgentIds((int) ($ticket->department_id ?? 0))
            ->contains($replyUserId);
    }

    protected function createdKey(object $ticket): string
    {
        return 'support-ticket-created-notified:' . (string) ($ticket->id ?? 'unknown');
    }

    /**
     * @return EloquentCollection<int, User>
     */
    protected function superAdmins(): EloquentCollection
    {
        return User::query()
            ->where('is_super_admin', true)
            ->whereNotNull('email')
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    protected function departmentAgentIds(int $departmentId): Collection
    {
        if ($departmentId <= 0) {
            return collect();
        }

        return DB::table(config('creators-ticketing.table_prefix') . 'department_users')
            ->where('department_id', $departmentId)
            ->where(function ($query): void {
                $query
                    ->where('can_view_all_tickets', true)
                    ->orWhere('can_reply_to_tickets', true)
                    ->orWhere('can_assign_tickets', true)
                    ->orWhere('role', 'manager');
            })
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }
}
