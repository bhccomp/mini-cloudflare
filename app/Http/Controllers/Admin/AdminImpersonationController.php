<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AdminImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminImpersonationController extends Controller
{
    public function store(Request $request, User $user, AdminImpersonationService $impersonation): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin?->is_super_admin, 403);
        abort_if($user->is_super_admin, 403, 'Super admins cannot be impersonated.');
        abort_if(! $user->organizations()->exists(), 422, 'This user does not have access to the app panel.');

        $impersonation->start($admin, $user);
        $impersonation->touchOrganizationNotificationSuppression($user->currentOrganization);

        return redirect('/app')->with('status', 'You are now signed in as '.$user->email.'.');
    }

    public function destroy(Request $request, AdminImpersonationService $impersonation): RedirectResponse
    {
        abort_unless($request->user() && $impersonation->isImpersonating(), 403);

        $impersonator = $impersonation->stop();

        return redirect('/admin')->with('status', 'Returned to admin as '.($impersonator?->email ?? 'admin').'.');
    }
}
