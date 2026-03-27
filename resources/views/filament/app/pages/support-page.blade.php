<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    <style>
        .fp-support-surface .bg-white,
        .fp-support-surface [class*="bg-gray-50"],
        .fp-support-surface [class*="bg-slate-50"] {
            background: inherit;
        }

        .dark .fp-support-surface {
            color: rgb(226 232 240);
        }

        .dark .fp-support-surface > div,
        .dark .fp-support-surface .shadow-sm,
        .dark .fp-support-surface .shadow-xl {
            box-shadow: none;
        }

        .dark .fp-support-surface .bg-white,
        .dark .fp-support-surface [class*="bg-gray-50"],
        .dark .fp-support-surface [class*="bg-slate-50"] {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.92), rgba(15, 23, 42, 0.82)) !important;
        }

        .dark .fp-support-surface .border-gray-200,
        .dark .fp-support-surface .border-gray-100,
        .dark .fp-support-surface [class*="border-gray-"] {
            border-color: rgba(96, 165, 250, 0.16) !important;
        }

        .dark .fp-support-surface .text-gray-900,
        .dark .fp-support-surface .text-gray-700 {
            color: rgb(248 250 252) !important;
        }

        .dark .fp-support-surface .text-gray-600,
        .dark .fp-support-surface .text-gray-500,
        .dark .fp-support-surface .text-gray-400 {
            color: rgb(203 213 225) !important;
        }
    </style>

    <div class="fp-protection-shell space-y-6">
        <div class="max-w-3xl space-y-2">
            <h2 class="text-xl font-semibold text-gray-950 dark:text-slate-100">Support</h2>
            <p class="text-sm text-gray-600 dark:text-slate-300">
                Open a new ticket, review your current conversations, and track resolved issues for your account.
            </p>
        </div>

        <div class="fp-support-surface overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-sky-400/15 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))] dark:shadow-[0_24px_50px_rgba(2,6,23,0.34)]">
            @livewire('creators-ticketing.ticket-submit-form')
        </div>
    </div>
</x-filament-panels::page>
