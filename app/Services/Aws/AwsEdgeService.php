<?php

namespace App\Services\Aws;

use App\Models\Site;
use Aws\Acm\AcmClient;
use Aws\CloudFront\CloudFrontClient;
use Aws\WAFV2\WAFV2Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class AwsEdgeService
{
    public function __construct(
        protected ?CloudFrontClient $cloudFront = null,
        protected ?WAFV2Client $waf = null,
        protected ?AcmClient $acm = null,
    ) {}

    public function requestAcmCertificate(Site $site): array
    {
        if ($site->acm_certificate_arn && Arr::get($site->required_dns_records, 'acm_validation')) {
            return [
                'changed' => false,
                'certificate_arn' => $site->acm_certificate_arn,
                'required_dns_records' => $site->required_dns_records,
                'message' => 'Certificate already requested.',
            ];
        }

        if ($this->dryRun()) {
            $records = [[
                'purpose' => 'acm_validation',
                'type' => 'CNAME',
                'name' => '_'.Str::lower(Str::random(8)).'.'.$site->apex_domain,
                'value' => '_'.Str::lower(Str::random(8)).'.acm-validations.aws.',
                'status' => 'pending',
            ]];

            if ($site->www_enabled) {
                $records[] = [
                    'purpose' => 'acm_validation',
                    'type' => 'CNAME',
                    'name' => '_'.Str::lower(Str::random(8)).'.www.'.$site->apex_domain,
                    'value' => '_'.Str::lower(Str::random(8)).'.acm-validations.aws.',
                    'status' => 'pending',
                ];
            }

            return [
                'changed' => true,
                'certificate_arn' => 'arn:aws:acm:us-east-1:000000000000:certificate/'.Str::uuid(),
                'required_dns_records' => ['acm_validation' => $records],
                'message' => 'Dry-run ACM request created.',
            ];
        }

        $client = $this->acmClient();
        $sans = $site->www_enabled ? [$site->www_domain] : [];

        $request = [
            'DomainName' => $site->apex_domain,
            'ValidationMethod' => 'DNS',
        ];

        if ($sans !== []) {
            $request['SubjectAlternativeNames'] = $sans;
        }

        $result = $client->requestCertificate($request);
        $arn = (string) $result->get('CertificateArn');

        $describe = $client->describeCertificate(['CertificateArn' => $arn])->toArray();
        $domainOptions = Arr::get($describe, 'Certificate.DomainValidationOptions', []);

        $records = [];
        foreach ($domainOptions as $option) {
            $record = Arr::get($option, 'ResourceRecord');
            if (! $record) {
                continue;
            }

            $records[] = [
                'purpose' => 'acm_validation',
                'type' => (string) Arr::get($record, 'Type'),
                'name' => (string) Arr::get($record, 'Name'),
                'value' => (string) Arr::get($record, 'Value'),
                'status' => 'pending',
            ];
        }

        return [
            'changed' => true,
            'certificate_arn' => $arn,
            'required_dns_records' => ['acm_validation' => $records],
            'message' => 'ACM certificate requested.',
        ];
    }

    public function checkAcmDnsValidation(Site $site): array
    {
        $records = Arr::get($site->required_dns_records, 'acm_validation', []);
        if ($records === []) {
            return ['validated' => false, 'required_dns_records' => $site->required_dns_records, 'message' => 'No ACM records found.'];
        }

        $updated = [];
        $allValid = true;

        foreach ($records as $record) {
            $name = rtrim((string) Arr::get($record, 'name', ''), '.');
            $expected = rtrim((string) Arr::get($record, 'value', ''), '.');
            $actual = collect($this->lookupCname($name))
                ->map(fn (string $v) => rtrim(strtolower($v), '.'))
                ->all();

            $valid = in_array(strtolower($expected), $actual, true);
            $allValid = $allValid && $valid;

            $updated[] = [
                ...$record,
                'status' => $valid ? 'verified' : 'pending',
            ];
        }

        $dns = $site->required_dns_records ?? [];
        $dns['acm_validation'] = $updated;

        return [
            'validated' => $allValid,
            'required_dns_records' => $dns,
            'message' => $allValid ? 'ACM DNS validated.' : 'ACM DNS validation pending.',
        ];
    }

    public function provisionCloudFrontDistribution(Site $site): array
    {
        if ($site->cloudfront_distribution_id && $site->cloudfront_domain_name) {
            return [
                'changed' => false,
                'distribution_id' => $site->cloudfront_distribution_id,
                'distribution_domain_name' => $site->cloudfront_domain_name,
                'required_dns_records' => $site->required_dns_records,
                'message' => 'CloudFront distribution already exists.',
            ];
        }

        if (! $site->acm_certificate_arn) {
            throw new RuntimeException('ACM certificate ARN missing.');
        }

        if ($this->dryRun()) {
            $domain = Str::lower(Str::random(12)).'.cloudfront.net';
            $dns = $site->required_dns_records ?? [];
            $dns['traffic'] = $this->trafficRecords($site, $domain);

            return [
                'changed' => true,
                'distribution_id' => 'E'.Str::upper(Str::random(13)),
                'distribution_domain_name' => $domain,
                'required_dns_records' => $dns,
                'message' => 'Dry-run CloudFront distribution provisioned.',
            ];
        }

        $originHost = (string) parse_url((string) $site->origin_url, PHP_URL_HOST);
        if (! $originHost) {
            throw new RuntimeException('Origin URL host is required.');
        }

        $aliases = [$site->apex_domain];
        if ($site->www_enabled && $site->www_domain) {
            $aliases[] = $site->www_domain;
        }

        $distribution = $this->cloudFrontClient()->createDistribution([
            'DistributionConfig' => [
                'CallerReference' => (string) Str::uuid(),
                'Comment' => 'FirePhage site '.$site->id,
                'Enabled' => true,
                'Aliases' => [
                    'Quantity' => count($aliases),
                    'Items' => $aliases,
                ],
                'Origins' => [
                    'Quantity' => 1,
                    'Items' => [[
                        'Id' => 'origin-'.$site->id,
                        'DomainName' => $originHost,
                        'CustomOriginConfig' => [
                            'HTTPPort' => 80,
                            'HTTPSPort' => 443,
                            'OriginProtocolPolicy' => 'https-only',
                            'OriginSslProtocols' => ['Quantity' => 1, 'Items' => ['TLSv1.2']],
                        ],
                    ]],
                ],
                'DefaultCacheBehavior' => [
                    'TargetOriginId' => 'origin-'.$site->id,
                    'ViewerProtocolPolicy' => 'redirect-to-https',
                    'AllowedMethods' => [
                        'Quantity' => 2,
                        'Items' => ['GET', 'HEAD'],
                        'CachedMethods' => ['Quantity' => 2, 'Items' => ['GET', 'HEAD']],
                    ],
                    'ForwardedValues' => [
                        'QueryString' => true,
                        'Cookies' => ['Forward' => 'all'],
                    ],
                    'MinTTL' => 0,
                ],
                'ViewerCertificate' => [
                    'ACMCertificateArn' => $site->acm_certificate_arn,
                    'SSLSupportMethod' => 'sni-only',
                    'MinimumProtocolVersion' => 'TLSv1.2_2021',
                ],
                'PriceClass' => 'PriceClass_100',
            ],
        ])->toArray();

        $distributionId = (string) Arr::get($distribution, 'Distribution.Id');
        $domain = (string) Arr::get($distribution, 'Distribution.DomainName');

        $dns = $site->required_dns_records ?? [];
        $dns['traffic'] = $this->trafficRecords($site, $domain);

        return [
            'changed' => true,
            'distribution_id' => $distributionId,
            'distribution_domain_name' => $domain,
            'required_dns_records' => $dns,
            'message' => 'CloudFront distribution provisioned.',
        ];
    }

    public function provisionWafWebAcl(Site $site, bool $strict = false): array
    {
        if ($site->waf_web_acl_arn) {
            return [
                'changed' => false,
                'web_acl_arn' => $site->waf_web_acl_arn,
                'message' => 'WAF WebACL already exists.',
            ];
        }

        if ($this->dryRun()) {
            return [
                'changed' => true,
                'web_acl_arn' => 'arn:aws:wafv2:us-east-1:000000000000:global/webacl/fp-site-'.$site->id.'/'.Str::uuid(),
                'message' => 'Dry-run WAF provisioned.',
            ];
        }

        $name = 'fp-site-'.$site->id;
        $result = $this->wafClient()->createWebACL([
            'Name' => $name,
            'Scope' => 'CLOUDFRONT',
            'DefaultAction' => ['Allow' => new \stdClass],
            'VisibilityConfig' => [
                'SampledRequestsEnabled' => true,
                'CloudWatchMetricsEnabled' => true,
                'MetricName' => $name,
            ],
            'Rules' => $this->wafRules($strict),
        ])->toArray();

        return [
            'changed' => true,
            'web_acl_arn' => (string) Arr::get($result, 'Summary.ARN'),
            'message' => 'WAF WebACL provisioned.',
        ];
    }

    public function associateWebAclToDistribution(Site $site): array
    {
        if (! $site->cloudfront_distribution_id || ! $site->waf_web_acl_arn) {
            return [
                'changed' => false,
                'message' => 'Distribution or WebACL missing.',
            ];
        }

        if ($this->dryRun()) {
            return [
                'changed' => true,
                'message' => 'Dry-run WebACL associated to distribution.',
            ];
        }

        $get = $this->cloudFrontClient()->getDistributionConfig([
            'Id' => $site->cloudfront_distribution_id,
        ])->toArray();

        $config = Arr::get($get, 'DistributionConfig', []);
        $etag = (string) Arr::get($get, 'ETag');

        if ((string) Arr::get($config, 'WebACLId') === $site->waf_web_acl_arn) {
            return [
                'changed' => false,
                'message' => 'WebACL already associated.',
            ];
        }

        $config['WebACLId'] = $site->waf_web_acl_arn;

        $this->cloudFrontClient()->updateDistribution([
            'Id' => $site->cloudfront_distribution_id,
            'IfMatch' => $etag,
            'DistributionConfig' => $config,
        ]);

        return [
            'changed' => true,
            'message' => 'WebACL associated to distribution.',
        ];
    }

    public function checkTrafficDns(Site $site): array
    {
        if (! $site->cloudfront_domain_name) {
            return ['validated' => false, 'message' => 'CloudFront domain is missing.'];
        }

        $targets = [$site->apex_domain];
        if ($site->www_enabled && $site->www_domain) {
            $targets[] = $site->www_domain;
        }

        $all = true;
        $dns = $site->required_dns_records ?? [];
        $traffic = Arr::get($dns, 'traffic', []);

        foreach ($targets as $domain) {
            $valid = $this->domainPointsToCloudFront($domain, $site->cloudfront_domain_name);
            $all = $all && $valid;

            foreach ($traffic as &$record) {
                if (($record['name'] ?? null) === $domain) {
                    $record['status'] = $valid ? 'verified' : 'pending';
                }
            }
            unset($record);
        }

        $dns['traffic'] = $traffic;

        return [
            'validated' => $all,
            'required_dns_records' => $dns,
            'message' => $all ? 'Traffic DNS is pointed to CloudFront.' : 'Point your domain to CloudFront target and retry.',
        ];
    }

    public function setUnderAttackMode(Site $site, bool $enabled): array
    {
        if (! $site->waf_web_acl_arn) {
            return ['changed' => false, 'enabled' => $enabled, 'message' => 'WAF is not provisioned yet.'];
        }

        if ($this->dryRun()) {
            return ['changed' => $site->under_attack !== $enabled, 'enabled' => $enabled, 'message' => 'Dry-run under-attack updated.'];
        }

        $this->updateWafRateRule($site->waf_web_acl_arn, $enabled);

        return ['changed' => true, 'enabled' => $enabled, 'message' => 'Under-attack mode updated.'];
    }

    public function invalidateCloudFrontCache(Site $site, array $paths = ['/*']): array
    {
        if (! $site->cloudfront_distribution_id) {
            return ['changed' => false, 'message' => 'CloudFront distribution missing.'];
        }

        if ($this->dryRun()) {
            return [
                'changed' => true,
                'invalidation_id' => Str::upper(Str::random(12)),
                'paths' => array_values($paths),
                'message' => 'Dry-run invalidation requested.',
            ];
        }

        $result = $this->cloudFrontClient()->createInvalidation([
            'DistributionId' => $site->cloudfront_distribution_id,
            'InvalidationBatch' => [
                'CallerReference' => (string) Str::uuid(),
                'Paths' => ['Quantity' => count($paths), 'Items' => array_values($paths)],
            ],
        ])->toArray();

        return [
            'changed' => true,
            'invalidation_id' => (string) Arr::get($result, 'Invalidation.Id'),
            'paths' => array_values($paths),
            'message' => 'Invalidation requested.',
        ];
    }

    protected function trafficRecords(Site $site, string $cloudFrontDomain): array
    {
        $records = [[
            'purpose' => 'traffic',
            'type' => 'CNAME/ALIAS',
            'name' => $site->apex_domain,
            'value' => $cloudFrontDomain,
            'status' => 'pending',
        ]];

        if ($site->www_enabled && $site->www_domain) {
            $records[] = [
                'purpose' => 'traffic',
                'type' => 'CNAME',
                'name' => $site->www_domain,
                'value' => $cloudFrontDomain,
                'status' => 'pending',
            ];
        }

        return $records;
    }

    protected function domainPointsToCloudFront(string $domain, string $cloudFrontDomain): bool
    {
        $domain = rtrim(strtolower($domain), '.');
        $cloudFrontDomain = rtrim(strtolower($cloudFrontDomain), '.');

        $cname = collect($this->lookupCname($domain))
            ->map(fn (string $v) => rtrim(strtolower($v), '.'))
            ->contains($cloudFrontDomain);

        if ($cname) {
            return true;
        }

        $domainIps = gethostbynamel($domain) ?: [];
        $cfIps = gethostbynamel($cloudFrontDomain) ?: [];

        if ($domainIps === [] || $cfIps === []) {
            return false;
        }

        return count(array_intersect($domainIps, $cfIps)) > 0;
    }

    protected function updateWafRateRule(string $webAclArn, bool $strict): void
    {
        $id = $this->webAclIdFromArn($webAclArn);
        $name = $this->webAclNameFromArn($webAclArn);

        $existing = $this->wafClient()->getWebACL([
            'Scope' => 'CLOUDFRONT',
            'Id' => $id,
            'Name' => $name,
        ])->toArray();

        $this->wafClient()->updateWebACL([
            'Scope' => 'CLOUDFRONT',
            'Id' => $id,
            'Name' => $name,
            'DefaultAction' => Arr::get($existing, 'WebACL.DefaultAction'),
            'VisibilityConfig' => Arr::get($existing, 'WebACL.VisibilityConfig'),
            'Rules' => $this->wafRules($strict),
            'LockToken' => Arr::get($existing, 'LockToken'),
        ]);
    }

    protected function wafRules(bool $strict): array
    {
        return [
            [
                'Name' => 'AWSManagedRulesCommonRuleSet',
                'Priority' => 1,
                'Statement' => [
                    'ManagedRuleGroupStatement' => [
                        'VendorName' => 'AWS',
                        'Name' => 'AWSManagedRulesCommonRuleSet',
                    ],
                ],
                'OverrideAction' => ['None' => new \stdClass],
                'VisibilityConfig' => [
                    'SampledRequestsEnabled' => true,
                    'CloudWatchMetricsEnabled' => true,
                    'MetricName' => 'managed-common',
                ],
            ],
            [
                'Name' => 'RateLimit',
                'Priority' => 2,
                'Statement' => [
                    'RateBasedStatement' => [
                        'Limit' => $strict ? 500 : 2000,
                        'AggregateKeyType' => 'IP',
                    ],
                ],
                'Action' => ['Block' => new \stdClass],
                'VisibilityConfig' => [
                    'SampledRequestsEnabled' => true,
                    'CloudWatchMetricsEnabled' => true,
                    'MetricName' => 'rate-limit',
                ],
            ],
        ];
    }

    protected function lookupCname(string $name): array
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            return [];
        }

        $process = new Process(['dig', '+short', 'CNAME', $name]);
        $process->setTimeout(10);
        $process->run();

        return collect(explode("\n", trim($process->getOutput())))->filter()->values()->all();
    }

    protected function webAclIdFromArn(string $arn): string
    {
        return (string) last(explode('/', $arn));
    }

    protected function webAclNameFromArn(string $arn): string
    {
        $parts = explode('/', $arn);

        return (string) ($parts[count($parts) - 2] ?? '');
    }

    protected function cloudFrontClient(): CloudFrontClient
    {
        return $this->cloudFront ??= new CloudFrontClient([
            'version' => 'latest',
            'region' => config('services.aws_edge.region', 'us-east-1'),
            'credentials' => [
                'key' => config('services.aws_edge.access_key_id'),
                'secret' => config('services.aws_edge.secret_access_key'),
            ],
        ]);
    }

    protected function wafClient(): WAFV2Client
    {
        return $this->waf ??= new WAFV2Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => config('services.aws_edge.access_key_id'),
                'secret' => config('services.aws_edge.secret_access_key'),
            ],
        ]);
    }

    protected function acmClient(): AcmClient
    {
        return $this->acm ??= new AcmClient([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => config('services.aws_edge.access_key_id'),
                'secret' => config('services.aws_edge.secret_access_key'),
            ],
        ]);
    }

    protected function dryRun(): bool
    {
        return (bool) config('services.aws_edge.dry_run', true);
    }
}
