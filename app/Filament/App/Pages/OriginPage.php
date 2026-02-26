<?php

namespace App\Filament\App\Pages;

class OriginPage extends BaseProtectionPage
{
    protected static ?string $slug = 'origin';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Origin';

    protected static ?string $title = 'Origin';

    protected string $view = 'filament.app.pages.protection.origin';

    public function originExposureStatus(): string
    {
        if (! $this->site) {
            return 'Inactive';
        }

        $lockdown = (bool) data_get($this->site->required_dns_records, 'control_panel.origin_lockdown', false);
        $underAttack = (bool) $this->site->under_attack;

        return match (true) {
            $lockdown && $underAttack => 'Protected',
            $lockdown => 'Partially Exposed',
            default => 'Exposed',
        };
    }

    public function originExposureColor(): string
    {
        return match ($this->originExposureStatus()) {
            'Protected' => 'success',
            'Partially Exposed' => 'warning',
            default => 'danger',
        };
    }

    public function originLatency(): string
    {
        $ms = data_get($this->site?->provider_meta, 'origin_latency_ms');

        return is_numeric($ms) ? ((int) $ms).' ms' : 'No telemetry yet';
    }
}
