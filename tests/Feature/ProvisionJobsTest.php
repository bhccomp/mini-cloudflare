<?php

namespace Tests\Feature;

use App\Jobs\CheckAcmDnsValidationJob;
use App\Jobs\MarkSiteReadyForCutoverJob;
use App\Jobs\ProvisionEdgeDeploymentJob;
use App\Jobs\RequestAcmCertificateJob;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\Edge\EdgeProviderInterface;
use App\Services\Edge\EdgeProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class ProvisionJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_sets_pending_dns_validation_and_saves_required_dns_records(): void
    {
        [$site, $user] = $this->newSiteAndUser();

        $provider = new class implements EdgeProviderInterface
        {
            public function key(): string
            {
                return Site::PROVIDER_AWS;
            }

            public function requiresCertificateValidation(): bool
            {
                return true;
            }

            public function requestCertificate(Site $site): array
            {
                return [
                    'changed' => true,
                    'certificate_arn' => 'arn:aws:acm:us-east-1:000000000000:certificate/demo',
                    'required_dns_records' => [
                        'acm_validation' => [[
                            'type' => 'CNAME',
                            'name' => '_abc.example.com',
                            'value' => '_abc.acm-validations.aws.',
                            'status' => 'pending',
                        ]],
                    ],
                ];
            }

            public function checkCertificateValidation(Site $site): array
            {
                return ['validated' => false];
            }

            public function createDeployment(Site $site): array
            {
                return [];
            }

            public function checkDns(Site $site): array
            {
                return ['validated' => false];
            }

            public function purgeCache(Site $site, array $paths = ['/*']): array
            {
                return ['changed' => false];
            }

            public function setUnderAttackMode(Site $site, bool $enabled): array
            {
                return ['changed' => false, 'enabled' => $enabled];
            }
        };

        (new RequestAcmCertificateJob($site->id, $user->id))->handle($this->managerFor($provider));

        $site->refresh();

        $this->assertSame(Site::STATUS_PENDING_DNS_VALIDATION, $site->status);
        $this->assertNotEmpty(data_get($site->required_dns_records, 'acm_validation'));
    }

    public function test_check_dns_not_validated_stays_pending_dns_validation(): void
    {
        [$site, $user] = $this->newSiteAndUser([
            'status' => Site::STATUS_PENDING_DNS_VALIDATION,
            'required_dns_records' => [
                'acm_validation' => [[
                    'type' => 'CNAME',
                    'name' => '_abc.example.com',
                    'value' => '_abc.acm-validations.aws.',
                    'status' => 'pending',
                ]],
            ],
        ]);

        $provider = new class implements EdgeProviderInterface
        {
            public function key(): string
            {
                return Site::PROVIDER_AWS;
            }

            public function requiresCertificateValidation(): bool
            {
                return true;
            }

            public function requestCertificate(Site $site): array
            {
                return [];
            }

            public function checkCertificateValidation(Site $site): array
            {
                return [
                    'validated' => false,
                    'message' => 'ACM DNS validation pending.',
                    'required_dns_records' => $site->required_dns_records,
                ];
            }

            public function createDeployment(Site $site): array
            {
                return [];
            }

            public function checkDns(Site $site): array
            {
                return ['validated' => false];
            }

            public function purgeCache(Site $site, array $paths = ['/*']): array
            {
                return ['changed' => false];
            }

            public function setUnderAttackMode(Site $site, bool $enabled): array
            {
                return ['changed' => false, 'enabled' => $enabled];
            }
        };

        (new CheckAcmDnsValidationJob($site->id, $user->id))->handle($this->managerFor($provider));

        $site->refresh();

        $this->assertSame(Site::STATUS_PENDING_DNS_VALIDATION, $site->status);
    }

    public function test_check_dns_validated_transitions_deploying_to_ready_for_cutover(): void
    {
        [$site, $user] = $this->newSiteAndUser([
            'status' => Site::STATUS_PENDING_DNS_VALIDATION,
            'acm_certificate_arn' => 'arn:aws:acm:us-east-1:000000000000:certificate/demo',
            'required_dns_records' => [
                'acm_validation' => [[
                    'type' => 'CNAME',
                    'name' => '_abc.example.com',
                    'value' => '_abc.acm-validations.aws.',
                    'status' => 'verified',
                ]],
            ],
        ]);

        Bus::fake();

        $provider = new class implements EdgeProviderInterface
        {
            public function key(): string
            {
                return Site::PROVIDER_AWS;
            }

            public function requiresCertificateValidation(): bool
            {
                return true;
            }

            public function requestCertificate(Site $site): array
            {
                return [];
            }

            public function checkCertificateValidation(Site $site): array
            {
                return [
                    'validated' => true,
                    'required_dns_records' => $site->required_dns_records,
                ];
            }

            public function createDeployment(Site $site): array
            {
                return [
                    'provider_resource_id' => 'E12345',
                    'distribution_id' => 'E12345',
                    'distribution_domain_name' => 'd111111abcdef8.cloudfront.net',
                    'web_acl_arn' => 'arn:aws:wafv2:us-east-1:000000000000:global/webacl/site/123',
                    'required_dns_records' => [
                        'traffic' => [[
                            'type' => 'CNAME',
                            'name' => 'www.example.com',
                            'value' => 'd111111abcdef8.cloudfront.net',
                            'status' => 'pending',
                        ]],
                    ],
                ];
            }

            public function checkDns(Site $site): array
            {
                return ['validated' => false];
            }

            public function purgeCache(Site $site, array $paths = ['/*']): array
            {
                return ['changed' => false];
            }

            public function setUnderAttackMode(Site $site, bool $enabled): array
            {
                return ['changed' => false, 'enabled' => $enabled];
            }
        };

        (new CheckAcmDnsValidationJob($site->id, $user->id))->handle($this->managerFor($provider));

        Bus::assertChained([
            ProvisionEdgeDeploymentJob::class,
            MarkSiteReadyForCutoverJob::class,
        ]);

        (new ProvisionEdgeDeploymentJob($site->id, $user->id))->handle($this->managerFor($provider));
        (new MarkSiteReadyForCutoverJob($site->id, $user->id))->handle();

        $site->refresh();

        $this->assertSame(Site::STATUS_READY_FOR_CUTOVER, $site->status);
        $this->assertNotNull($site->waf_web_acl_arn);
        $this->assertNotNull($site->cloudfront_distribution_id);
        $this->assertNotNull($site->cloudfront_domain_name);
    }

    public function test_check_cutover_not_completed_stays_ready_for_cutover(): void
    {
        [$site, $user] = $this->newSiteAndUser([
            'status' => Site::STATUS_READY_FOR_CUTOVER,
            'cloudfront_domain_name' => 'd111111abcdef8.cloudfront.net',
        ]);

        $provider = new class implements EdgeProviderInterface
        {
            public function key(): string
            {
                return Site::PROVIDER_AWS;
            }

            public function requiresCertificateValidation(): bool
            {
                return true;
            }

            public function requestCertificate(Site $site): array
            {
                return [];
            }

            public function checkCertificateValidation(Site $site): array
            {
                return ['validated' => false];
            }

            public function createDeployment(Site $site): array
            {
                return [];
            }

            public function checkDns(Site $site): array
            {
                return [
                    'validated' => false,
                    'message' => 'Point your domain to edge target and retry.',
                    'required_dns_records' => $site->required_dns_records,
                ];
            }

            public function purgeCache(Site $site, array $paths = ['/*']): array
            {
                return ['changed' => false];
            }

            public function setUnderAttackMode(Site $site, bool $enabled): array
            {
                return ['changed' => false, 'enabled' => $enabled];
            }
        };

        (new CheckAcmDnsValidationJob($site->id, $user->id))->handle($this->managerFor($provider));

        $site->refresh();

        $this->assertSame(Site::STATUS_READY_FOR_CUTOVER, $site->status);
    }

    public function test_check_cutover_completed_transitions_to_active(): void
    {
        [$site, $user] = $this->newSiteAndUser([
            'status' => Site::STATUS_READY_FOR_CUTOVER,
            'cloudfront_domain_name' => 'd111111abcdef8.cloudfront.net',
        ]);

        $provider = new class implements EdgeProviderInterface
        {
            public function key(): string
            {
                return Site::PROVIDER_AWS;
            }

            public function requiresCertificateValidation(): bool
            {
                return true;
            }

            public function requestCertificate(Site $site): array
            {
                return [];
            }

            public function checkCertificateValidation(Site $site): array
            {
                return ['validated' => false];
            }

            public function createDeployment(Site $site): array
            {
                return [];
            }

            public function checkDns(Site $site): array
            {
                return [
                    'validated' => true,
                    'message' => 'Traffic DNS is pointed to edge target.',
                    'required_dns_records' => $site->required_dns_records,
                ];
            }

            public function purgeCache(Site $site, array $paths = ['/*']): array
            {
                return ['changed' => false];
            }

            public function setUnderAttackMode(Site $site, bool $enabled): array
            {
                return ['changed' => false, 'enabled' => $enabled];
            }
        };

        (new CheckAcmDnsValidationJob($site->id, $user->id))->handle($this->managerFor($provider));

        $site->refresh();

        $this->assertSame(Site::STATUS_ACTIVE, $site->status);
    }

    protected function newSiteAndUser(array $siteOverrides = []): array
    {
        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['role' => 'owner']);

        $site = Site::create(array_merge([
            'organization_id' => $org->id,
            'display_name' => 'Main Site',
            'name' => 'Main Site',
            'apex_domain' => 'example.com',
            'provider' => Site::PROVIDER_AWS,
            'www_enabled' => true,
            'origin_type' => 'url',
            'origin_url' => 'https://origin.example.com',
            'status' => Site::STATUS_DRAFT,
        ], $siteOverrides));

        return [$site, $user];
    }

    protected function managerFor(EdgeProviderInterface $provider): EdgeProviderManager
    {
        $manager = Mockery::mock(EdgeProviderManager::class);
        $manager->shouldReceive('forSite')
            ->andReturn($provider);

        return $manager;
    }
}
