@props(['text'])

<div
    x-data="{
        open: false,
        placement: 'center',
        updatePlacement() {
            this.$nextTick(() => {
                const tooltip = this.$refs.tooltip;
                const trigger = this.$refs.trigger;

                if (! tooltip || ! trigger) {
                    return;
                }

                tooltip.style.visibility = 'hidden';
                tooltip.style.display = 'block';

                const rect = tooltip.getBoundingClientRect();
                const triggerRect = trigger.getBoundingClientRect();
                const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
                const gutter = 16;

                if (rect.right > viewportWidth - gutter) {
                    this.placement = 'right';
                } else if (rect.left < gutter) {
                    this.placement = 'left';
                } else {
                    const triggerCenter = triggerRect.left + (triggerRect.width / 2);

                    if (triggerCenter > viewportWidth - 220) {
                        this.placement = 'right';
                    } else if (triggerCenter < 220) {
                        this.placement = 'left';
                    } else {
                        this.placement = 'center';
                    }
                }

                tooltip.style.display = '';
                tooltip.style.visibility = '';
            });
        },
    }"
    class="relative inline-flex shrink-0"
    @mouseenter="open = true; updatePlacement()"
    @mouseleave="open = false"
>
    <button
        x-ref="trigger"
        type="button"
        class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-gray-800 bg-gray-900 text-white shadow-sm transition hover:border-primary-500 hover:bg-primary-600 focus:border-primary-500 focus:bg-primary-600 dark:border-gray-100 dark:bg-white dark:text-gray-900 dark:hover:border-primary-300 dark:hover:bg-primary-200 dark:focus:border-primary-300 dark:focus:bg-primary-200"
        @focus="open = true; updatePlacement()"
        @blur="open = false"
        @click.prevent="open = ! open; if (open) updatePlacement()"
        aria-label="More information"
    >
        <x-filament::icon icon="heroicon-m-question-mark-circle" class="h-5 w-5" />
    </button>

    <div
        x-ref="tooltip"
        x-cloak
        x-show="open"
        x-transition.opacity.duration.150ms
        @click.outside="open = false"
        :class="{
            'left-1/2 -translate-x-1/2': placement === 'center',
            'left-0 translate-x-0': placement === 'left',
            'right-0 left-auto translate-x-0': placement === 'right',
        }"
        class="absolute top-full z-[120] mt-2 w-96 max-w-[min(28rem,calc(100vw-2rem))] rounded-xl border border-gray-800 bg-gray-950 px-4 py-3 text-sm font-normal leading-6 text-gray-100 shadow-2xl shadow-gray-950/30 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
    >
        {{ $text }}
    </div>
</div>
