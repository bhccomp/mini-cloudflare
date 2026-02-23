<?php

namespace Tests\Feature;

use App\Jobs\ProvisionCloudFrontJob;
use App\Jobs\ProvisionWafJob;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_jobs_are_retry_safe_in_dry_run_mode(): void
    {
        config()->set('services.aws_edge.dry_run', true);

        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['role' => 'owner']);

        $site = Site::create([
            'organization_id' => $org->id,
            'name' => 'Main',
            'apex_domain' => 'example.com',
            'environment' => 'prod',
            'status' => 'active',
        ]);

        (new ProvisionCloudFrontJob($site->id, $user->id))->handle(app(\App\Services\Aws\AwsEdgeService::class));
        (new ProvisionCloudFrontJob($site->id, $user->id))->handle(app(\App\Services\Aws\AwsEdgeService::class));

        (new ProvisionWafJob($site->id, $user->id))->handle(app(\App\Services\Aws\AwsEdgeService::class));
        (new ProvisionWafJob($site->id, $user->id))->handle(app(\App\Services\Aws\AwsEdgeService::class));

        $site->refresh();

        $this->assertNotNull($site->cloudfront_distribution_id);
        $this->assertNotNull($site->waf_web_acl_arn);
        $this->assertSame('ready', $site->provisioning_status);

        $this->assertGreaterThanOrEqual(2, AuditLog::query()->where('action', 'cloudfront.provision')->count());
        $this->assertGreaterThanOrEqual(2, AuditLog::query()->where('action', 'waf.provision')->count());
    }
}
