<?php

namespace App\Filament\Admin\Widgets;

use App\Models\BlogPost;
use App\Models\ContactSubmission;
use App\Models\EarlyAccessLead;
use App\Models\Site;
use App\Models\User;
use App\Models\WordPressSubscriber;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use daacreators\CreatorsTicketing\Models\Ticket;

class AdminOverviewStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Admin Snapshot';

    protected ?string $description = 'Support, growth, publishing, and platform counts for the current FirePhage admin view.';

    protected function getStats(): array
    {
        $openTickets = Ticket::query()
            ->whereHas('status', fn (Builder $query) => $query->where('is_closing_status', false))
            ->count();

        $unassignedTickets = Ticket::query()
            ->whereNull('assignee_id')
            ->whereHas('status', fn (Builder $query) => $query->where('is_closing_status', false))
            ->count();

        $newContacts = ContactSubmission::query()
            ->where('status', 'new')
            ->count();

        $recentLeads = EarlyAccessLead::query()
            ->where('signed_up_at', '>=', now()->subDays(7))
            ->count();

        $verifiedSubscribers = WordPressSubscriber::query()
            ->whereNotNull('verified_at')
            ->count();

        $subscriberTotal = WordPressSubscriber::query()->count();

        $publishedPosts = BlogPost::query()->published()->count();
        $draftPosts = BlogPost::query()->where('is_published', false)->count();

        $userTotal = User::query()->count();
        $activeSites = Site::query()->where('status', Site::STATUS_ACTIVE)->count();

        return [
            Stat::make('Open Tickets', $openTickets)
                ->description($unassignedTickets.' unassigned')
                ->descriptionIcon('heroicon-m-ticket')
                ->color($openTickets > 0 ? 'warning' : 'success'),
            Stat::make('Contact Inbox', $newContacts)
                ->description('New requests awaiting triage')
                ->descriptionIcon('heroicon-m-envelope')
                ->color($newContacts > 0 ? 'info' : 'gray'),
            Stat::make('Early Access Leads', EarlyAccessLead::query()->count())
                ->description($recentLeads.' signed up in the last 7 days')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success'),
            Stat::make('WordPress Subscribers', $subscriberTotal)
                ->description($verifiedSubscribers.' verified for signatures')
                ->descriptionIcon('heroicon-m-command-line')
                ->color('primary'),
            Stat::make('Blog Posts', $publishedPosts)
                ->description($draftPosts.' drafts still unpublished')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('gray'),
            Stat::make('Users', $userTotal)
                ->description($activeSites.' active protected sites')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('gray'),
        ];
    }
}
