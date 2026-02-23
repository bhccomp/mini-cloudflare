<?php

namespace App\Services\Aws;

use App\Models\Site;
use Aws\CloudFront\CloudFrontClient;
use Aws\WAFV2\WAFV2Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class AwsEdgeService
{
    public function __construct(
        protected ?CloudFrontClient $cloudFront = null,
        protected ?WAFV2Client $waf = null,
    ) {}

    public function provisionCloudFront(Site $site): array
    {
        if ($site->cloudfront_distribution_id) {
            return [
                'changed' => false,
                'distribution_id' => $site->cloudfront_distribution_id,
                'distribution_domain' => $site->cloudfront_domain_name,
                'message' => 'Distribution already exists, skipping.',
            ];
        }

        if ($this->dryRun()) {
            return [
                'changed' => true,
                'distribution_id' => 'E'.Str::upper(Str::random(13)),
                'distribution_domain' => Str::lower(Str::random(12)).'.cloudfront.net',
                'message' => 'Dry-run: CloudFront distribution simulated.',
            ];
        }

        $this->ensureAwsCredentials();

        // Real API call intentionally minimal for MVP scaffolding.
        return [
            'changed' => false,
            'message' => 'CloudFront provisioning API wiring is enabled, but full payload templates are coming soon.',
        ];
    }

    public function provisionWaf(Site $site): array
    {
        if ($site->waf_web_acl_arn) {
            return [
                'changed' => false,
                'web_acl_arn' => $site->waf_web_acl_arn,
                'message' => 'WAF already exists, skipping.',
            ];
        }

        if ($this->dryRun()) {
            return [
                'changed' => true,
                'web_acl_arn' => 'arn:aws:wafv2:'.config('services.aws_edge.region').':000000000000:global/webacl/'.Str::slug($site->name).'/'.Str::uuid(),
                'message' => 'Dry-run: WAF Web ACL simulated.',
            ];
        }

        $this->ensureAwsCredentials();

        return [
            'changed' => false,
            'message' => 'WAF provisioning API wiring is enabled, but managed rule template rollout is coming soon.',
        ];
    }

    public function setUnderAttackMode(Site $site, bool $enabled): array
    {
        if ($this->dryRun()) {
            return [
                'changed' => $site->under_attack_mode_enabled !== $enabled,
                'enabled' => $enabled,
                'message' => 'Dry-run: under-attack mode simulated.',
            ];
        }

        $this->ensureAwsCredentials();

        return [
            'changed' => false,
            'enabled' => $enabled,
            'message' => 'WAF rate-limit override integration is coming soon.',
        ];
    }

    public function invalidateCache(Site $site, array $paths = ['/*']): array
    {
        if (! $site->cloudfront_distribution_id) {
            return [
                'changed' => false,
                'message' => 'Distribution is not provisioned yet.',
            ];
        }

        if ($this->dryRun()) {
            return [
                'changed' => true,
                'invalidation_id' => Str::upper(Str::random(12)),
                'paths' => Arr::wrap($paths),
                'message' => 'Dry-run: invalidation simulated.',
            ];
        }

        $this->ensureAwsCredentials();

        return [
            'changed' => false,
            'paths' => Arr::wrap($paths),
            'message' => 'CloudFront invalidation wiring is enabled, detailed status tracking is coming soon.',
        ];
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
