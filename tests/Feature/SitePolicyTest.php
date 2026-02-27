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
            'display_name' => 'A',
            'apex_domain' => 'example-a.com',
            'origin_type' => 'url',
            'origin_url' => 'https://origin-a.example.com',
            'status' => 'draft',
        ]);

        $siteB = Site::create([
            'organization_id' => $orgB->id,
            'name' => 'B',
            'display_name' => 'B',
            'apex_domain' => 'example-b.com',
            'origin_type' => 'url',
            'origin_url' => 'https://origin-b.example.com',
            'status' => 'draft',
        ]);

        $this->assertTrue($policy->view($user, $siteA));
        $this->assertTrue($policy->manageSecurityActions($user, $siteA));
        $this->assertFalse($policy->view($user, $siteB));
        $this->assertFalse($policy->manageSecurityActions($user, $siteB));
    }

    public function test_viewer_can_read_but_cannot_write_site_actions(): void
    {
        $policy = new SitePolicy;

        $org = Organization::create(['name' => 'Org Viewer', 'slug' => 'org-viewer']);
        $user = User::factory()->create(['current_organization_id' => $org->id]);

        $user->organizations()->attach($org->id, [
            'role' => 'viewer',
        ]);

        $site = Site::create([
            'organization_id' => $org->id,
            'name' => 'Viewer Site',
            'display_name' => 'Viewer Site',
            'apex_domain' => 'viewer.example.com',
            'origin_type' => 'url',
            'origin_url' => 'https://origin-viewer.example.com',
            'status' => 'draft',
        ]);

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $site));
        $this->assertFalse($policy->update($user, $site));
        $this->assertFalse($policy->manageSecurityActions($user, $site));
        $this->assertFalse($policy->create($user));
    }
}
