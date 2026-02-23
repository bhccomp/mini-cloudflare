<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\Site;
use App\Policies\OrganizationPolicy;
use App\Policies\SitePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Organization::class => OrganizationPolicy::class,
        Site::class => SitePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(static fn ($user) => $user->is_super_admin ? true : null);
    }
}
