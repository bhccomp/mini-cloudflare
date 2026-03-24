<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\BandwidthUsageStats;
use App\Filament\App\Widgets\AvailabilityStatusStats;
use App\Filament\App\Widgets\CacheDistributionChart;
use App\Filament\App\Widgets\EdgeServiceStatusStats;
use App\Filament\App\Widgets\SecurityPostureTrendChart;
use App\Filament\App\Widgets\RegionalThreatLevelChart;
use App\Filament\App\Widgets\RegionalTrafficShareChart;
use App\Filament\App\Widgets\WordPress\WordPressConnectionStats;
use App\Filament\App\Resources\SiteResource;
use App\Jobs\CheckAcmDnsValidationJob;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteAvailabilityCheck;
use App\Services\Billing\SiteBillingStateService;
use App\Services\Billing\SiteUsageMeteringService;
use App\Services\Billing\SubscriptionSiteAssignmentService;
use App\Services\Billing\OrganizationEntitlementService;
use App\Services\OrganizationAccessService;
use App\Services\WordPress\PluginSiteService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteStatusHubPage extends BaseProtectionPage
{
    protected static string|\UnitEnum|null $navigationGroup = 'General';

    protected static ?string $slug = 'status-hub';

    protected static ?int $navigationSort = -50;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Status Hub';

    protected static ?string $title = 'Site Status Hub';

    protected string $view = 'filament.app.pages.site-status-hub';

    public function mount(Request $request, \App\Services\SiteContext $siteContext, \App\Services\UiModeManager $uiMode): void
    {
        parent::mount($request, $siteContext, $uiMode);

        $billingState = (string) $request->query('billing', '');

        if ($billingState === 'success') {
            Notification::make()
                ->title('Checkout completed.')
                ->body('Stripe payment was completed. FirePhage is syncing the subscription for this site now.')
                ->success()
                ->send();
        }

        if ($billingState === 'cancelled') {
            Notification::make()
                ->title('Checkout was cancelled.')
                ->body('The site draft is saved. You can restart checkout whenever you are ready.')
                ->warning()
                ->send();
        }

        if ($billingState === 'covered') {
            Notification::make()
                ->title('This site is already covered.')
                ->body('FirePhage attached this domain to an existing subscription slot for the selected plan.')
                ->success()
                ->send();
        }
    }

    public function getHeader(): ?View
    {
        return view('filament.app.pages.protection.page-header-with-routing-warning');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addSite')
                ->label('Add Site')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(SiteResource::getUrl('create')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (! $this->site || ! $this->isSiteLive()) {
            return [];
        }

        if ($this->isSimpleMode()) {
            return [];
        }

        $widgets = [
            EdgeServiceStatusStats::class,
            AvailabilityStatusStats::class,
            BandwidthUsageStats::class,
            RegionalTrafficShareChart::class,
            CacheDistributionChart::class,
            RegionalThreatLevelChart::class,
            SecurityPostureTrendChart::class,
        ];

        if ($this->site?->pluginConnection) {
            $widgets[] = WordPressConnectionStats::class;
        }

        return $widgets;
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        if ($this->isSimpleMode()) {
            return 1;
        }

        return [
            'md' => 2,
            'xl' => 4,
        ];
    }

    public function isBunnyFlow(): bool
    {
        return ($this->site?->provider ?? '') === Site::PROVIDER_BUNNY;
    }

    public function isLiveProtected(): bool
    {
        if (! $this->site) {
            return false;
        }

        return $this->isSiteLive() && ($this->edgeRoutingStatus()['status'] ?? null) === 'protected';
    }

    public function isSiteLive(): bool
    {
        if (! $this->site) {
            return false;
        }

        return $this->isBunnyFlow()
            ? ($this->site->onboarding_status === Site::ONBOARDING_LIVE
                || $this->site->status === Site::STATUS_ACTIVE)
            : $this->site->status === Site::STATUS_ACTIVE;
    }

    public function steps(): array
    {
        if ($this->isBunnyFlow()) {
            return [
                1 => 'Create site',
                2 => 'Provision edge',
                3 => 'Update DNS',
                4 => 'Verify cutover',
                5 => 'Protection active',
            ];
        }

        return [
            1 => 'Create',
            2 => 'Validate domain',
            3 => 'Deploy edge',
            4 => 'Cutover DNS',
        ];
    }

    public function currentStep(): int
    {
        if ($this->isBunnyFlow()) {
            return match ($this->site?->onboarding_status) {
                Site::ONBOARDING_DRAFT => 1,
                Site::ONBOARDING_PROVISIONING_EDGE => 2,
                Site::ONBOARDING_PENDING_DNS_CUTOVER => 3,
                Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING => 4,
                Site::ONBOARDING_LIVE => 5,
                Site::ONBOARDING_FAILED => 2,
                default => 1,
            };
        }

        return match ($this->site?->status) {
            Site::STATUS_DRAFT => 1,
            Site::STATUS_PENDING_DNS_VALIDATION => 2,
            Site::STATUS_DEPLOYING => 3,
            Site::STATUS_READY_FOR_CUTOVER, Site::STATUS_ACTIVE => 4,
            Site::STATUS_FAILED => 1,
            default => 1,
        };
    }

    public function acmValidationRecords(): array
    {
        return (array) data_get($this->site?->required_dns_records, 'acm_validation', []);
    }

    public function trafficDnsRecords(): array
    {
        $records = (array) data_get($this->site?->required_dns_records, 'traffic', []);

        if ($records !== []) {
            return $records;
        }

        return $this->cutoverRecords();
    }

    public function cutoverRecords(): array
    {
        $target = (string) ($this->site?->cloudfront_domain_name ?? '');

        if ($target === '') {
            return [];
        }

        return [
            [
                'host' => 'www.'.$this->site->apex_domain,
                'type' => 'CNAME',
                'value' => $target,
                'ttl' => 'Auto',
                'note' => 'Point www directly to the edge domain.',
            ],
            [
                'host' => $this->site->apex_domain,
                'type' => 'ALIAS / ANAME',
                'value' => $target,
                'ttl' => 'Auto',
                'note' => 'Use provider flattening/aliasing for apex records.',
            ],
        ];
    }

    public function onboardingLabel(): string
    {
        $status = $this->site?->onboarding_status;

        return Site::onboardingStatuses()[$status] ?? 'Draft';
    }

    public function autoCheckBunnyCutover(): void
    {
        $this->refreshSite();

        if (! $this->site || ! $this->isBunnyFlow()) {
            return;
        }

        if (! in_array($this->site->onboarding_status, [
            Site::ONBOARDING_PENDING_DNS_CUTOVER,
            Site::ONBOARDING_DNS_VERIFIED_SSL_PENDING,
        ], true)) {
            return;
        }

        if ($this->site->last_checked_at && $this->site->last_checked_at->gt(now()->subSeconds(10))) {
            return;
        }

        CheckAcmDnsValidationJob::dispatch($this->site->id, auth()->id());
    }

    public function pollStatus(): void
    {
        parent::pollStatus();

        if ($this->isBunnyFlow()) {
            $this->autoCheckBunnyCutover();
        }
    }

    public function siteSubscription(): ?OrganizationSubscription
    {
        if (! $this->site) {
            return null;
        }

        return app(SubscriptionSiteAssignmentService::class)->subscriptionForSite($this->site);
    }

    public function sitePlan(): ?Plan
    {
        if (! $this->site) {
            return null;
        }

        return app(OrganizationEntitlementService::class)->sitePlan($this->site);
    }

    public function siteBillingStatusLabel(): string
    {
        $status = (string) data_get($this->siteBillingState(), 'status', '');

        return match ($status) {
            'active' => 'Paid',
            'trialing' => 'Trialing',
            'past_due' => 'Past Due',
            'checkout_completed' => 'Syncing',
            default => data_get($this->site?->provider_meta, 'billing.selected_plan_id') ? 'Payment Required' : 'Not Set Up',
        };
    }

    public function siteBillingStatusColor(): string
    {
        $status = (string) data_get($this->siteBillingState(), 'status', '');

        return match ($status) {
            'active', 'trialing' => 'success',
            'checkout_completed' => 'primary',
            'past_due' => 'warning',
            default => 'gray',
        };
    }

    public function siteBillingDescription(): string
    {
        $billingState = $this->siteBillingState();
        $plan = $this->sitePlan();
        $status = (string) data_get($billingState, 'status', '');
        $assignment = app(SubscriptionSiteAssignmentService::class);
        $subscription = $this->siteSubscription();
        $siteUsage = $subscription ? $assignment->usedWebsiteSlots($subscription) : 0;
        $siteCapacity = $subscription ? $assignment->includedWebsiteSlots($subscription) : ($plan?->includedWebsites() ?? 1);
        $capacityLine = $plan ? " {$siteUsage} of {$siteCapacity} site slots are currently assigned." : '';

        $description = match ($status) {
            'active' => $plan ? "This domain is covered by the {$plan->name} plan.{$capacityLine}" : 'This domain has an active paid subscription.',
            'trialing' => $plan ? "This domain is currently trialing on the {$plan->name} plan.{$capacityLine}" : 'This domain is currently in trial.',
            'past_due' => 'Stripe reported a payment issue for this domain. Update billing in the customer portal or restart checkout.',
            'checkout_completed' => 'Checkout finished and FirePhage is syncing the subscription. Refresh shortly if the badge has not updated yet.',
            'not_set_up' => 'Choose a plan and complete checkout before activating paid protection for this domain.',
            default => $plan ? "This domain is assigned to {$plan->name}, but checkout still needs to be completed." : 'Choose a plan and complete checkout before activating paid protection for this domain.',
        };

        if (! in_array($status, ['active', 'trialing', 'past_due', 'checkout_completed', 'payment_required', 'not_set_up'], true)) {
            return (string) data_get($billingState, 'message', $description);
        }

        if (in_array($status, ['active', 'trialing'], true) && ! $subscription) {
            return (string) data_get($billingState, 'message', $description);
        }

        return $description;
    }

    public function canCheckoutSitePlan(): bool
    {
        $user = auth()->user();

        if (! $user || ! $this->site?->organization) {
            return false;
        }

        return app(OrganizationAccessService::class)->can(
            $user,
            $this->site->organization,
            OrganizationAccessService::PERMISSION_BILLING_READ,
        ) && $this->sitePlan() !== null;
    }

    public function siteCheckoutUrl(): ?string
    {
        $plan = $this->sitePlan();

        if (! $this->site || ! $plan) {
            return null;
        }

        return route('app.sites.checkout', [
            'site' => $this->site,
            'plan' => $plan,
        ]);
    }

    public function siteUsageSummary(): array
    {
        if (! $this->site) {
            return [];
        }

        return app(SiteUsageMeteringService::class)->currentMonthSummary(
            $this->site,
            $this->sitePlan(),
            $this->siteSubscription(),
        );
    }

    public function siteCapacitySummary(): array
    {
        $subscription = $this->siteSubscription();
        $plan = $this->sitePlan();
        $assignment = app(SubscriptionSiteAssignmentService::class);

        if ($subscription) {
            return [
                'used' => $assignment->usedWebsiteSlots($subscription),
                'included' => $assignment->includedWebsiteSlots($subscription),
            ];
        }

        return [
            'used' => 1,
            'included' => $plan?->includedWebsites() ?? 1,
        ];
    }

    public function siteBillingState(): array
    {
        if (! $this->site) {
            return [
                'status' => 'not_set_up',
                'subscription' => null,
                'plan' => null,
                'requires_checkout' => false,
                'can_progress_protection' => false,
                'message' => 'No site selected.',
            ];
        }

        return app(SiteBillingStateService::class)->summaryForSite($this->site);
    }

    /**
     * @return array<int, array{label:string,value:string,support:string,help:string,color:string}>
     */
    public function simpleServiceOverview(): array
    {
        if (! $this->site) {
            return [];
        }

        $routing = $this->edgeRoutingStatus();
        $wordpress = app(PluginSiteService::class);
        $health = $this->site->pluginConnection ? $wordpress->wordpressHealthSummaryForSite($this->site) : [];
        $scan = $this->site->pluginConnection ? $wordpress->wordpressScanSummaryForSite($this->site) : [];
        $latestAvailability = $this->site->availabilityChecks()->latest('checked_at')->first();

        $protectionValue = match ($routing['status'] ?? 'unavailable') {
            'protected' => 'Protected',
            'partial' => 'Partially Protected',
            'drift' => 'Needs Attention',
            default => $this->isSiteLive() ? 'Live' : 'Still onboarding',
        };
        $protectionColor = match ($routing['status'] ?? 'unavailable') {
            'protected' => 'success',
            'partial' => 'warning',
            'drift' => 'danger',
            default => 'gray',
        };

        $availabilityValue = 'Not checked yet';
        $availabilitySupport = 'FirePhage has not run an availability check for this site yet.';
        $availabilityColor = 'gray';

        if ($latestAvailability instanceof SiteAvailabilityCheck) {
            $availabilityValue = $latestAvailability->status === 'up' ? 'Up' : 'Down';
            $availabilitySupport = $latestAvailability->status === 'up'
                ? 'Latest monitor check succeeded'.($latestAvailability->latency_ms ? ' in '.$latestAvailability->latency_ms.' ms.' : '.')
                : 'Latest monitor check reported a problem'.($latestAvailability->error_message ? ': '.$latestAvailability->error_message : '.');
            $availabilityColor = $latestAvailability->status === 'up' ? 'success' : 'danger';
        }

        $wordpressValue = 'Not connected';
        $wordpressSupport = 'Connect the plugin to start collecting WordPress health and malware scan data.';
        $wordpressColor = 'gray';

        if ($this->site->pluginConnection) {
            $warnings = (int) ($health['warning'] ?? 0);
            $critical = (int) ($health['critical'] ?? 0);
            $suspicious = (int) ($scan['suspicious_files'] ?? 0);
            $wordpressValue = $critical > 0 || $suspicious > 0
                ? 'Needs review'
                : 'Connected';
            $wordpressSupport = $critical > 0 || $suspicious > 0
                ? trim("{$critical} critical issue(s), {$warnings} warning(s), {$suspicious} suspicious file(s) reported.")
                : 'Plugin is connected and recent health reports look stable.';
            $wordpressColor = $critical > 0 || $suspicious > 0 ? 'warning' : 'success';
        }

        return [
            [
                'label' => 'Protection',
                'value' => $protectionValue,
                'support' => (string) ($routing['message'] ?? 'Traffic routing and protection status for this site.'),
                'help' => 'This tells you whether traffic is actually flowing through FirePhage protection right now, not just whether the site was set up once.',
                'color' => $protectionColor,
            ],
            [
                'label' => 'Availability',
                'value' => $availabilityValue,
                'support' => $availabilitySupport,
                'help' => 'Availability checks confirm whether the site is reachable from the outside. This is the closest simple uptime signal in the dashboard.',
                'color' => $availabilityColor,
            ],
            [
                'label' => 'Billing',
                'value' => $this->siteBillingStatusLabel(),
                'support' => $this->siteBillingDescription(),
                'help' => 'Billing status controls whether paid protection and related features are active for this site.',
                'color' => $this->siteBillingStatusColor(),
            ],
            [
                'label' => 'SSL',
                'value' => $this->certificateStatus(),
                'support' => 'Certificate status for encrypted visitor traffic.',
                'help' => 'SSL shows whether the site has a valid certificate in place or is still waiting on validation or issuance.',
                'color' => str_contains(strtolower($this->certificateStatus()), 'active') || str_contains(strtolower($this->certificateStatus()), 'issued') ? 'success' : 'warning',
            ],
            [
                'label' => 'CDN',
                'value' => $this->distributionHealth(),
                'support' => 'Edge delivery status for the site.',
                'help' => 'CDN status tells you whether FirePhage edge delivery is deployed and healthy enough to serve traffic properly.',
                'color' => $this->distributionHealth() === 'Healthy' ? 'success' : 'warning',
            ],
            [
                'label' => 'Cache',
                'value' => ucfirst($this->cacheMode()),
                'support' => 'Current hit ratio: '.$this->metricCacheHitRatio(),
                'help' => 'Cache mode affects how aggressively FirePhage serves repeat requests from the edge instead of your origin server.',
                'color' => $this->cacheMode() === 'aggressive' ? 'success' : 'primary',
            ],
            [
                'label' => 'WordPress',
                'value' => $wordpressValue,
                'support' => $wordpressSupport,
                'help' => 'This summarizes whether the WordPress plugin is connected and whether recent health or malware reports need attention.',
                'color' => $wordpressColor,
            ],
        ];
    }

    /**
     * @return array{title:string,body:string,color:string}
     */
    public function simpleServiceOverviewRecommendation(): array
    {
        $items = collect($this->simpleServiceOverview());
        $issues = $items->filter(fn (array $item): bool => in_array($item['color'], ['danger', 'warning'], true))->count();

        if ($issues >= 3) {
            return [
                'title' => 'What to focus on first',
                'body' => 'A few services need attention. Start with protection routing, billing status, and WordPress health so the site is both covered and monitored correctly.',
                'color' => 'warning',
            ];
        }

        if ($issues >= 1) {
            return [
                'title' => 'What to check next',
                'body' => 'Most of the site looks healthy. Review the highlighted cards below to resolve the remaining setup or monitoring gaps.',
                'color' => 'primary',
            ];
        }

        return [
            'title' => 'What this means',
            'body' => 'Core services look healthy. This simple view keeps the main status signals visible without requiring you to open each technical page.',
            'color' => 'success',
        ];
    }
}
