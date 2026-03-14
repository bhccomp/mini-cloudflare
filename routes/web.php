<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\App\AcceptOrganizationInvitationController;
use App\Http\Controllers\WordPress\VerifyFreeTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home-variant-1')->name('home');
Route::view('/blue-alternative', 'marketing.home')->name('home.blue');
Route::view('/contact', 'marketing.contact')->name('contact');
Route::view('/logos', 'marketing.logos')->name('logos');
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
});
