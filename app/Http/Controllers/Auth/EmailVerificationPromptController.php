<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (request()->user()?->hasVerifiedEmail()) {
            return redirect('/app');
        }

        return view('auth.verify-email');
    }
}
