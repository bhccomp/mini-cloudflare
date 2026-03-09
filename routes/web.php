<?php

use App\Http\Controllers\App\AcceptOrganizationInvitationController;
use App\Http\Controllers\WordPress\VerifyFreeTokenController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home-variant-1')->name('home');
Route::view('/blue-alternative', 'marketing.home')->name('home.blue');
Route::view('/contact', 'marketing.contact')->name('contact');
Route::view('/logos', 'marketing.logos')->name('logos');
Route::redirect('/login', '/app/login')->name('login');
Route::redirect('/register', '/app/login')->name('register');
Route::get('/wordpress/free-token/verify/{token}', VerifyFreeTokenController::class)->name('wordpress.free-token.verify');

Route::middleware('auth')->group(function (): void {
    Route::get('/app/invitations/{token}/accept', AcceptOrganizationInvitationController::class)
        ->name('app.invitations.accept');
});
