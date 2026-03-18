<x-filament-panels::page>
    <div class="space-y-6">
        <div class="max-w-3xl space-y-2">
            <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Support</h2>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Open a new ticket, review your current conversations, and track resolved issues for your account.
            </p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950">
            @livewire('creators-ticketing.ticket-submit-form')
        </div>
    </div>
</x-filament-panels::page>
