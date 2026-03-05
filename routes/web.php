<?php

use App\Http\Controllers\App\AcceptOrganizationInvitationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home')->name('home');
Route::view('/home-variant-1', 'marketing.home-variant-1')->name('home.variant1');
Route::view('/home-variant-2', 'marketing.home-variant-2')->name('home.variant2');
Route::view('/home-variant-3', 'marketing.home-variant-3')->name('home.variant3');
Route::view('/contact', 'marketing.contact')->name('contact');
Route::view('/logos', 'marketing.logos')->name('logos');
Route::redirect('/login', '/app/login')->name('login');
Route::redirect('/register', '/app/login')->name('register');

Route::middleware('auth')->group(function (): void {
    Route::get('/app/invitations/{token}/accept', AcceptOrganizationInvitationController::class)
        ->name('app.invitations.accept');
});
