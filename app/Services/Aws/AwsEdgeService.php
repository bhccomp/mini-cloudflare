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
            $token = Str::lower(Str::random(8));
            $dnsRecords = [
                [
                    'purpose' => 'acm_validation',
                    'type' => 'CNAME',
                    'name' => "_{$token}.{$site->apex_domain}",
                    'value' => "_{$token}.acm-validations.aws.",
                    'status' => 'pending',
                ],
            ];

            if ($site->www_domain) {
                $token2 = Str::lower(Str::random(8));
                $dnsRecords[] = [
                    'purpose' => 'acm_validation',
                    'type' => 'CNAME',
                    'name' => "_{$token2}.{$site->www_domain}",
                    'value' => "_{$token2}.acm-validations.aws.",
                    'status' => 'pending',
                ];
            }

            return [
                'changed' => true,
                'certificate_arn' => 'arn:aws:acm:us-east-1:000000000000:certificate/'.Str::uuid(),
                'required_dns_records' => [
                    'acm_validation' => $dnsRecords,
                ],
                'message' => 'Dry-run: ACM certificate requested.',
            ];
        }

        $client = $this->acmClient();
        $domains = array_values(array_filter([$site->apex_domain, $site->www_domain]));

        $request = [
            'DomainName' => $site->apex_domain,
            'ValidationMethod' => 'DNS',
            'Options' => [
                'CertificateTransparencyLoggingPreference' => 'ENABLED',
            ],
        ];

        if (count($domains) > 1) {
            $request['SubjectAlternativeNames'] = [$site->www_domain];
        }

        $result = $client->requestCertificate($request);
        $certificateArn = (string) $result->get('CertificateArn');

        $describe = $client->describeCertificate([
            'CertificateArn' => $certificateArn,
        ]);

        $records = [];
        foreach (($describe->get('Certificate')['DomainValidationOptions'] ?? []) as $validationOption) {
            $record = $validationOption['ResourceRecord'] ?? null;
            if (! $record) {
                continue;
            }

            $records[] = [
                'purpose' => 'acm_validation',
                'type' => $record['Type'],
                'name' => $record['Name'],
                'value' => $record['Value'],
                'status' => 'pending',
            ];
        }

        return [
            'changed' => true,
            'certificate_arn' => $certificateArn,
            'required_dns_records' => [
                'acm_validation' => $records,
            ],
            'message' => 'ACM certificate requested.',
        ];
    }

    public function checkDnsValidation(Site $site): array
    {
        $records = Arr::get($site->required_dns_records, 'acm_validation', []);

        if ($records === []) {
            return [
                'validated' => false,
                'required_dns_records' => $site->required_dns_records,
                'message' => 'No ACM validation records found.',
            ];
        }

        $validatedCount = 0;
        $updated = [];

        foreach ($records as $record) {
            $name = rtrim((string) Arr::get($record, 'name', ''), '.');
            $expected = rtrim((string) Arr::get($record, 'value', ''), '.');
            $actualValues = $this->lookupCname($name);

            $isValid = collect($actualValues)
                ->map(fn (string $value) => rtrim(strtolower($value), '.'))
                ->contains(strtolower($expected));

            if ($isValid) {
                $validatedCount++;
            }

            $updated[] = [
                ...$record,
                'status' => $isValid ? 'verified' : 'pending',
            ];
        }

        $requiredDnsRecords = $site->required_dns_records ?? [];
        $requiredDnsRecords['acm_validation'] = $updated;

        return [
            'validated' => $validatedCount === count($updated),
            'required_dns_records' => $requiredDnsRecords,
            'message' => $validatedCount === count($updated)
                ? 'ACM validation records are published.'
                : 'DNS validation records are not fully propagated yet.',
        ];
    }

    public function provisionEdge(Site $site): array
    {
        if ($site->cloudfront_distribution_id && $site->waf_web_acl_arn && $site->status === 'active') {
            return [
                'changed' => false,
                'distribution_id' => $site->cloudfront_distribution_id,
                'distribution_domain_name' => $site->cloudfront_domain_name,
                'waf_web_acl_arn' => $site->waf_web_acl_arn,
                'required_dns_records' => $site->required_dns_records,
                'message' => 'Edge resources already active.',
            ];
        }

        if ($this->dryRun()) {
            $distributionDomain = Str::lower(Str::random(12)).'.cloudfront.net';
            $requiredDnsRecords = $site->required_dns_records ?? [];
            $requiredDnsRecords['traffic'] = [
                [
                    'purpose' => 'traffic',
                    'type' => 'CNAME',
                    'name' => $site->apex_domain,
                    'value' => $distributionDomain,
                    'status' => 'pending',
                ],
            ];

            if ($site->www_domain) {
                $requiredDnsRecords['traffic'][] = [
                    'purpose' => 'traffic',
                    'type' => 'CNAME',
                    'name' => $site->www_domain,
                    'value' => $distributionDomain,
                    'status' => 'pending',
                ];
            }

            return [
                'changed' => true,
                'distribution_id' => 'E'.Str::upper(Str::random(13)),
                'distribution_domain_name' => $distributionDomain,
                'waf_web_acl_arn' => 'arn:aws:wafv2:us-east-1:000000000000:global/webacl/firephage-site-'.$site->id.'/'.Str::uuid(),
                'required_dns_records' => $requiredDnsRecords,
                'message' => 'Dry-run: CloudFront + WAF provisioned.',
            ];
        }

        $this->ensureAwsCredentials();

        $wafArn = $site->waf_web_acl_arn ?: $this->createWebAcl($site, strictMode: false)['web_acl_arn'];
        $distribution = $site->cloudfront_distribution_id
            ? [
                'distribution_id' => $site->cloudfront_distribution_id,
                'distribution_domain_name' => $site->cloudfront_domain_name,
            ]
            : $this->createCloudFrontDistribution($site, $wafArn);

        $requiredDnsRecords = $site->required_dns_records ?? [];
        $requiredDnsRecords['traffic'] = [
            [
                'purpose' => 'traffic',
                'type' => 'CNAME',
                'name' => $site->apex_domain,
                'value' => $distribution['distribution_domain_name'],
                'status' => 'pending',
            ],
        ];

        if ($site->www_domain) {
            $requiredDnsRecords['traffic'][] = [
                'purpose' => 'traffic',
                'type' => 'CNAME',
                'name' => $site->www_domain,
                'value' => $distribution['distribution_domain_name'],
                'status' => 'pending',
            ];
        }

        return [
            'changed' => true,
            'distribution_id' => $distribution['distribution_id'],
            'distribution_domain_name' => $distribution['distribution_domain_name'],
            'waf_web_acl_arn' => $wafArn,
            'required_dns_records' => $requiredDnsRecords,
            'message' => 'Edge resources provisioned.',
        ];
    }

    public function setUnderAttackMode(Site $site, bool $enabled): array
    {
        if (! $site->waf_web_acl_arn) {
            return [
                'changed' => false,
                'enabled' => $enabled,
                'message' => 'WAF is not provisioned yet.',
            ];
        }

        if ($this->dryRun()) {
            return [
                'changed' => $site->under_attack_mode_enabled !== $enabled,
                'enabled' => $enabled,
                'message' => 'Dry-run: under-attack mode toggled.',
            ];
        }

        $this->updateWebAclRateRule($site->waf_web_acl_arn, strictMode: $enabled);

        return [
            'changed' => true,
            'enabled' => $enabled,
            'message' => 'Under-attack mode updated.',
        ];
    }

    public function invalidateCache(Site $site, array $paths = ['/*']): array
    {
        if (! $site->cloudfront_distribution_id) {
            return [
                'changed' => false,
                'message' => 'CloudFront distribution is not provisioned yet.',
            ];
        }

        if ($this->dryRun()) {
            return [
                'changed' => true,
                'invalidation_id' => Str::upper(Str::random(12)),
                'paths' => Arr::wrap($paths),
                'message' => 'Dry-run: cache invalidation simulated.',
            ];
        }

        $result = $this->cloudFrontClient()->createInvalidation([
            'DistributionId' => $site->cloudfront_distribution_id,
            'InvalidationBatch' => [
                'CallerReference' => (string) Str::uuid(),
                'Paths' => [
                    'Quantity' => count($paths),
                    'Items' => array_values($paths),
                ],
            ],
        ]);

        return [
            'changed' => true,
            'invalidation_id' => (string) Arr::get($result->toArray(), 'Invalidation.Id'),
            'paths' => array_values($paths),
            'message' => 'CloudFront invalidation requested.',
        ];
    }

    protected function createWebAcl(Site $site, bool $strictMode): array
    {
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
            'Rules' => $this->wafRules($strictMode),
        ]);

        return [
            'web_acl_arn' => Arr::get($result->toArray(), 'Summary.ARN'),
        ];
    }

    protected function updateWebAclRateRule(string $webAclArn, bool $strictMode): void
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
            'DefaultAction' => Arr::get($existing, 'WebACL.DefaultAction', ['Allow' => new \stdClass]),
            'VisibilityConfig' => Arr::get($existing, 'WebACL.VisibilityConfig'),
            'LockToken' => Arr::get($existing, 'LockToken'),
            'Rules' => $this->wafRules($strictMode),
        ]);
    }

    protected function createCloudFrontDistribution(Site $site, string $webAclArn): array
    {
        if (! $site->acm_certificate_arn) {
            throw new RuntimeException('ACM certificate ARN missing; cannot create CloudFront distribution.');
        }

        $aliases = array_values(array_filter([$site->apex_domain, $site->www_domain]));
        $originDomain = $site->origin_type === 'ip'
            ? (string) $site->origin_host
            : (string) parse_url((string) $site->origin_url, PHP_URL_HOST);

        if (! $originDomain) {
            throw new RuntimeException('Origin host is missing.');
        }

        $distributionConfig = [
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
                    'DomainName' => $originDomain,
                    'CustomOriginConfig' => [
                        'HTTPPort' => 80,
                        'HTTPSPort' => 443,
                        'OriginProtocolPolicy' => 'https-only',
                        'OriginSslProtocols' => [
                            'Quantity' => 1,
                            'Items' => ['TLSv1.2'],
                        ],
                    ],
                ]],
            ],
            'DefaultCacheBehavior' => [
                'TargetOriginId' => 'origin-'.$site->id,
                'ViewerProtocolPolicy' => 'redirect-to-https',
                'Compress' => true,
                'AllowedMethods' => [
                    'Quantity' => 7,
                    'Items' => ['GET', 'HEAD', 'OPTIONS', 'PUT', 'POST', 'PATCH', 'DELETE'],
                    'CachedMethods' => [
                        'Quantity' => 2,
                        'Items' => ['GET', 'HEAD'],
                    ],
                ],
                'ForwardedValues' => [
                    'QueryString' => true,
                    'Cookies' => ['Forward' => 'all'],
                ],
                'MinTTL' => 0,
                'DefaultTTL' => 60,
                'MaxTTL' => 300,
            ],
            'PriceClass' => 'PriceClass_100',
            'ViewerCertificate' => [
                'ACMCertificateArn' => $site->acm_certificate_arn,
                'SSLSupportMethod' => 'sni-only',
                'MinimumProtocolVersion' => 'TLSv1.2_2021',
            ],
            'WebACLId' => $webAclArn,
        ];

        $result = $this->cloudFrontClient()->createDistribution([
            'DistributionConfig' => $distributionConfig,
        ]);

        return [
            'distribution_id' => Arr::get($result->toArray(), 'Distribution.Id'),
            'distribution_domain_name' => Arr::get($result->toArray(), 'Distribution.DomainName'),
        ];
    }

    protected function wafRules(bool $strictMode): array
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
                        'Limit' => $strictMode ? 500 : 2000,
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

        $fromDig = collect(explode("\n", trim($process->getOutput())))
            ->filter()
            ->values()
            ->all();

        if ($fromDig !== []) {
            return $fromDig;
        }

        return collect(dns_get_record($name, DNS_CNAME) ?: [])
            ->pluck('target')
            ->filter()
            ->values()
            ->all();
    }

    protected function webAclIdFromArn(string $arn): string
    {
        $parts = explode('/', $arn);

        return (string) end($parts);
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

    protected function ensureAwsCredentials(): void
    {
        if (! config('services.aws_edge.access_key_id') || ! config('services.aws_edge.secret_access_key')) {
            throw new RuntimeException('AWS_EDGE credentials are missing. Set AWS_EDGE_ACCESS_KEY_ID and AWS_EDGE_SECRET_ACCESS_KEY.');
        }
    }

    protected function dryRun(): bool
    {
        return (bool) config('services.aws_edge.dry_run', true);
    }
}
