<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use App\Services\Auth\WorkspaceAccountCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! $this->googleOauthConfigured()) {
            return redirect('/register')->withErrors([
                'google' => 'Google sign-in is not available right now.',
            ]);
        }

        return Socialite::driver('google')
            ->redirect();
    }

    public function callback(WorkspaceAccountCreator $creator): RedirectResponse
    {
        if (! $this->googleOauthConfigured()) {
            return redirect('/app/login')->withErrors([
                'google' => 'Google sign-in is not available right now.',
            ]);
        }

        $googleUser = Socialite::driver('google')->user();
        $email = Str::lower(trim((string) $googleUser->getEmail()));

        if ($email === '') {
            return redirect('/app/login')->withErrors([
                'google' => 'Google did not return an email address for this account.',
            ]);
        }

        $user = User::query()
            ->where('google_id', (string) $googleUser->getId())
            ->orWhere('email', $email)
            ->first();

        $wasCreated = false;

        if ($user) {
            $user->forceFill([
                'google_id' => (string) $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar() ?: $user->avatar_url,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        } else {
            $user = $creator->create(
                name: (string) ($googleUser->getName() ?: 'Google User'),
                email: $email,
                password: Str::random(40),
                organizationName: null,
                googleId: (string) $googleUser->getId(),
                avatarUrl: $googleUser->getAvatar(),
                markEmailVerified: true,
            );
            $wasCreated = true;
        }

        if ($wasCreated) {
            $user->notify(new WelcomeUserNotification($user->fresh('currentOrganization')));
        }

        Auth::login($user);
        request()->session()->regenerate();

        return redirect($user->is_super_admin ? '/admin' : '/app');
    }

    protected function googleOauthConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
