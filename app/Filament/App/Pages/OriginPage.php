<?php

namespace App\Filament\App\Pages;

use App\Services\SiteContext;
use App\Services\UiModeManager;
use Illuminate\Http\Request;

class OriginPage extends BaseProtectionPage
{
    protected static string|\UnitEnum|null $navigationGroup = 'General';

    protected static ?string $slug = 'origin';

    protected static ?int $navigationSort = -30;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Origin';

    protected static ?string $title = 'Origin';

    protected string $view = 'filament.app.pages.protection.origin';

    public string $originHostHeaderInput = '';

    public function mount(Request $request, SiteContext $siteContext, UiModeManager $uiMode): void
    {
        parent::mount($request, $siteContext, $uiMode);

        $this->fillOriginState();
    }

    public function originExposureStatus(): string
    {
        if (! $this->site) {
            return 'Inactive';
        }

        $verification = $this->originSslVerificationEnabled();
        $coalescing = $this->requestCoalescingEnabled();
        $staleOffline = $this->staleWhileOfflineEnabled();

        return match (true) {
            $verification && $coalescing && $staleOffline => 'Hardened',
            $verification || $coalescing => 'Protected',
            default => 'Basic',
        };
    }

    public function originLatency(): string
    {
        $ms = data_get($this->site?->provider_meta, 'origin_latency_ms');

        return is_numeric($ms) ? ((int) $ms).' ms' : 'No telemetry yet';
    }

    public function originHostHeader(): string
    {
        return (string) data_get(
            $this->site?->required_dns_records,
            'control_panel.origin_host_header',
            data_get($this->site?->provider_meta, 'origin.host_header', data_get($this->site?->provider_meta, 'origin_host_header', strtolower((string) $this->site?->apex_domain)))
        );
    }

    public function requestCoalescingEnabled(): bool
    {
        return (bool) data_get(
            $this->site?->required_dns_records,
            'control_panel.request_coalescing_enabled',
            data_get($this->site?->provider_meta, 'origin.request_coalescing_enabled', true)
        );
    }

    public function requestCoalescingTimeout(): int
    {
        return (int) data_get(
            $this->site?->required_dns_records,
            'control_panel.request_coalescing_timeout',
            data_get($this->site?->provider_meta, 'origin.request_coalescing_timeout', 30)
        );
    }

    public function originRetries(): int
    {
        return (int) data_get(
            $this->site?->required_dns_records,
            'control_panel.origin_retries',
            data_get($this->site?->provider_meta, 'origin.origin_retries', 1)
        );
    }

    public function originConnectTimeout(): int
    {
        return (int) data_get(
            $this->site?->required_dns_records,
            'control_panel.origin_connect_timeout',
            data_get($this->site?->provider_meta, 'origin.origin_connect_timeout', 10)
        );
    }

    public function originResponseTimeout(): int
    {
        return (int) data_get(
            $this->site?->required_dns_records,
            'control_panel.origin_response_timeout',
            data_get($this->site?->provider_meta, 'origin.origin_response_timeout', 60)
        );
    }

    public function originRetryDelay(): int
    {
        return (int) data_get(
            $this->site?->required_dns_records,
            'control_panel.origin_retry_delay',
            data_get($this->site?->provider_meta, 'origin.origin_retry_delay', 1)
        );
    }

    public function originRetry5xxEnabled(): bool
    {
        return (bool) data_get(
            $this->site?->required_dns_records,
            'control_panel.origin_retry_5xx',
            data_get($this->site?->provider_meta, 'origin.origin_retry_5xx', true)
        );
    }

    public function originRetryConnectionTimeoutEnabled(): bool
    {
        return (bool) data_get(
            $this->site?->required_dns_records,
            'control_panel.origin_retry_connection_timeout',
            data_get($this->site?->provider_meta, 'origin.origin_retry_connection_timeout', true)
        );
    }

    public function originRetryResponseTimeoutEnabled(): bool
    {
        return (bool) data_get(
            $this->site?->required_dns_records,
            'control_panel.origin_retry_response_timeout',
            data_get($this->site?->provider_meta, 'origin.origin_retry_response_timeout', false)
        );
    }

    public function staleWhileUpdatingEnabled(): bool
    {
        return (bool) data_get(
            $this->site?->required_dns_records,
            'control_panel.stale_while_updating',
            data_get($this->site?->provider_meta, 'origin.stale_while_updating', true)
        );
    }

    public function staleWhileOfflineEnabled(): bool
    {
        return (bool) data_get(
            $this->site?->required_dns_records,
            'control_panel.stale_while_offline',
            data_get($this->site?->provider_meta, 'origin.stale_while_offline', true)
        );
    }

    public function saveOriginHostHeader(): void
    {
        if (! $this->site) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Origin host header')) {
            return;
        }

        $value = trim($this->originHostHeaderInput);
        if ($value === '') {
            $value = strtolower((string) $this->site->apex_domain);
        }

        $this->applySiteControlImmediately('origin_host_header', $value, 'Origin host header saved');
        $this->fillOriginState();
    }

    public function setOriginSslVerificationState(bool $enabled): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('Origin certificate verification')) {
            return;
        }

        $this->applySiteControlImmediately('origin_ssl_verification', $enabled, 'Origin certificate verification saved');
        $this->fillOriginState();
    }

    public function setRequestCoalescingState(bool $enabled): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('Request coalescing')) {
            return;
        }

        $this->applySiteControlImmediately('request_coalescing_enabled', $enabled, 'Request coalescing saved');
        $this->fillOriginState();
    }

    public function setRequestCoalescingTimeout(int $seconds): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('Request coalescing timeout')) {
            return;
        }

        $this->applySiteControlImmediately('request_coalescing_timeout', $seconds, 'Request coalescing timeout saved');
        $this->fillOriginState();
    }

    public function setOriginRetries(int $retries): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('Origin retries')) {
            return;
        }

        $this->applySiteControlImmediately('origin_retries', $retries, 'Origin retries saved');
        $this->fillOriginState();
    }

    public function setOriginConnectTimeout(int $seconds): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('Origin connect timeout')) {
            return;
        }

        $this->applySiteControlImmediately('origin_connect_timeout', $seconds, 'Origin connect timeout saved');
        $this->fillOriginState();
    }

    public function setOriginResponseTimeout(int $seconds): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('Origin response timeout')) {
            return;
        }

        $this->applySiteControlImmediately('origin_response_timeout', $seconds, 'Origin response timeout saved');
        $this->fillOriginState();
    }

    public function setOriginRetryDelay(int $seconds): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('Origin retry delay')) {
            return;
        }

        $this->applySiteControlImmediately('origin_retry_delay', $seconds, 'Origin retry delay saved');
        $this->fillOriginState();
    }

    public function setOriginRetry5xxState(bool $enabled): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('5xx retry policy')) {
            return;
        }

        $this->applySiteControlImmediately('origin_retry_5xx', $enabled, '5xx retry policy saved');
        $this->fillOriginState();
    }

    public function setOriginRetryConnectionTimeoutState(bool $enabled): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('Connection timeout retry policy')) {
            return;
        }

        $this->applySiteControlImmediately('origin_retry_connection_timeout', $enabled, 'Connection timeout retry policy saved');
        $this->fillOriginState();
    }

    public function setOriginRetryResponseTimeoutState(bool $enabled): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('Response timeout retry policy')) {
            return;
        }

        $this->applySiteControlImmediately('origin_retry_response_timeout', $enabled, 'Response timeout retry policy saved');
        $this->fillOriginState();
    }

    public function setStaleWhileUpdatingState(bool $enabled): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('Stale while updating')) {
            return;
        }

        $this->applySiteControlImmediately('stale_while_updating', $enabled, 'Stale while updating saved');
        $this->fillOriginState();
    }

    public function setStaleWhileOfflineState(bool $enabled): void
    {
        if (! $this->site || ! $this->ensureNotDemoReadOnly('Stale while offline')) {
            return;
        }

        $this->applySiteControlImmediately('stale_while_offline', $enabled, 'Stale while offline saved');
        $this->fillOriginState();
    }

    protected function fillOriginState(): void
    {
        $this->originHostHeaderInput = $this->originHostHeader();
    }
}
