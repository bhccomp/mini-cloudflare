<?php

namespace App\Filament\App\Resources\SiteResource\Pages;

use App\Filament\App\Pages\SiteStatusHubPage;
use App\Filament\App\Resources\SiteResource;
use App\Jobs\MarkSiteReadyForCutoverJob;
use App\Jobs\ProvisionEdgeDeploymentJob;
use App\Models\Plan;
use App\Models\Site;
use App\Services\OrganizationAccessService;
use App\Services\Billing\SubscriptionSiteAssignmentService;
use App\Services\Edge\EdgeProviderManager;
use App\Services\SiteContext;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected ?Plan $selectedPlan = null;

    protected bool $subscriptionSlotAssigned = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $domain = $this->normalizeDomainInput((string) ($data['apex_domain'] ?? ''));
        $originIp = $this->normalizeOriginIpInput((string) ($data['origin_ip'] ?? ''));
        $this->selectedPlan = Plan::query()->find((int) ($data['plan_id'] ?? 0));

        $data['apex_domain'] = $domain;
        $data['display_name'] = $domain;
        $data['name'] = $domain;
        $data['status'] = Site::STATUS_DRAFT;
        $provider = strtolower((string) ($data['provider'] ?? config('edge.default_provider', Site::PROVIDER_BUNNY)));
        if (! array_key_exists($provider, Site::providers())) {
            $provider = Site::PROVIDER_BUNNY;
        }

        $data['provider'] = $provider;
        $data['onboarding_status'] = Site::ONBOARDING_DRAFT;
        $data['origin_type'] = 'ip';
        $data['www_enabled'] = (bool) ($data['www_enabled'] ?? true);
        $data['origin_ip'] = $originIp;
        $data['origin_url'] = $originIp !== '' ? 'http://'.$originIp : null;
        $data['origin_host'] = $domain;

        if ($this->selectedPlan) {
            $data['provider_meta'] = array_merge((array) ($data['provider_meta'] ?? []), [
                'billing' => [
                    'selected_plan_id' => $this->selectedPlan->id,
                    'selected_plan_code' => $this->selectedPlan->code,
                    'selected_interval' => 'month',
                    'checkout_required' => true,
                ],
            ]);
        }

        unset($data['plan_id']);

        return $data;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Create protection layer');
    }

    protected function getRedirectUrl(): string
    {
        $this->tryAssignExistingSubscriptionSlot();

        if ($this->subscriptionSlotAssigned) {
            return SiteStatusHubPage::getUrl(['site_id' => $this->record->id]).'&billing=covered';
        }

        if (
            $this->record
            && $this->selectedPlan
            && $this->userCanOpenCheckout()
            && ! app()->runningUnitTests()
        ) {
            return route('app.sites.checkout', [
                'site' => $this->record,
                'plan' => $this->selectedPlan,
            ]);
        }

        return SiteStatusHubPage::getUrl(['site_id' => $this->record->id]);
    }

    protected function afterCreate(): void
    {
        app(SiteContext::class)->setSelectedSiteId(auth()->user(), $this->record->id);
        $this->tryAssignExistingSubscriptionSlot();

        $body = 'Your site is selected. Continue setup in the Site Status Hub.';

        if ($this->subscriptionSlotAssigned && $this->selectedPlan) {
            $body = "Site draft created. This domain now uses an available {$this->selectedPlan->name} plan slot.";
        } elseif ($this->selectedPlan && $this->userCanOpenCheckout()) {
            $body = 'Site draft created. Continue to secure checkout to activate billing for this domain.';
        } elseif ($this->selectedPlan) {
            $body = 'Site draft created. A team member with billing access must complete checkout for this domain.';
        }

        if ($this->record->provider === Site::PROVIDER_BUNNY && ! app()->runningUnitTests()) {
            try {
                (new ProvisionEdgeDeploymentJob($this->record->id, auth()->id()))
                    ->handle(app(EdgeProviderManager::class));
                (new MarkSiteReadyForCutoverJob($this->record->id, auth()->id()))
                    ->handle();
                $body = 'Edge provisioning started automatically. Continue DNS setup in the Site Status Hub.';
            } catch (\Throwable $e) {
                $body = 'Site created, but edge provisioning failed. Open Status Hub for details and retry.';
            }
        }

        Notification::make()
            ->title('Site created')
            ->body($body)
            ->success()
            ->send();
    }

    protected function normalizeDomainInput(string $value): string
    {
        $input = strtolower(trim($value));

        if ($input === '') {
            return '';
        }

        $candidate = str_contains($input, '://') ? $input : 'https://'.ltrim($input, '/');
        $host = parse_url($candidate, PHP_URL_HOST) ?: $input;
        $host = explode(':', $host)[0];
        $host = trim($host, '.');

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    protected function normalizeOriginIpInput(string $value): string
    {
        return trim($value);
    }

    protected function userCanOpenCheckout(): bool
    {
        $user = auth()->user();

        if (! $user || ! $this->record?->organization) {
            return false;
        }

        return app(OrganizationAccessService::class)->can(
            $user,
            $this->record->organization,
            OrganizationAccessService::PERMISSION_BILLING_READ,
        );
    }

    protected function tryAssignExistingSubscriptionSlot(): void
    {
        if ($this->subscriptionSlotAssigned || ! $this->record || ! $this->selectedPlan) {
            return;
        }

        $assignmentService = app(SubscriptionSiteAssignmentService::class);
        $reusableSubscription = $assignmentService->reusableSubscriptionForPlan($this->record, $this->selectedPlan);

        if (! $reusableSubscription) {
            return;
        }

        $assignmentService->assignSite($reusableSubscription, $this->record);
        $this->subscriptionSlotAssigned = true;
    }
}
