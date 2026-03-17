<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <div class="fp-protection-shell space-y-6">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @if ($this->isSimpleMode())
                <x-filament.app.simple-security-snapshot
                    :items="$this->simpleSecuritySnapshot()"
                    :recommendation="$this->simpleSecurityRecommendation()"
                    :show-pro-button="true"
                />
            @endif
        @endif
    </div>
</x-filament-panels::page>
