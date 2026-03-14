<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'organization_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $email = Str::lower(trim((string) $validated['email']));

        $user = DB::transaction(function () use ($validated, $email): User {
            $organization = Organization::create([
                'name' => $validated['organization_name'],
                'slug' => $this->generateUniqueOrganizationSlug($validated['organization_name']),
                'billing_email' => $email,
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'password' => $validated['password'],
                'current_organization_id' => $organization->id,
            ]);

            $organization->users()->attach($user->id, [
                'role' => OrganizationAccessService::ROLE_OWNER,
                'permissions' => json_encode(
                    app(OrganizationAccessService::class)->defaultPermissionsForRole(OrganizationAccessService::ROLE_OWNER),
                    JSON_THROW_ON_ERROR,
                ),
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/app');
    }

    private function generateUniqueOrganizationSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'workspace';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
