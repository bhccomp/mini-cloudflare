<?php

namespace App\Http\Controllers\Auth;

use App\Notifications\EmailVerifiedNotification;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect('/app')->with('status', 'Your email is already verified.');
        }

        $request->fulfill();
        $request->user()?->notify(new EmailVerifiedNotification);

        return redirect('/app')->with('status', 'Your email has been verified. You can now manage sites and protection settings.');
    }
}
