<?php

namespace App\Services\Edge\Providers;

use App\Models\Site;
use App\Services\Aws\AwsEdgeService;
use App\Services\Edge\EdgeProviderInterface;

class AwsCdnProvider implements EdgeProviderInterface
{
    public function __construct(protected AwsEdgeService $aws) {}

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
        return $this->aws->requestAcmCertificate($site);
    }

    public function checkCertificateValidation(Site $site): array
    {
        return $this->aws->checkAcmDnsValidation($site);
    }

    public function createDeployment(Site $site): array
    {
        $waf = $this->aws->provisionWafWebAcl($site, strict: (bool) $site->under_attack);

        $site->forceFill([
            'waf_web_acl_arn' => $waf['web_acl_arn'] ?? $site->waf_web_acl_arn,
        ])->save();

        $cdn = $this->aws->provisionCloudFrontDistribution($site->fresh());

        $site->forceFill([
            'cloudfront_distribution_id' => $cdn['distribution_id'] ?? $site->cloudfront_distribution_id,
            'cloudfront_domain_name' => $cdn['distribution_domain_name'] ?? $site->cloudfront_domain_name,
            'required_dns_records' => $cdn['required_dns_records'] ?? $site->required_dns_records,
        ])->save();

        $associate = $this->aws->associateWebAclToDistribution($site->fresh());

        return [
            'ok' => true,
            'status' => Site::STATUS_DEPLOYING,
            'provider' => $this->key(),
            'provider_resource_id' => $cdn['distribution_id'] ?? $site->cloudfront_distribution_id,
            'provider_meta' => [
                'distribution_id' => $cdn['distribution_id'] ?? $site->cloudfront_distribution_id,
                'distribution_domain_name' => $cdn['distribution_domain_name'] ?? $site->cloudfront_domain_name,
                'waf_web_acl_arn' => $waf['web_acl_arn'] ?? $site->waf_web_acl_arn,
            ],
            'distribution_id' => $cdn['distribution_id'] ?? $site->cloudfront_distribution_id,
            'distribution_domain_name' => $cdn['distribution_domain_name'] ?? $site->cloudfront_domain_name,
            'web_acl_arn' => $waf['web_acl_arn'] ?? $site->waf_web_acl_arn,
            'dns_records' => (array) data_get($cdn, 'required_dns_records.traffic', []),
            'required_dns_records' => $cdn['required_dns_records'] ?? $site->required_dns_records,
            'notes' => array_values(array_filter([
                $waf['message'] ?? null,
                $cdn['message'] ?? null,
                $associate['message'] ?? null,
            ])),
        ];
    }

    public function checkDns(Site $site): array
    {
        return $this->aws->checkTrafficDns($site);
    }

    public function purgeCache(Site $site, array $paths = ['/*']): array
    {
        return $this->aws->invalidateCloudFrontCache($site, $paths);
    }

    public function setUnderAttackMode(Site $site, bool $enabled): array
    {
        return $this->aws->setUnderAttackMode($site, $enabled);
    }
}
