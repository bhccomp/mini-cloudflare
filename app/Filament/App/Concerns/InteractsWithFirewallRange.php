<?php

namespace App\Filament\App\Concerns;

trait InteractsWithFirewallRange
{
    protected function firewallRange(): string
    {
        return request()->query('range') === '7d' ? '7d' : '24h';
    }

    protected function firewallRangeLabel(): string
    {
        return $this->firewallRange() === '7d' ? '7 days' : '24 hours';
    }
}
