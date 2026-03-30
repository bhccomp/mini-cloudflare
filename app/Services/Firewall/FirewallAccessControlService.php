<?php

namespace App\Services\Firewall;

use App\Models\AuditLog;
use App\Models\Site;
use App\Models\SiteFirewallRule;
use App\Services\Bunny\BunnyShieldAccessListService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;

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
        ?int $actorId,
        string $ruleType,
        array $targets,
        string $action,
        string $mode = SiteFirewallRule::MODE_ENFORCED,
        ?CarbonInterface $expiresAt = null,
        ?string $note = null,
        ?string $displayName = null,
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
            $resolvedDisplayName = $displayName ?: $this->singleRuleLabel($ruleType, $target, $action);

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
                'meta' => [
                    'display_name' => $resolvedDisplayName,
                ],
            ]);

            if ($mode === SiteFirewallRule::MODE_ENFORCED) {
                $this->applyRule($rule, $actorId);
            }

            $created[] = $rule->fresh();
        }

        return $created;
    }

    public function applyRule(SiteFirewallRule $rule, ?int $actorId): SiteFirewallRule
    {
        $site = $rule->site;

        if (! $site || ! $this->supportsManagedRules($site)) {
            $rule->update([
                'status' => SiteFirewallRule::STATUS_PENDING,
                'meta' => ['message' => 'Managed access rules are not supported for this edge mode.'],
            ]);

            return $rule->fresh();
        }

        $meta = is_array($rule->meta) ? $rule->meta : [];
        $providerPayload = $this->providerRulePayload($site, $rule, $meta);

        try {
            $providerResult = $this->bunny->createRule($site, [
                'rule_type' => $providerPayload['rule_type'],
                'target' => $providerPayload['target'],
                'content' => $providerPayload['content'],
                'action' => $rule->action,
                'expires_at' => $rule->expires_at?->toIso8601String(),
                'note' => $rule->note,
            ]);

            $meta['provider_response'] = $providerResult['response'] ?? [];
            unset($meta['error']);

            $rule->update([
                'provider_rule_id' => (string) ($providerResult['provider_rule_id'] ?? ''),
                'status' => SiteFirewallRule::STATUS_ACTIVE,
                'mode' => SiteFirewallRule::MODE_ENFORCED,
                'activated_at' => now(),
                'meta' => $meta,
            ]);

            $this->audit($site, $actorId, 'firewall.rule.apply', 'success', 'Firewall access rule enforced.', [
                'rule_id' => $rule->id,
                'provider_rule_id' => $rule->provider_rule_id,
                'rule_type' => $rule->rule_type,
                'target' => $rule->target,
                'action' => $rule->action,
            ]);
        } catch (\Throwable $exception) {
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

    /**
     * @param  array<string, mixed>  $meta
     * @return array{rule_type: string, target: string, content: ?string}
     */
    protected function providerRulePayload(Site $site, SiteFirewallRule $rule, array $meta): array
    {
        $content = trim((string) ($meta['content'] ?? ''));

        if ($rule->rule_type !== SiteFirewallRule::TYPE_CONTINENT) {
            return [
                'rule_type' => $rule->rule_type,
                'target' => $rule->target,
                'content' => $content !== '' ? $content : null,
            ];
        }

        $selectedContinents = collect(Arr::wrap($meta['targets'] ?? []))
            ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
            ->filter()
            ->values();

        if ($selectedContinents->isEmpty()) {
            $selectedContinents = collect([strtoupper(trim((string) $rule->target))])->filter()->values();
        }

        $continentMap = $this->bunny->continentCountries();
        $countryTargets = $selectedContinents
            ->flatMap(fn (string $continent): array => $continentMap[$continent] ?? [])
            ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
            ->filter(fn (string $value): bool => strlen($value) === 2)
            ->unique()
            ->values();

        if ($countryTargets->isEmpty()) {
            throw new \RuntimeException('No country mapping is available for the selected continent.');
        }

        return [
            'rule_type' => SiteFirewallRule::TYPE_COUNTRY,
            'target' => $rule->target,
            'content' => $countryTargets->implode("\n"),
        ];
    }

    /**
     * @return array<int, SiteFirewallRule>
     */
    public function createRuleSet(
        Site $site,
        ?int $actorId,
        string $ruleType,
        array $targets,
        string $action,
        string $mode = SiteFirewallRule::MODE_ENFORCED,
        ?CarbonInterface $expiresAt = null,
        ?string $note = null,
        ?string $displayName = null,
    ): array {
        $targets = collect($targets)
            ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values();

        if ($targets->isEmpty()) {
            return [];
        }

        $existing = $this->groupedRulesQuery(
            site: $site,
            ruleType: $ruleType,
            action: $action,
            mode: $mode,
        )->get();

        if ($existing->isNotEmpty()) {
            return [$this->mergeGroupedRules(
                rules: $existing,
                incomingTargets: $targets->all(),
                actorId: $actorId,
                displayName: $displayName,
                note: $note,
                expiresAt: $expiresAt,
            )];
        }

        $count = $targets->count();
        $summary = $displayName ?: $this->ruleSetLabel($ruleType, $action);

        $meta = [
            'targets' => $targets->all(),
            'content' => $targets->implode("\n"),
            'entry_count' => $count,
            'display_name' => $summary,
        ];

        $rule = SiteFirewallRule::query()->create([
            'site_id' => $site->id,
            'created_by_user_id' => $actorId,
            'provider' => $site->provider,
            'rule_type' => $ruleType,
            'target' => $summary,
            'action' => $action,
            'mode' => $mode,
            'status' => SiteFirewallRule::STATUS_PENDING,
            'expires_at' => $expiresAt,
            'note' => $note,
            'meta' => $meta,
        ]);

        if ($mode === SiteFirewallRule::MODE_ENFORCED) {
            $this->applyRule($rule, $actorId);
        }

        return [$rule->fresh()];
    }

    public function consolidateGroupedRuleSets(Site $site, ?int $actorId = null): void
    {
        SiteFirewallRule::query()
            ->where('site_id', $site->id)
            ->whereIn('rule_type', [SiteFirewallRule::TYPE_COUNTRY, SiteFirewallRule::TYPE_CONTINENT])
            ->whereIn('status', [
                SiteFirewallRule::STATUS_PENDING,
                SiteFirewallRule::STATUS_ACTIVE,
                SiteFirewallRule::STATUS_FAILED,
            ])
            ->get()
            ->groupBy(fn (SiteFirewallRule $rule): string => implode('|', [
                $rule->rule_type,
                $rule->action,
                $rule->mode,
            ]))
            ->each(function (Collection $rules) use ($actorId): void {
                if ($rules->count() < 2) {
                    return;
                }

                $this->mergeGroupedRules(
                    rules: $rules,
                    incomingTargets: [],
                    actorId: $actorId,
                );
            });
    }

    public function singleRuleLabel(string $ruleType, string $target, string $action): string
    {
        $target = trim($target);

        return match ($ruleType) {
            SiteFirewallRule::TYPE_IP => 'IP '.$this->actionVerb($action).' '.$target,
            SiteFirewallRule::TYPE_CIDR => 'Network '.$this->actionVerb($action).' '.$target,
            SiteFirewallRule::TYPE_ASN => 'ASN '.$this->actionVerb($action).' '.$target,
            SiteFirewallRule::TYPE_COUNTRY => 'Country '.$this->actionVerb($action).' '.$target,
            SiteFirewallRule::TYPE_CONTINENT => 'Continent '.$this->actionVerb($action).' '.$target,
            default => $target,
        };
    }

    protected function actionVerb(string $action): string
    {
        return match (strtolower($action)) {
            SiteFirewallRule::ACTION_ALLOW => 'allow',
            SiteFirewallRule::ACTION_CHALLENGE => 'challenge',
            SiteFirewallRule::ACTION_LOG => 'log',
            default => 'block',
        };
    }

    public function removeRule(SiteFirewallRule $rule, ?int $actorId): void
    {
        $site = $rule->site;

        if ($site && $this->supportsManagedRules($site) && $rule->provider_rule_id) {
            $this->bunny->deleteRule($site, (string) $rule->provider_rule_id);
        }

        if ($site) {
            $this->audit($site, $actorId, 'firewall.rule.remove', 'success', 'Firewall access rule removed.', [
                'rule_id' => $rule->id,
                'provider_rule_id' => $rule->provider_rule_id,
            ]);
        }

        $rule->delete();
    }

    public function disableRule(SiteFirewallRule $rule, ?int $actorId): SiteFirewallRule
    {
        $site = $rule->site;

        if ($site && $this->supportsManagedRules($site) && $rule->provider_rule_id) {
            $this->bunny->deleteRule($site, (string) $rule->provider_rule_id);
        }

        $meta = is_array($rule->meta) ? $rule->meta : [];
        unset($meta['error']);
        $meta['disabled_at'] = now()->toIso8601String();

        $rule->update([
            'provider_rule_id' => null,
            'status' => SiteFirewallRule::STATUS_REMOVED,
            'meta' => $meta,
        ]);

        if ($site) {
            $this->audit($site, $actorId, 'firewall.rule.disable', 'success', 'Firewall access rule disabled.', [
                'rule_id' => $rule->id,
                'rule_type' => $rule->rule_type,
                'target' => $rule->target,
                'action' => $rule->action,
            ]);
        }

        return $rule->fresh();
    }

    public function enableRule(SiteFirewallRule $rule, ?int $actorId): SiteFirewallRule
    {
        $meta = is_array($rule->meta) ? $rule->meta : [];
        unset($meta['error'], $meta['disabled_at']);

        $rule->update([
            'status' => SiteFirewallRule::STATUS_PENDING,
            'meta' => $meta,
        ]);

        if ($rule->mode === SiteFirewallRule::MODE_ENFORCED) {
            return $this->applyRule($rule->fresh(), $actorId);
        }

        return $rule->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRule(SiteFirewallRule $rule, ?int $actorId, array $data): SiteFirewallRule
    {
        $site = $rule->site;
        if (! $site) {
            return $rule->fresh();
        }

        if ($this->supportsManagedRules($site) && $rule->provider_rule_id) {
            try {
                $this->bunny->deleteRule($site, (string) $rule->provider_rule_id);
            } catch (\Throwable) {
                // Continue with local update and re-apply; old edge rule may already be missing.
            }
        }

        $meta = is_array($rule->meta) ? $rule->meta : [];
        $ruleType = $rule->rule_type;
        $target = trim((string) Arr::get($data, 'target', $rule->target));
        $action = strtolower((string) Arr::get($data, 'action', $rule->action));
        $displayName = trim((string) Arr::get($data, 'display_name', (string) ($meta['display_name'] ?? '')));

        if (in_array($ruleType, [SiteFirewallRule::TYPE_COUNTRY, SiteFirewallRule::TYPE_CONTINENT], true)) {
            $codes = collect(Arr::wrap(Arr::get($data, 'grouped_targets', [])))
                ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
                ->filter(fn (string $code): bool => preg_match('/^[A-Z]{2}$/', $code) === 1)
                ->unique()
                ->values();

            if ($codes->isEmpty()) {
                $sourceField = $ruleType === SiteFirewallRule::TYPE_COUNTRY ? 'countries_content' : 'continents_content';

                $codes = collect(preg_split('/\r\n|\r|\n/', (string) Arr::get($data, $sourceField, '')) ?: [])
                ->map(fn (string $line): string => strtoupper(trim($line)))
                ->filter(fn (string $code): bool => preg_match('/^[A-Z]{2}$/', $code) === 1)
                ->unique()
                ->values();
            }

            if ($codes->isNotEmpty()) {
                $meta['targets'] = $codes->all();
                $meta['content'] = $codes->implode("\n");
                $meta['entry_count'] = $codes->count();
                $target = $this->ruleSetLabel($ruleType, $action);
            }
        } else {
            unset($meta['targets'], $meta['content'], $meta['entry_count']);
        }

        $meta['display_name'] = $displayName !== ''
            ? $displayName
            : ($ruleType === SiteFirewallRule::TYPE_COUNTRY && ! empty($meta['entry_count'])
                ? $this->ruleSetLabel($ruleType, $action)
                : $this->singleRuleLabel($ruleType, $target, $action));

        unset($meta['error'], $meta['provider_response']);

        $mode = (string) Arr::get($data, 'mode', $rule->mode);
        $action = strtolower((string) Arr::get($data, 'action', $rule->action));
        $note = Arr::get($data, 'note');
        $expiresAt = Arr::get($data, 'expires_at');

        $rule->update([
            'target' => $target,
            'action' => $action,
            'mode' => $mode,
            'note' => is_string($note) ? $note : null,
            'expires_at' => $expiresAt instanceof CarbonInterface ? $expiresAt : null,
            'provider_rule_id' => null,
            'status' => SiteFirewallRule::STATUS_PENDING,
            'activated_at' => null,
            'meta' => $meta,
        ]);

        $this->audit($site, $actorId, 'firewall.rule.update', 'success', 'Firewall access rule updated.', [
            'rule_id' => $rule->id,
            'rule_type' => $rule->rule_type,
            'target' => $rule->target,
            'action' => $rule->action,
            'mode' => $rule->mode,
        ]);

        if ($mode === SiteFirewallRule::MODE_ENFORCED) {
            return $this->applyRule($rule->fresh(), $actorId);
        }

        return $rule->fresh();
    }

    public function removeTargetFromRuleSet(SiteFirewallRule $rule, ?int $actorId, string $target): ?SiteFirewallRule
    {
        if (! in_array($rule->rule_type, [SiteFirewallRule::TYPE_COUNTRY, SiteFirewallRule::TYPE_CONTINENT], true)) {
            $this->removeRule($rule, $actorId);

            return null;
        }

        $target = strtoupper(trim($target));
        $remainingTargets = collect($this->groupedRuleTargets($rule))
            ->reject(fn (string $value): bool => $value === $target)
            ->values();

        if ($remainingTargets->isEmpty()) {
            $this->removeRule($rule, $actorId);

            return null;
        }

        return $this->updateRule($rule, $actorId, [
            'action' => $rule->action,
            'mode' => $rule->mode,
            'display_name' => (string) data_get($rule->meta, 'display_name', ''),
            'note' => $rule->note,
            'expires_at' => $rule->expires_at,
            'grouped_targets' => $remainingTargets->all(),
        ]);
    }

    public function deployStagedRules(Site $site, ?int $actorId): int
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

    public function expireTemporaryRules(Site $site, ?int $actorId): int
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

    public function quickBlockIp(Site $site, ?int $actorId, string $ip, ?string $note = null): ?SiteFirewallRule
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
    protected function audit(Site $site, ?int $actorId, string $action, string $status, string $message, array $meta = []): void
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

    protected function ruleSetLabel(string $ruleType, string $action): string
    {
        $typeLabel = match ($ruleType) {
            SiteFirewallRule::TYPE_COUNTRY => 'Country',
            SiteFirewallRule::TYPE_CONTINENT => 'Continent',
            SiteFirewallRule::TYPE_IP => 'IP',
            SiteFirewallRule::TYPE_CIDR => 'CIDR',
            SiteFirewallRule::TYPE_ASN => 'ASN',
            default => 'Access',
        };

        $suffix = match (strtolower($action)) {
            SiteFirewallRule::ACTION_ALLOW => 'Allowlist',
            SiteFirewallRule::ACTION_CHALLENGE => 'Challenges',
            SiteFirewallRule::ACTION_LOG => 'Logs',
            default => 'Blocks',
        };

        return "{$typeLabel} {$suffix}";
    }

    protected function groupedRulesQuery(
        Site $site,
        string $ruleType,
        string $action,
        string $mode,
    )
    {
        return SiteFirewallRule::query()
            ->where('site_id', $site->id)
            ->where('rule_type', $ruleType)
            ->where('action', $action)
            ->where('mode', $mode)
            ->whereIn('status', [
                SiteFirewallRule::STATUS_PENDING,
                SiteFirewallRule::STATUS_ACTIVE,
                SiteFirewallRule::STATUS_REMOVED,
                SiteFirewallRule::STATUS_FAILED,
            ]);
    }

    /**
     * @param  Collection<int, SiteFirewallRule>  $rules
     * @param  array<int, string>  $incomingTargets
     */
    protected function mergeGroupedRules(
        Collection $rules,
        array $incomingTargets,
        ?int $actorId,
        ?string $displayName = null,
        ?string $note = null,
        ?CarbonInterface $expiresAt = null,
    ): SiteFirewallRule {
        /** @var SiteFirewallRule $keeper */
        $keeper = $rules
            ->sortBy([
                fn (SiteFirewallRule $rule): int => match ($rule->status) {
                    SiteFirewallRule::STATUS_ACTIVE => 0,
                    SiteFirewallRule::STATUS_PENDING => 1,
                    SiteFirewallRule::STATUS_FAILED => 2,
                    SiteFirewallRule::STATUS_REMOVED => 3,
                    default => 4,
                },
                fn (SiteFirewallRule $rule): int => $rule->id,
            ])
            ->first();

        $mergedTargets = $rules
            ->flatMap(fn (SiteFirewallRule $rule): array => $this->groupedRuleTargets($rule))
            ->merge($incomingTargets)
            ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
            ->filter(fn (string $value): bool => preg_match('/^[A-Z]{2}$/', $value) === 1)
            ->unique()
            ->values();

        $updated = $this->updateRule($keeper, $actorId, [
            'action' => $keeper->action,
            'mode' => $keeper->mode,
            'display_name' => $displayName !== null && trim($displayName) !== ''
                ? $displayName
                : (string) data_get($keeper->meta, 'display_name', $this->ruleSetLabel($keeper->rule_type, $keeper->action)),
            'note' => $note ?? $keeper->note,
            'expires_at' => $expiresAt ?? $keeper->expires_at,
            'grouped_targets' => $mergedTargets->all(),
        ]);

        $rules
            ->reject(fn (SiteFirewallRule $rule): bool => $rule->id === $keeper->id)
            ->each(fn (SiteFirewallRule $rule) => $this->removeRule($rule, $actorId));

        return $updated;
    }

    /**
     * @return array<int, string>
     */
    protected function groupedRuleTargets(SiteFirewallRule $rule): array
    {
        $meta = is_array($rule->meta) ? $rule->meta : [];
        $targets = collect(Arr::wrap($meta['targets'] ?? []))
            ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
            ->filter(fn (string $value): bool => preg_match('/^[A-Z]{2}$/', $value) === 1)
            ->unique()
            ->values();

        if ($targets->isNotEmpty()) {
            return $targets->all();
        }

        return collect(preg_split('/\r\n|\r|\n/', (string) ($meta['content'] ?? '')) ?: [])
            ->map(fn (string $line): string => strtoupper(trim($line)))
            ->filter(fn (string $value): bool => preg_match('/^[A-Z]{2}$/', $value) === 1)
            ->unique()
            ->values()
            ->all();
    }
}
