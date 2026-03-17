@props(['text'])

<div
    x-data="{ open: false }"
    class="relative inline-flex shrink-0"
    @mouseenter="open = true"
    @mouseleave="open = false"
>
    <button
        type="button"
        class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-gray-800 bg-gray-900 text-white shadow-sm transition hover:border-primary-500 hover:bg-primary-600 focus:border-primary-500 focus:bg-primary-600 dark:border-gray-100 dark:bg-white dark:text-gray-900 dark:hover:border-primary-300 dark:hover:bg-primary-200 dark:focus:border-primary-300 dark:focus:bg-primary-200"
        @focus="open = true"
        @blur="open = false"
        @click.prevent="open = ! open"
        aria-label="More information"
    >
        <x-filament::icon icon="heroicon-m-question-mark-circle" class="h-5 w-5" />
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity.duration.150ms
        @click.outside="open = false"
        class="absolute left-1/2 top-full z-[120] mt-2 w-96 max-w-[28rem] -translate-x-1/2 rounded-xl border border-gray-800 bg-gray-950 px-4 py-3 text-sm font-normal leading-6 text-gray-100 shadow-2xl shadow-gray-950/30 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
    >
        {{ $text }}
    </div>
</div>
