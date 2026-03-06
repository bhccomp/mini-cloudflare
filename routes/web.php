<?php

use App\Http\Controllers\App\AcceptOrganizationInvitationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home-variant-1')->name('home');
Route::view('/blue-alternative', 'marketing.home')->name('home.blue');
Route::view('/contact', 'marketing.contact')->name('contact');
Route::view('/logos', 'marketing.logos')->name('logos');
Route::redirect('/login', '/app/login')->name('login');
Route::redirect('/register', '/app/login')->name('register');

Route::middleware('auth')->group(function (): void {
    Route::get('/app/invitations/{token}/accept', AcceptOrganizationInvitationController::class)
        ->name('app.invitations.accept');
});
