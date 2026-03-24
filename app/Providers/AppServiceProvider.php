<?php

namespace App\Providers;

use App\Filament\Auth\DemoLoginResponse;
use App\Models\User;
use App\Notifications\TicketAgentReplyCustomerNotification;
use App\Notifications\TicketCreatedAdminNotification;
use App\Notifications\TicketCreatedCustomerNotification;
use App\Notifications\TicketCustomerReplyAdminNotification;
use App\Services\Billing\PlanCatalogService;
use App\Services\DemoModeService;
use App\Services\Edge\EdgeProviderManager;
use App\Services\Support\TicketingNotificationService;
use daacreators\CreatorsTicketing\Events\TicketCreated;
use daacreators\CreatorsTicketing\Events\TicketReplyAdded;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EdgeProviderManager::class);
        $this->app->singleton(PlanCatalogService::class);
        $this->app->singleton(DemoModeService::class);
        $this->app->bind(LoginResponseContract::class, DemoLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(TicketCreated::class, function (TicketCreated $event): void {
            $notifications = app(TicketingNotificationService::class);

            if (! $notifications->shouldSendCreatedNotifications($event->ticket)) {
                return;
            }

            $requester = $event->user;

            if ($requester instanceof User && $requester->is_super_admin) {
                return;
            }

            $ticket = $event->ticket->loadMissing('department', 'status', 'requester');
            $recipients = $notifications->adminRecipientsForTicket($ticket);

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new TicketCreatedAdminNotification(
                $ticket,
                $requester instanceof User ? $requester : null,
            ));

            if ($ticket->requester instanceof User && filled($ticket->requester->email)) {
                $ticket->requester->notify(new TicketCreatedCustomerNotification($ticket));
            }
        });

        Event::listen(TicketReplyAdded::class, function (TicketReplyAdded $event): void {
            $notifications = app(TicketingNotificationService::class);
            $reply = $event->reply->loadMissing('user');
            $ticket = $event->ticket->loadMissing('department', 'status', 'requester');
            $replyUser = $reply->user;

            if ($notifications->isCustomerReply($ticket, $reply)) {
                $recipients = $notifications->adminRecipientsForTicket($ticket, preferAssignedUser: true);

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new TicketCustomerReplyAdminNotification(
                        $ticket,
                        $reply,
                        $replyUser instanceof User ? $replyUser : null,
                    ));
                }

                return;
            }

            if ($notifications->isAgentReply($ticket, $reply)
                && $ticket->requester instanceof User
                && filled($ticket->requester->email)) {
                $ticket->requester->notify(new TicketAgentReplyCustomerNotification($ticket, $reply));
            }
        });

        View::composer([
            'components.marketing.pricing',
            'components.marketing.pricing-variant-1',
            'components.marketing.hero',
            'components.marketing.hero-variant-1',
        ], function ($view): void {
            $catalog = app(PlanCatalogService::class);

            $view->with('marketingPlans', $catalog->marketingPlans());
            $view->with('marketingTrialPlan', $catalog->marketingTrialPlan());
        });
        View::composer(['components.marketing.hero', 'components.marketing.hero-variant-1'], function ($view): void {
            $view->with('demoDashboardUrl', 'https://'.config('demo.host').'/app');
        });
    }
}
