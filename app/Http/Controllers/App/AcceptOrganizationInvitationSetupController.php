<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\Auth\OrganizationInvitationAcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AcceptOrganizationInvitationSetupController extends Controller
{
    public function create(string $token, Request $request): View|RedirectResponse
    {
        $invitation = $this->invitation($token);

        if (! $invitation || ! $invitation->isPending()) {
            return redirect('/app/login')->with('status', 'This invitation is no longer valid.');
        }

        if ($request->user()) {
            return redirect()->route('app.invitations.accept', ['token' => $token]);
        }

        $existingUser = User::query()
            ->whereRaw('lower(email) = ?', [strtolower($invitation->email)])
            ->first();

        return view('auth.accept-organization-invitation', [
            'invitation' => $invitation,
            'existingUser' => $existingUser,
        ]);
    }

    public function store(
        string $token,
        Request $request,
        OrganizationInvitationAcceptanceService $acceptanceService,
    ): RedirectResponse {
        $invitation = $this->invitation($token);

        if (! $invitation || ! $invitation->isPending()) {
            return redirect('/app/login')->with('status', 'This invitation is no longer valid.');
        }

        $existingUser = User::query()
            ->whereRaw('lower(email) = ?', [strtolower($invitation->email)])
            ->first();

        if ($existingUser) {
            return redirect('/app/login')->with('status', 'An account for this invitation email already exists. Please sign in first, then open the invitation link again.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $user = User::query()->create([
            'name' => (string) $validated['name'],
            'email' => Str::lower(trim((string) $invitation->email)),
            'password' => Hash::make((string) $validated['password']),
            'email_verified_at' => now(),
            'is_super_admin' => false,
        ]);

        $acceptanceService->accept($invitation, $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/app')
            ->with('status', 'Invitation accepted. Your FirePhage account is ready.');
    }

    private function invitation(string $token): ?OrganizationInvitation
    {
        return OrganizationInvitation::query()
            ->with('organization')
            ->where('token', $token)
            ->first();
    }
}
