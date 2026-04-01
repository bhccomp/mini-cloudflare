<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class ServicePageController extends Controller
{
    public function index(): View
    {
        return view('marketing.services.index', [
            'services' => $this->services(),
        ]);
    }

    public function show(string $service): View
    {
        $services = $this->services();
        $serviceData = $services->get($service);

        abort_unless(is_array($serviceData), 404);

        return view('marketing.services.show', [
            'serviceKey' => $service,
            'service' => $serviceData,
            'services' => $services,
            'relatedPosts' => $this->relatedPostsForService($service, $serviceData),
        ]);
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function services(): Collection
    {
        /** @var array<string, array<string, mixed>> $services */
        $services = config('marketing-services', []);

        return collect($services);
    }

    private function relatedPostsForService(string $serviceKey, array $serviceData): Collection
    {
        $keywords = collect(match ($serviceKey) {
            'waf' => ['waf', 'firewall', 'attack', 'edge security', 'origin protection'],
            'cdn' => ['cdn', 'cache', 'delivery', 'performance', 'latency'],
            'cache' => ['cache', 'caching', 'origin offload', 'edge cache', 'performance'],
            'ddos-protection' => ['ddos', 'under attack', 'traffic spike', 'incident', 'surge'],
            'bot-protection' => ['bot', 'bots', 'scraping', 'login abuse', 'brute force', 'woocommerce'],
            'wordpress-plugin' => ['wordpress plugin', 'wordpress', 'malware', 'health checks', 'plugin'],
            'uptime-monitor' => ['uptime', 'availability', 'downtime', 'monitoring'],
            default => [$serviceKey, (string) ($serviceData['nav_label'] ?? '')],
        })->filter();

        return BlogPost::query()
            ->published()
            ->when($keywords->isNotEmpty(), function ($query) use ($keywords): void {
                $query->where(function ($inner) use ($keywords): void {
                    foreach ($keywords as $keyword) {
                        $inner->orWhere('title', 'like', '%'.$keyword.'%')
                            ->orWhere('excerpt', 'like', '%'.$keyword.'%')
                            ->orWhere('content_markdown', 'like', '%'.$keyword.'%');
                    }
                });
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();
    }
}
