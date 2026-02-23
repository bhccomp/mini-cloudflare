<?php

namespace Tests\Feature;

use App\Jobs\CheckSiteDnsAndFinalizeProvisioningJob;
use App\Jobs\StartSiteProvisioningJob;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\Aws\AwsEdgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioning_flow_transitions_draft_to_active_with_audit_entries(): void
    {
        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['role' => 'owner']);

        $site = Site::create([
            'organization_id' => $org->id,
            'name' => 'Main',
            'display_name' => 'Main',
            'apex_domain' => 'example.com',
            'origin_type' => 'url',
            'origin_url' => 'https://origin.example.com',
            'status' => 'draft',
        ]);

        $fakeAws = new class extends AwsEdgeService
        {
            public function requestAcmCertificate(Site $site): array
            {
                return [
                    'changed' => true,
                    'certificate_arn' => 'arn:aws:acm:us-east-1:000000000000:certificate/test-cert',
                    'required_dns_records' => [
                        'acm_validation' => [[
                            'purpose' => 'acm_validation',
                            'type' => 'CNAME',
                            'name' => '_abc.example.com',
                            'value' => '_abc.acm-validations.aws.',
                            'status' => 'pending',
                        ]],
                    ],
                    'message' => 'Certificate requested',
                ];
            }

            public function checkDnsValidation(Site $site): array
            {
                return [
                    'validated' => true,
                    'required_dns_records' => [
                        'acm_validation' => [[
                            'purpose' => 'acm_validation',
                            'type' => 'CNAME',
                            'name' => '_abc.example.com',
                            'value' => '_abc.acm-validations.aws.',
                            'status' => 'verified',
                        ]],
                    ],
                    'message' => 'DNS validated',
                ];
            }

            public function provisionEdge(Site $site): array
            {
                return [
                    'changed' => true,
                    'distribution_id' => 'E123FAKE',
                    'distribution_domain_name' => 'd111111abcdef8.cloudfront.net',
                    'waf_web_acl_arn' => 'arn:aws:wafv2:us-east-1:000000000000:global/webacl/fp-site/abc',
                    'required_dns_records' => [
                        'acm_validation' => [[
                            'purpose' => 'acm_validation',
                            'type' => 'CNAME',
                            'name' => '_abc.example.com',
                            'value' => '_abc.acm-validations.aws.',
                            'status' => 'verified',
                        ]],
                        'traffic' => [[
                            'purpose' => 'traffic',
                            'type' => 'CNAME',
                            'name' => 'example.com',
                            'value' => 'd111111abcdef8.cloudfront.net',
                            'status' => 'pending',
                        ]],
                    ],
                    'message' => 'Provisioned',
                ];
            }
        };

        (new StartSiteProvisioningJob($site->id, $user->id))->handle($fakeAws);
        $site->refresh();

        $this->assertSame('pending_dns', $site->status);
        $this->assertNotNull($site->acm_certificate_arn);

        (new CheckSiteDnsAndFinalizeProvisioningJob($site->id, $user->id))->handle($fakeAws);
        $site->refresh();

        $this->assertSame('active', $site->status);
        $this->assertNotNull($site->cloudfront_distribution_id);
        $this->assertNotNull($site->waf_web_acl_arn);

        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('action', 'site.provision.start')->count());
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('action', 'site.provision.finalize')->count());
    }
}
