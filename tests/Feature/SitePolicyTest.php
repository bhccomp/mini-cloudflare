<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Policies\SitePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_site_only_in_their_organization(): void
    {
        $policy = new SitePolicy;

        $orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b']);

        $user = User::factory()->create();
        $user->organizations()->attach($orgA->id, ['role' => 'owner']);

        $siteA = Site::create([
            'organization_id' => $orgA->id,
            'name' => 'A',
            'apex_domain' => 'example-a.com',
            'environment' => 'prod',
            'status' => 'active',
        ]);

        $siteB = Site::create([
            'organization_id' => $orgB->id,
            'name' => 'B',
            'apex_domain' => 'example-b.com',
            'environment' => 'prod',
            'status' => 'active',
        ]);

        $this->assertTrue($policy->view($user, $siteA));
        $this->assertTrue($policy->manageSecurityActions($user, $siteA));
        $this->assertFalse($policy->view($user, $siteB));
        $this->assertFalse($policy->manageSecurityActions($user, $siteB));
    }
}
