<?php

namespace App\Services\Analytics;

use App\Models\Site;
use App\Models\SiteAnalyticsMetric;
use App\Services\Aws\AwsAnalyticsService;
use App\Services\Bunny\BunnyAnalyticsService;

class AnalyticsSyncManager
{
    public function __construct(
        protected AwsAnalyticsService $aws,
        protected BunnyAnalyticsService $bunny,
    ) {}

    public function syncSiteMetrics(Site $site): ?SiteAnalyticsMetric
    {
        return match ($site->provider) {
            Site::PROVIDER_BUNNY => $this->bunny->syncSiteMetrics($site),
            default => $this->aws->syncSiteMetrics($site),
        };
    }
}
