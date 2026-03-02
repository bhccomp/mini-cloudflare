<?php

namespace App\Services\Firewall;

use App\Models\AuditLog;
use App\Models\Site;
use App\Models\SiteFirewallRule;
use App\Services\Bunny\BunnyShieldAccessListService;
use Carbon\CarbonInterface;

class FirewallAccessControlService
{
    public function __construct(protected BunnyShieldAccessListService $bunny) {}

    public function supportsManagedRules(Site $site): bool
    {
        return $site->provider === Site::PROVIDER_BUNNY;
    }

    /**
     * @return array<string, string>
     */
    public function countryOptions(Site $site): array
    {
        if (! $this->supportsManagedRules($site)) {
            return [];
        }

        return $this->bunny->countries();
    }

    /**
     * @return array<string, string>
     */
    public function continentOptions(Site $site): array
    {
        if (! $this->supportsManagedRules($site)) {
            return [];
        }

        $map = $this->bunny->continentCountries();
        $labels = [
            'AF' => 'Africa',
            'AN' => 'Antarctica',
            'AS' => 'Asia',
            'EU' => 'Europe',
            'NA' => 'North America',
            'OC' => 'Oceania',
            'SA' => 'South America',
        ];

        return collect(array_keys($map))
            ->sort()
            ->mapWithKeys(fn (string $code): array => [$code => $labels[$code] ?? $code])
            ->all();
    }

    /**
     * @return array<int, SiteFirewallRule>
     */
    public function createRules(
        Site $site,
        int $actorId,
        string $ruleType,
        array $targets,
        string $action,
        string $mode = SiteFirewallRule::MODE_ENFORCED,
        ?CarbonInterface $expiresAt = null,
        ?string $note = null,
    ): array {
        $targets = collect($targets)
            ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values();

        if ($targets->isEmpty()) {
            return [];
        }

        $created = [];

        foreach ($targets as $target) {
            $rule = SiteFirewallRule::query()->create([
                'site_id' => $site->id,
                'created_by_user_id' => $actorId,
                'provider' => $site->provider,
                'rule_type' => $ruleType,
                'target' => $target,
                'action' => $action,
                'mode' => $mode,
                'status' => SiteFirewallRule::STATUS_PENDING,
                'expires_at' => $expiresAt,
                'note' => $note,
            ]);

            if ($mode === SiteFirewallRule::MODE_ENFORCED) {
                $this->applyRule($rule, $actorId);
            }

            $created[] = $rule->fresh();
        }

        return $created;
    }

    public function applyRule(SiteFirewallRule $rule, int $actorId): SiteFirewallRule
    {
        $site = $rule->site;

        if (! $site || ! $this->supportsManagedRules($site)) {
            $rule->update([
                'status' => SiteFirewallRule::STATUS_PENDING,
                'meta' => ['message' => 'Managed access rules are not supported for this edge mode.'],
            ]);

            return $rule->fresh();
        }

        try {
            $providerResult = $this->bunny->createRule($site, [
                'rule_type' => $rule->rule_type,
                'target' => $rule->target,
                'action' => $rule->action,
                'expires_at' => $rule->expires_at?->toIso8601String(),
                'note' => $rule->note,
            ]);

            $rule->update([
                'provider_rule_id' => (string) ($providerResult['provider_rule_id'] ?? ''),
                'status' => SiteFirewallRule::STATUS_ACTIVE,
                'mode' => SiteFirewallRule::MODE_ENFORCED,
                'activated_at' => now(),
                'meta' => [
                    'provider_response' => $providerResult['response'] ?? [],
                ],
            ]);

            $this->audit($site, $actorId, 'firewall.rule.apply', 'success', 'Firewall access rule enforced.', [
                'rule_id' => $rule->id,
                'provider_rule_id' => $rule->provider_rule_id,
                'rule_type' => $rule->rule_type,
                'target' => $rule->target,
                'action' => $rule->action,
            ]);
        } catch (\Throwable $exception) {
            $meta = is_array($rule->meta) ? $rule->meta : [];
            $meta['error'] = $exception->getMessage();

            $rule->update([
                'status' => SiteFirewallRule::STATUS_FAILED,
                'meta' => $meta,
            ]);

            $this->audit($site, $actorId, 'firewall.rule.apply', 'failed', 'Firewall access rule enforcement failed.', [
                'rule_id' => $rule->id,
                'rule_type' => $rule->rule_type,
                'target' => $rule->target,
                'action' => $rule->action,
                'error' => $exception->getMessage(),
            ]);
        }

        return $rule->fresh();
    }

    public function removeRule(SiteFirewallRule $rule, int $actorId): SiteFirewallRule
    {
        $site = $rule->site;

        if ($site && $this->supportsManagedRules($site) && $rule->provider_rule_id) {
            $this->bunny->deleteRule($site, (string) $rule->provider_rule_id);
        }

        $rule->update([
            'status' => SiteFirewallRule::STATUS_REMOVED,
            'expired_at' => now(),
        ]);

        if ($site) {
            $this->audit($site, $actorId, 'firewall.rule.remove', 'success', 'Firewall access rule removed.', [
                'rule_id' => $rule->id,
                'provider_rule_id' => $rule->provider_rule_id,
            ]);
        }

        return $rule->fresh();
    }

    public function deployStagedRules(Site $site, int $actorId): int
    {
        $rules = SiteFirewallRule::query()
            ->where('site_id', $site->id)
            ->where('mode', SiteFirewallRule::MODE_STAGED)
            ->whereIn('status', [SiteFirewallRule::STATUS_PENDING, SiteFirewallRule::STATUS_ACTIVE])
            ->orderBy('action')
            ->orderBy('id')
            ->get();

        $count = 0;

        foreach ($rules as $rule) {
            $this->applyRule($rule, $actorId);
            $count++;
        }

        return $count;
    }

    public function expireTemporaryRules(Site $site, int $actorId): int
    {
        $rules = SiteFirewallRule::query()
            ->where('site_id', $site->id)
            ->where('status', SiteFirewallRule::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($rules as $rule) {
            $this->removeRule($rule, $actorId);
            $rule->update(['status' => SiteFirewallRule::STATUS_EXPIRED]);
            $count++;
        }

        return $count;
    }

    public function setPolicyFlags(Site $site, bool $stagingMode, bool $allowlistPriority, ?int $actorId = null): void
    {
        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];
        $meta['firewall_policy'] = [
            'staging_mode' => $stagingMode,
            'allowlist_priority' => $allowlistPriority,
        ];

        $site->update(['provider_meta' => $meta]);

        if ($actorId) {
            $this->audit($site, $actorId, 'firewall.policy.update', 'success', 'Firewall policy updated.', $meta['firewall_policy']);
        }
    }

    public function quickBlockIp(Site $site, int $actorId, string $ip, ?string $note = null): ?SiteFirewallRule
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $created = $this->createRules(
            site: $site,
            actorId: $actorId,
            ruleType: SiteFirewallRule::TYPE_IP,
            targets: [$ip],
            action: SiteFirewallRule::ACTION_BLOCK,
            mode: SiteFirewallRule::MODE_ENFORCED,
            expiresAt: now()->addDay(),
            note: $note ?: 'Added from firewall event row.',
        );

        return $created[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function audit(Site $site, int $actorId, string $action, string $status, string $message, array $meta = []): void
    {
        AuditLog::query()->create([
            'actor_id' => $actorId,
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'meta' => $meta + ['provider' => $site->provider],
        ]);
    }
}
