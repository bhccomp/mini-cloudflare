<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\WelcomeUserNotification;
use App\Services\Auth\WorkspaceAccountCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, WorkspaceAccountCreator $creator): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'organization_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $email = Str::lower(trim((string) $validated['email']));

        $user = $creator->create(
            name: (string) $validated['name'],
            email: $email,
            password: (string) $validated['password'],
            organizationName: (string) $validated['organization_name'],
        );

        $user->notify(new WelcomeUserNotification($user->fresh('currentOrganization')));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/app');
    }
}
