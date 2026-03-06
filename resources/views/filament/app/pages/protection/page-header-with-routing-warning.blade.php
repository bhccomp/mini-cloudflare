@php
    $heading = $this->getHeading();
    $headerActions = $this->getCachedHeaderActions();
    $headerActionsAlignment = $this->getHeaderActionsAlignment();
    $breadcrumbs = filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [];
    $subheading = $this->getSubheading();
@endphp

<div class="space-y-6">
    @if (filled($headerActions) || $breadcrumbs || filled($heading) || filled($subheading))
        <x-filament-panels::header
            :actions="$headerActions"
            :actions-alignment="$headerActionsAlignment"
            :breadcrumbs="$breadcrumbs"
            :heading="$heading"
            :subheading="$subheading"
        >
            @if ($heading instanceof \Illuminate\Contracts\Support\Htmlable)
                <x-slot name="heading">
                    {{ $heading }}
                </x-slot>
            @endif

            @if ($subheading instanceof \Illuminate\Contracts\Support\Htmlable)
                <x-slot name="subheading">
                    {{ $subheading }}
                </x-slot>
            @endif
        </x-filament-panels::header>
    @endif

    @include('filament.app.pages.protection.edge-routing-warning')
</div>
