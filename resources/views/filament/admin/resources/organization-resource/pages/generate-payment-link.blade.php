<x-filament-panels::page>
    <form wire:submit="generate" class="space-y-6">
        <x-filament::section
            heading="Create Stripe payment link"
            description="Pick the paid plan for this account, generate the Stripe link, and send it directly to the customer. After payment, later site onboarding can continue without the usual checkout step."
            icon="heroicon-o-link"
        >
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit" icon="heroicon-m-link">
                    Generate link
                </x-filament::button>
            </div>
        </x-filament::section>
    </form>

    @if ($this->generatedUrl)
        <x-filament::section
            heading="Payment link"
            description="Send this Stripe link to the customer. The subscription is attached to this account, so onboarding can continue later without repeating payment."
            icon="heroicon-o-credit-card"
        >
            <div class="space-y-4">
                <label class="block text-sm font-medium text-gray-950 dark:text-white">
                    Stripe checkout URL
                </label>
                <input
                    type="text"
                    readonly
                    value="{{ $this->generatedUrl }}"
                    onclick="this.select()"
                    class="fi-input block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none dark:border-white/10 dark:bg-white/5 dark:text-white"
                >

                <div class="flex flex-wrap gap-3">
                    <x-filament::button
                        type="button"
                        color="gray"
                        icon="heroicon-m-clipboard"
                        x-on:click="navigator.clipboard.writeText(@js($this->generatedUrl))"
                    >
                        Copy link
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="$this->generatedUrl"
                        target="_blank"
                        icon="heroicon-m-arrow-top-right-on-square"
                    >
                        Open Stripe checkout
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
