<?php

namespace App\Http\Controllers\App;

use App\Models\OrganizationInvitation;
use App\Services\Auth\OrganizationInvitationAcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcceptOrganizationInvitationController
{
    public function __invoke(
        string $token,
        Request $request,
        OrganizationInvitationAcceptanceService $acceptanceService,
    ): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('app.invitations.accept.setup', ['token' => $token]);
        }

        $invitation = OrganizationInvitation::query()
            ->with('organization')
            ->where('token', $token)
            ->first();

        if (! $invitation || ! $invitation->isPending()) {
            return redirect('/app')->with('status', 'This invitation is no longer valid.');
        }

        try {
            $acceptanceService->accept($invitation, $user);
        } catch (\RuntimeException $exception) {
            return redirect('/app')->with('status', $exception->getMessage());
        }

        return redirect('/app')
            ->with('status', 'Invitation accepted. You now have access to this organization.');
    }
}
