<section id="dashboard-preview" class="border-y border-slate-800/80 bg-slate-900/40">
    <div class="mx-auto grid w-full max-w-7xl gap-8 px-6 py-16 lg:grid-cols-12 lg:px-8 lg:py-20">
        <div class="order-2 lg:order-1 lg:col-span-5">
            <h2 class="text-3xl font-semibold text-white">See What You'll Control</h2>
            <p class="mt-3 text-sm text-slate-300">A single dashboard for WAF rules, origin security, and monitoring.</p>
            <ul class="mt-6 space-y-3 text-sm text-slate-200">
                <li>Block by country, IP, CIDR</li>
                <li>Rate limiting presets</li>
                <li>Origin exposure status &amp; policy</li>
                <li>Uptime + error spikes (5xx)</li>
            </ul>
        </div>

        <div class="order-1 lg:order-2 lg:col-span-7">
            <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-3 shadow-[0_14px_45px_rgba(2,6,23,0.38)] sm:p-4">
                <button
                    type="button"
                    data-dashboard-preview-open
                    class="w-full overflow-hidden rounded-xl border border-slate-800/90 bg-slate-900 text-left transition hover:border-slate-700"
                    aria-haspopup="dialog"
                    aria-controls="dashboard-preview-modal"
                >
                    <picture>
                        <source srcset="{{ asset('images/dashboard-preview.webp') }}" type="image/webp">
                        <img
                            src="{{ asset('images/dashboard-preview.png') }}"
                            alt="FirePhage Security dashboard preview"
                            width="1901"
                            height="959"
                            loading="lazy"
                            decoding="async"
                            class="h-auto w-full object-cover"
                        >
                    </picture>
                </button>

                <div class="mt-4 px-1">
                    <p class="text-sm font-semibold text-white">Live dashboard preview</p>
                    <p class="mt-1 text-xs text-slate-400">Threat summary, traffic map, top countries, and top IPs in one view.</p>
                </div>
            </div>
        </div>
    </div>

    <div
        id="dashboard-preview-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/85 p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Dashboard preview zoom"
        data-dashboard-preview-modal
    >
        <div class="relative w-full max-w-6xl" data-dashboard-preview-panel>
            <button
                type="button"
                class="absolute right-2 top-2 rounded-md border border-slate-700 bg-slate-900/80 px-3 py-1 text-sm text-white hover:bg-slate-800"
                data-dashboard-preview-close
                aria-label="Close preview"
            >
                Close
            </button>

            <div class="overflow-hidden rounded-xl border border-white/10 bg-slate-900 shadow-2xl">
                <picture>
                    <source srcset="{{ asset('images/dashboard-preview.webp') }}" type="image/webp">
                    <img
                        src="{{ asset('images/dashboard-preview.png') }}"
                        alt="FirePhage Security dashboard preview enlarged"
                        width="1901"
                        height="959"
                        decoding="async"
                        class="h-auto w-full object-contain"
                    >
                </picture>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const openButton = document.querySelector('[data-dashboard-preview-open]');
            const modal = document.querySelector('[data-dashboard-preview-modal]');
            const panel = document.querySelector('[data-dashboard-preview-panel]');
            const closeButton = document.querySelector('[data-dashboard-preview-close]');

            if (!openButton || !modal || !panel || !closeButton) {
                return;
            }

            const openModal = () => {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.style.overflow = 'hidden';
                closeButton.focus();
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
                openButton.focus();
            };

            openButton.addEventListener('click', openModal);
            closeButton.addEventListener('click', closeModal);

            modal.addEventListener('click', (event) => {
                if (!panel.contains(event.target)) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        })();
    </script>
</section>
