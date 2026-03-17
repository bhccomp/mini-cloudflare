<?php

use App\Http\Controllers\EarlyAccessController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\App\AcceptOrganizationInvitationController;
use App\Http\Controllers\App\SiteCheckoutController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WordPress\VerifyFreeTokenController;
use App\Http\Middleware\RedirectPublicHomeToEarlyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home-variant-1')
    ->middleware(RedirectPublicHomeToEarlyAccess::class)
    ->name('home');
Route::get('/early-access', [EarlyAccessController::class, 'create'])->name('early-access');
Route::post('/early-access', [EarlyAccessController::class, 'store'])->name('early-access.store');
Route::view('/blue-alternative', 'marketing.home')->name('home.blue');
Route::view('/contact', 'marketing.contact')->name('contact');
Route::view('/terms', 'marketing.terms')->name('terms');
Route::view('/privacy', 'marketing.privacy')->name('privacy');
Route::view('/refund-policy', 'marketing.refund-policy')->name('refund-policy');
Route::view('/acceptable-use', 'marketing.acceptable-use')->name('acceptable-use');
Route::view('/logos', 'marketing.logos')->name('logos');
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
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

    Route::get('/app/invitations/{token}/accept', AcceptOrganizationInvitationController::class)
        ->name('app.invitations.accept');
    Route::get('/app/sites/{site}/checkout/{plan}', SiteCheckoutController::class)
        ->name('app.sites.checkout');
});
