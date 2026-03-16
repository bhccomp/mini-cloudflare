<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\BandwidthUsageStats;
use App\Filament\App\Widgets\CacheDistributionChart;
use App\Filament\App\Widgets\SecurityPostureTrendChart;
use App\Filament\App\Widgets\RegionalThreatLevelChart;
use App\Filament\App\Widgets\RegionalTrafficShareChart;
use App\Filament\App\Widgets\SiteSignalsStats;
use App\Filament\App\Resources\SiteResource;
use App\Jobs\CheckAcmDnsValidationJob;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\Site;
use App\Services\Billing\SiteUsageMeteringService;
use App\Services\Billing\SubscriptionSiteAssignmentService;
use App\Services\OrganizationAccessService;
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
            return [
                SiteSignalsStats::class,
                BandwidthUsageStats::class,
            ];
        }

        return [
            BandwidthUsageStats::class,
            RegionalTrafficShareChart::class,
            CacheDistributionChart::class,
            RegionalThreatLevelChart::class,
            SecurityPostureTrendChart::class,
        ];
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
        $subscriptionPlan = $this->siteSubscription()?->plan;

        if ($subscriptionPlan) {
            return $subscriptionPlan;
        }

        $planId = (int) data_get($this->site?->provider_meta, 'billing.selected_plan_id', 0);

        if ($planId < 1) {
            return null;
        }

        return Plan::query()->find($planId);
    }

    public function siteBillingStatusLabel(): string
    {
        $status = (string) ($this->siteSubscription()?->status ?? '');

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
        $status = (string) ($this->siteSubscription()?->status ?? '');

        return match ($status) {
            'active', 'trialing' => 'success',
            'checkout_completed' => 'primary',
            'past_due' => 'warning',
            default => 'gray',
        };
    }

    public function siteBillingDescription(): string
    {
        $plan = $this->sitePlan();
        $status = (string) ($this->siteSubscription()?->status ?? '');
        $assignment = app(SubscriptionSiteAssignmentService::class);
        $subscription = $this->siteSubscription();
        $siteUsage = $subscription ? $assignment->usedWebsiteSlots($subscription) : 0;
        $siteCapacity = $subscription ? $assignment->includedWebsiteSlots($subscription) : ($plan?->includedWebsites() ?? 1);
        $capacityLine = $plan ? " {$siteUsage} of {$siteCapacity} site slots are currently assigned." : '';

        return match ($status) {
            'active' => $plan ? "This domain is covered by the {$plan->name} plan.{$capacityLine}" : 'This domain has an active paid subscription.',
            'trialing' => $plan ? "This domain is currently trialing on the {$plan->name} plan.{$capacityLine}" : 'This domain is currently in trial.',
            'past_due' => 'Stripe reported a payment issue for this domain. Update billing in the customer portal or restart checkout.',
            'checkout_completed' => 'Checkout finished and FirePhage is syncing the subscription. Refresh shortly if the badge has not updated yet.',
            default => $plan ? "This domain is assigned to {$plan->name}, but checkout still needs to be completed." : 'Choose a plan and complete checkout before activating paid protection for this domain.',
        };
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
}
