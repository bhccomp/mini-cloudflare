<?php

namespace App\Filament\App\Concerns;

trait InteractsWithFirewallRange
{
    public ?string $selectedFirewallRange = null;

    protected function firewallRange(): string
    {
        if ($this->selectedFirewallRange === '7d') {
            return '7d';
        }

        return request()->query('range') === '7d' ? '7d' : '24h';
    }

    protected function firewallRangeLabel(): string
    {
        return $this->firewallRange() === '7d' ? '7 days' : '24 hours';
    }
}
