<?php

namespace App\Http\Controllers;

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
}
