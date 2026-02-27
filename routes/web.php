<?php

use App\Http\Controllers\App\AcceptOrganizationInvitationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');

Route::middleware('auth')->group(function (): void {
    Route::get('/app/invitations/{token}/accept', AcceptOrganizationInvitationController::class)
        ->name('app.invitations.accept');
});
