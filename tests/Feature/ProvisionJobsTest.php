<?php

namespace Tests\Feature;

use App\Jobs\AssociateWebAclToDistributionJob;
use App\Jobs\CheckAcmDnsValidationJob;
use App\Jobs\ProvisionCloudFrontDistributionJob;
use App\Jobs\ProvisionWafWebAclJob;
use App\Jobs\RequestAcmCertificateJob;
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

    public function test_mvp_job_flow_transitions_site_to_active_when_dns_validates(): void
    {
        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['role' => 'owner']);

        $site = Site::create([
            'organization_id' => $org->id,
            'display_name' => 'Main Site',
            'name' => 'Main Site',
            'apex_domain' => 'example.com',
            'www_enabled' => true,
            'origin_url' => 'https://origin.example.com',
            'status' => 'draft',
        ]);

        $aws = new class extends AwsEdgeService
        {
            public function requestAcmCertificate(Site $site): array
            {
                return [
                    'changed' => true,
                    'certificate_arn' => 'arn:aws:acm:us-east-1:000000000000:certificate/demo',
                    'required_dns_records' => [
                        'acm_validation' => [[
                            'purpose' => 'acm_validation',
                            'type' => 'CNAME',
                            'name' => '_abc.example.com',
                            'value' => '_abc.acm-validations.aws.',
                            'status' => 'pending',
                        ]],
                    ],
                ];
            }

            public function checkAcmDnsValidation(Site $site): array
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
                ];
            }

            public function provisionCloudFrontDistribution(Site $site): array
            {
                return [
                    'changed' => true,
                    'distribution_id' => 'E12345',
                    'distribution_domain_name' => 'd111111abcdef8.cloudfront.net',
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
                            'type' => 'CNAME/ALIAS',
                            'name' => 'example.com',
                            'value' => 'd111111abcdef8.cloudfront.net',
                            'status' => 'pending',
                        ]],
                    ],
                ];
            }

            public function provisionWafWebAcl(Site $site, bool $strict = false): array
            {
                return [
                    'changed' => true,
                    'web_acl_arn' => 'arn:aws:wafv2:us-east-1:000000000000:global/webacl/site/123',
                ];
            }

            public function associateWebAclToDistribution(Site $site): array
            {
                return ['changed' => true];
            }

            public function checkTrafficDns(Site $site): array
            {
                return [
                    'validated' => true,
                    'required_dns_records' => $site->required_dns_records,
                ];
            }
        };

        (new RequestAcmCertificateJob($site->id, $user->id))->handle($aws);
        (new CheckAcmDnsValidationJob($site->id, $user->id))->handle($aws);
        (new ProvisionCloudFrontDistributionJob($site->id, $user->id))->handle($aws);
        (new ProvisionWafWebAclJob($site->id, $user->id))->handle($aws);
        (new AssociateWebAclToDistributionJob($site->id, $user->id))->handle($aws);
        (new CheckAcmDnsValidationJob($site->id, $user->id))->handle($aws);

        $site->refresh();

        $this->assertSame('active', $site->status);
        $this->assertNotNull($site->acm_certificate_arn);
        $this->assertNotNull($site->cloudfront_distribution_id);
        $this->assertNotNull($site->waf_web_acl_arn);

        $this->assertGreaterThanOrEqual(1, AuditLog::where('action', 'acm.request')->count());
        $this->assertGreaterThanOrEqual(1, AuditLog::where('action', 'cloudfront.provision')->count());
        $this->assertGreaterThanOrEqual(1, AuditLog::where('action', 'waf.provision')->count());
    }
}
