<?php

namespace App\Console\Commands;

use App\Models\EdgeRequestLog;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\PluginSiteConnection;
use App\Models\Site;
use App\Models\SiteAnalyticsMetric;
use App\Models\SiteAvailabilityCheck;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedDemoDashboardCommand extends Command
{
    protected $signature = 'demo:seed-dashboard';

    protected $description = 'Create or refresh the seeded demo dashboard account and demo site data.';

    public function handle(): int
    {
        DB::transaction(function (): void {
            $organization = Organization::query()->updateOrCreate(
                ['slug' => (string) config('demo.organization.slug')],
                [
                    'name' => (string) config('demo.organization.name'),
                    'billing_email' => (string) config('demo.account.email'),
                    'settings' => ['demo_mode' => true],
                ],
            );

            $user = User::query()->updateOrCreate(
                ['email' => (string) config('demo.account.email')],
                [
                    'name' => (string) config('demo.account.name'),
                    'password' => (string) config('demo.account.password'),
                    'is_super_admin' => false,
                    'current_organization_id' => $organization->id,
                    'ui_mode' => 'simple',
                ],
            );

            $organization->users()->syncWithoutDetaching([
                $user->id => ['role' => 'owner', 'permissions' => null],
            ]);

            $plan = Plan::query()->firstOrCreate(
                ['code' => 'demo-growth'],
                [
                    'name' => 'Growth',
                    'headline' => 'Growth',
                    'description' => 'Demo growth plan',
                    'monthly_price_cents' => 9900,
                    'yearly_price_cents' => 99000,
                    'included_websites' => 3,
                    'included_requests_per_month' => 3000000,
                    'currency' => 'usd',
                    'cta_label' => 'Start Free',
                    'is_active' => true,
                ],
            );

            $site = Site::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'apex_domain' => (string) config('demo.site.apex_domain')],
                [
                    'name' => (string) config('demo.site.display_name'),
                    'display_name' => (string) config('demo.site.display_name'),
                    'provider' => Site::PROVIDER_BUNNY,
                    'status' => Site::STATUS_ACTIVE,
                    'onboarding_status' => Site::ONBOARDING_LIVE,
                    'origin_type' => 'url',
                    'origin_url' => 'https://origin.demo.firephage.test',
                    'origin_host' => 'origin.demo.firephage.test',
                    'origin_ip' => '203.0.113.10',
                    'www_enabled' => true,
                    'www_domain' => 'www.'.(string) config('demo.site.apex_domain'),
                    'cloudfront_distribution_id' => 'demo-edge-distribution',
                    'cloudfront_domain_name' => 'demo.firephage.edge.example',
                    'acm_certificate_arn' => 'demo-certificate',
                    'waf_web_acl_arn' => 'demo-waf-acl',
                    'under_attack' => false,
                    'provider_meta' => [
                        'demo_seeded' => true,
                        'demo_block_ratio' => 18.4,
                        'demo_suspicious_requests_24h' => 11372,
                        'demo_suspicious_ratio' => 24.2,
                        'demo_top_countries' => [
                            ['country' => 'US', 'requests' => 92145],
                            ['country' => 'GB', 'requests' => 40123],
                            ['country' => 'DE', 'requests' => 35511],
                            ['country' => 'NL', 'requests' => 18820],
                        ],
                        'demo_top_ips' => [
                            ['ip' => '203.0.113.25', 'requests' => 1803, 'blocked' => 1450, 'country' => 'US'],
                            ['ip' => '198.51.100.44', 'requests' => 1200, 'blocked' => 1140, 'country' => 'NL'],
                        ],
                        'shield_settings' => [
                            'waf_sensitivity' => 'medium',
                            'ddos_sensitivity' => 'high',
                            'bot_sensitivity' => 'medium',
                            'challenge_window_minutes' => 30,
                            'waf_enabled' => true,
                            'updated_at' => now()->toIso8601String(),
                        ],
                        'demo_rate_limits' => [
                            [
                                'id' => 'demo-rate-limit-login',
                                'Name' => 'Protect login paths',
                                'Description' => 'Challenge bursts against login paths.',
                                'Enabled' => true,
                                'ActionType' => 'challenge',
                                'RuleConfiguration' => [
                                    'windowSeconds' => 10,
                                    'requestLimit' => 30,
                                    'pathPattern' => '/wp-login.php*',
                                ],
                            ],
                            [
                                'id' => 'demo-rate-limit-api',
                                'Name' => 'Slow abusive API bursts',
                                'Description' => 'Block repeated abusive API hits.',
                                'Enabled' => true,
                                'ActionType' => 'block',
                                'RuleConfiguration' => [
                                    'windowSeconds' => 10,
                                    'requestLimit' => 120,
                                    'pathPattern' => '/wp-json/*',
                                ],
                            ],
                        ],
                    ],
                    'required_dns_records' => [
                        'traffic' => [
                            ['host' => (string) config('demo.site.apex_domain'), 'type' => 'ALIAS', 'value' => 'demo.firephage.edge.example'],
                            ['host' => 'www.'.(string) config('demo.site.apex_domain'), 'type' => 'CNAME', 'value' => 'demo.firephage.edge.example'],
                        ],
                        'control_panel' => [
                            'https_enforced' => true,
                            'cache_mode' => 'aggressive',
                            'origin_lockdown' => true,
                        ],
                        'demo_routing_status' => [
                            'status' => 'protected',
                            'label' => 'Protected',
                            'color' => 'success',
                            'message' => 'Demo traffic is simulated as routed through the FirePhage edge network.',
                            'expected_target' => 'demo.firephage.edge.example',
                            'checked_at' => now()->toIso8601String(),
                            'domains' => [
                                ['domain' => (string) config('demo.site.apex_domain'), 'points_to_edge' => true, 'resolved' => ['cname' => ['demo.firephage.edge.example'], 'a' => [], 'aaaa' => []]],
                                ['domain' => 'www.'.(string) config('demo.site.apex_domain'), 'points_to_edge' => true, 'resolved' => ['cname' => ['demo.firephage.edge.example'], 'a' => [], 'aaaa' => []]],
                            ],
                        ],
                    ],
                    'last_checked_at' => now()->subMinutes(3),
                    'last_provisioned_at' => now()->subDays(2),
                ],
            );

            $user->forceFill(['selected_site_id' => $site->id])->save();

            OrganizationSubscription::query()
                ->where('organization_id', $organization->id)
                ->where('site_id', $site->id)
                ->delete();

            $subscription = OrganizationSubscription::query()->create([
                'organization_id' => $organization->id,
                'site_id' => $site->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'stripe_customer_id' => 'cus_demo_firephage',
                'stripe_subscription_id' => 'sub_demo_firephage',
                'renews_at' => now()->addMonth(),
                'meta' => [
                    'interval' => 'month',
                    'demo_seeded' => true,
                ],
            ]);
            $subscription->sites()->sync([$site->id]);

            SiteAnalyticsMetric::query()->updateOrCreate(
                ['site_id' => $site->id],
                [
                    'blocked_requests_24h' => 42512,
                    'allowed_requests_24h' => 188941,
                    'total_requests_24h' => 231453,
                    'cache_hit_ratio' => 84.7,
                    'cached_requests_24h' => 159000,
                    'origin_requests_24h' => 72453,
                    'trend_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    'blocked_trend' => [4100, 5200, 6900, 5800, 6400, 7200, 6912],
                    'allowed_trend' => [25000, 26800, 27400, 28100, 29100, 30100, 31441],
                    'regional_traffic' => ['US' => 42, 'GB' => 18, 'DE' => 15, 'NL' => 9, 'Other' => 16],
                    'regional_threat' => ['US' => 31, 'GB' => 18, 'DE' => 14, 'NL' => 11, 'Other' => 26],
                    'source' => ['demo_seeded' => true],
                    'captured_at' => now()->subMinutes(2),
                ],
            );

            EdgeRequestLog::query()->where('site_id', $site->id)->delete();
            foreach ([
                ['/wp-login.php', 'BLOCK', 403, '203.0.113.25', 'US'],
                ['/wp-json/wc/store/cart', 'ALLOW', 200, '198.51.100.18', 'GB'],
                ['/xmlrpc.php', 'CHALLENGE', 429, '198.51.100.44', 'NL'],
                ['/checkout', 'ALLOW', 200, '203.0.113.77', 'DE'],
                ['/wp-admin/admin-ajax.php', 'BLOCK', 403, '203.0.113.88', 'US'],
            ] as $index => [$path, $action, $status, $ip, $country]) {
                EdgeRequestLog::query()->create([
                    'site_id' => $site->id,
                    'event_at' => now()->subMinutes(5 + $index),
                    'ip' => $ip,
                    'country' => $country,
                    'method' => 'GET',
                    'host' => (string) config('demo.site.apex_domain'),
                    'path' => $path,
                    'status_code' => $status,
                    'action' => $action,
                    'rule' => $action === 'ALLOW' ? null : 'demo_rule_'.Str::slug($path),
                    'user_agent' => 'Mozilla/5.0 (Demo Traffic)',
                    'meta' => ['demo_seeded' => true],
                ]);
            }

            SiteAvailabilityCheck::query()->where('site_id', $site->id)->delete();
            foreach ([
                [now()->subMinutes(1), 'up', 200, 182, null],
                [now()->subMinutes(6), 'up', 200, 171, null],
                [now()->subMinutes(11), 'up', 200, 195, null],
            ] as [$checkedAt, $status, $code, $latency, $error]) {
                SiteAvailabilityCheck::query()->create([
                    'site_id' => $site->id,
                    'checked_at' => $checkedAt,
                    'status' => $status,
                    'status_code' => $code,
                    'latency_ms' => $latency,
                    'error_message' => $error,
                    'meta' => ['demo_seeded' => true],
                ]);
            }

            PluginSiteConnection::query()->updateOrCreate(
                ['site_id' => $site->id],
                [
                    'site_token_hash' => hash('sha256', 'demo-plugin-token'),
                    'status' => 'connected',
                    'home_url' => 'https://'.(string) config('demo.site.apex_domain'),
                    'site_url' => 'https://'.(string) config('demo.site.apex_domain'),
                    'admin_email' => 'ops@firephage.demo',
                    'plugin_version' => '1.4.0',
                    'last_connected_at' => now()->subHours(4),
                    'last_seen_at' => now()->subMinutes(3),
                    'last_reported_at' => now()->subMinutes(4),
                    'last_report_payload' => [
                        'generated_at' => now()->subMinutes(4)->toIso8601String(),
                        'site' => [
                            'home_url' => 'https://'.(string) config('demo.site.apex_domain'),
                            'site_url' => 'https://'.(string) config('demo.site.apex_domain'),
                            'wp_version' => '6.8.1',
                            'php_version' => '8.3',
                            'plugin_version' => '1.4.0',
                        ],
                        'health' => [
                            'summary' => ['good' => 12, 'warning' => 2, 'critical' => 0],
                            'updates' => ['core_updates' => 0, 'plugin_updates' => 2, 'theme_updates' => 1, 'inactive_plugins' => 3],
                            'core_checksum' => ['status' => 'ok', 'summary' => 'Core checksums matched.'],
                        ],
                        'malware_scan' => [
                            'status' => 'review',
                            'scanned_files' => 8421,
                            'discovered_files' => 8436,
                            'suspicious_files' => 2,
                            'skipped_files' => 3,
                            'finished_at' => now()->subMinutes(9)->toIso8601String(),
                            'findings' => [
                                ['file' => 'wp-content/uploads/cache-loader.php', 'type' => 'malware', 'confidence' => 'high', 'reasons' => ['eval', 'obfuscation']],
                                ['file' => 'wp-content/plugins/old-staging/debug-shell.php', 'type' => 'review', 'confidence' => 'medium', 'reasons' => ['shell marker', 'unexpected path']],
                            ],
                        ],
                    ],
                ],
            );
        });

        $this->info('Demo dashboard account and demo site data have been seeded.');

        return self::SUCCESS;
    }
}
