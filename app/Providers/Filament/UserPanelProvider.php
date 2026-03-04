<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\FirewallPage;
use App\Http\Middleware\RedirectUnauthenticatedAppToLogin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class UserPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->favicon(asset('favicon.svg'))
            ->brandLogo(asset('images/logo-shield-phage-wordmark.svg'))
            ->brandLogoHeight('2rem')
            ->brandName('FirePhage')
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\\Filament\\App\\Pages')
            ->pages([
                FirewallPage::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label('General')->collapsed(),
                NavigationGroup::make()->label('Security & Protection')->collapsed(),
                NavigationGroup::make()->label('Performance')->collapsed(),
                NavigationGroup::make()->label('Monitoring')->collapsed(),
                NavigationGroup::make()->label('Account')->collapsed(),
                NavigationGroup::make()->label('Alerts')->collapsed(),
            ])
            ->widgets([
                AccountWidget::class,
            ])
            ->plugin(
                BreezyCore::make()
                    ->myProfile(
                        shouldRegisterUserMenu: true,
                        userMenuLabel: 'My Profile',
                        shouldRegisterNavigation: true,
                        navigationGroup: 'Account',
                        hasAvatars: true,
                    )
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): \Illuminate\Contracts\View\View => view('filament.app.components.topbar-site-switcher'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): \Illuminate\Contracts\View\View => view('filament.app.components.topbar-global-search'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): \Illuminate\Contracts\View\View => view('filament.app.components.panel-assets'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                RedirectUnauthenticatedAppToLogin::class,
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
