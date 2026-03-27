<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Widgets\AdminOverviewStats;
use App\Filament\Admin\Widgets\RecentBlogPostsTable;
use App\Filament\Admin\Widgets\RecentContactSubmissionsTable;
use App\Filament\Admin\Widgets\RecentEarlyAccessLeadsTable;
use App\Filament\Admin\Widgets\RecentTicketsTable;
use App\Filament\Admin\Widgets\RecentUsersTable;
use App\Filament\Admin\Widgets\RecentWordPressSubscribersTable;
use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use daacreators\CreatorsTicketing\TicketingPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->favicon(asset('favicon.svg'))
            ->brandLogo(asset('images/logo-shield-phage-wordmark.svg'))
            ->brandLogoHeight('2rem')
            ->brandName('FirePhage')
            ->databaseNotifications(position: DatabaseNotificationsPosition::Topbar)
            ->databaseNotificationsPolling('30s')
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AdminOverviewStats::class,
                RecentTicketsTable::class,
                RecentContactSubmissionsTable::class,
                RecentEarlyAccessLeadsTable::class,
                RecentWordPressSubscribersTable::class,
                RecentBlogPostsTable::class,
                RecentUsersTable::class,
            ])
            ->plugin(TicketingPlugin::make())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
