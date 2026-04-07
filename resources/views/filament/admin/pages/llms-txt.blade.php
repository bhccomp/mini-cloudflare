<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <x-filament::section>
                <div class="space-y-2 text-sm text-gray-300">
                    <p>Public URL: <a href="{{ route('seo.llms') }}" target="_blank" class="text-cyan-300 hover:text-cyan-200">/llms.txt</a></p>
                    <p>The live file uses this saved template and automatically injects all currently published blog posts where <code>{{ \App\Services\Seo\LlmsTxtService::BLOG_PLACEHOLDER }}</code> appears.</p>
                </div>
            </x-filament::section>

            <x-filament::actions alignment="end">
                <x-filament::button color="gray" type="button" wire:click="resetToDefault" wire:loading.attr="disabled" wire:target="resetToDefault">
                    Load default template
                </x-filament::button>

                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">
                    Save llms.txt
                </x-filament::button>
            </x-filament::actions>
        </form>
    </div>
</x-filament-panels::page>
