<x-filament-panels::page>
    <div class="space-y-8">
        <form wire:submit="save" class="space-y-8">
            {{ $this->form }}

            <x-filament::section class="mt-2">
                <div class="space-y-2 text-sm text-gray-300">
                    <p>Public URL: <a href="{{ route('seo.sitemap') }}" target="_blank" class="text-cyan-300 hover:text-cyan-200">/sitemap.xml</a></p>
                    <p>FirePhage detects public URLs automatically and builds the sitemap from that live list plus published blog posts and service pages.</p>
                </div>
            </x-filament::section>

            <x-filament::actions alignment="end" class="pt-2">
                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">
                    Save sitemap settings
                </x-filament::button>
            </x-filament::actions>
        </form>
    </div>
</x-filament-panels::page>
