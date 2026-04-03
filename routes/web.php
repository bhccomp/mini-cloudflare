<?php

use App\Http\Controllers\EarlyAccessController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\App\AcceptOrganizationInvitationController;
use App\Http\Controllers\App\AcceptOrganizationInvitationSetupController;
use App\Http\Controllers\App\SiteCheckoutController;
use App\Http\Controllers\App\SiteCheckoutSuccessController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ServicePageController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WordPress\VerifyFreeTokenController;
use App\Http\Middleware\RedirectPublicHomeToEarlyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::domain(config('demo.host'))->group(function (): void {
    Route::get('/', fn () => redirect('/app'));
});

Route::view('/', 'marketing.home-variant-1')->name('home');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/early-access', [EarlyAccessController::class, 'create'])->name('early-access');
Route::post('/early-access', [EarlyAccessController::class, 'store'])->name('early-access.store');
Route::view('/blue-alternative', 'marketing.home')->name('home.blue');
Route::get('/services', [ServicePageController::class, 'index'])->name('services.index');
Route::get('/services/{service}', [ServicePageController::class, 'show'])->name('services.show');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
Route::view('/terms', 'marketing.terms')->name('terms');
Route::view('/privacy', 'marketing.privacy')->name('privacy');
Route::view('/cookies', 'marketing.cookies')->name('cookies');
Route::view('/refund-policy', 'marketing.refund-policy')->name('refund-policy');
Route::view('/acceptable-use', 'marketing.acceptable-use')->name('acceptable-use');
Route::view('/logos', 'marketing.logos')->name('logos');
Route::view('/billing/checkout/complete', 'marketing.billing-checkout-complete')->name('billing.checkout.complete');
Route::view('/billing/checkout/cancelled', 'marketing.billing-checkout-cancelled')->name('billing.checkout.cancelled');
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});
Route::redirect('/login', '/app/login')->name('login');
Route::get('/wordpress/free-token/verify/{token}', VerifyFreeTokenController::class)->name('wordpress.free-token.verify');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', function (Request $request) {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    })->name('logout');

    Route::get('/app/sites/{site}/checkout/{plan}', SiteCheckoutController::class)
        ->name('app.sites.checkout');
    Route::get('/app/sites/{site}/checkout/success', SiteCheckoutSuccessController::class)
        ->name('app.sites.checkout.success');
});

Route::get('/app/invitations/{token}/accept', AcceptOrganizationInvitationController::class)
    ->name('app.invitations.accept');

Route::middleware('guest')->group(function (): void {
    Route::get('/app/invitations/{token}/setup', [AcceptOrganizationInvitationSetupController::class, 'create'])
        ->name('app.invitations.accept.setup');
    Route::post('/app/invitations/{token}/setup', [AcceptOrganizationInvitationSetupController::class, 'store'])
        ->name('app.invitations.accept.setup.store');
});
