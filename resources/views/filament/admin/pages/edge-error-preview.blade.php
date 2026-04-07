<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border p-5 shadow-sm" style="border-color: rgba(148, 163, 184, 0.18); background: rgba(15, 23, 42, 0.92);">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="space-y-2">
                    <h2 class="text-lg font-semibold" style="color: #f8fafc;">Preview draft</h2>
                    <p class="max-w-3xl text-sm" style="color: #cbd5e1;">
                        Edit the raw HTML that FirePhage would send to Bunny, then open it in a separate preview tab. This page does not republish anything to Bunny.
                    </p>
                </div>

                <form method="get" action="{{ \App\Filament\Admin\Pages\EdgeErrorPreviewPage::getUrl() }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label class="flex min-w-[220px] flex-col gap-1">
                        <span class="text-xs font-medium uppercase tracking-[0.18em]" style="color: #94a3b8;">Domain label</span>
                        <input
                            type="text"
                            name="domain"
                            value="{{ $this->domain }}"
                            class="rounded-xl px-3 py-2 text-sm shadow-sm outline-none transition"
                            style="border: 1px solid rgba(148, 163, 184, 0.22); background: #020617; color: #f8fafc;"
                        >
                    </label>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white transition"
                        style="background: linear-gradient(135deg, #0891b2 0%, #22d3ee 100%);"
                    >
                        Reload template
                    </button>
                </form>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($this->templates() as $template => $label)
                    @php
                        $active = $this->template === $template;
                    @endphp
                    <a
                        href="{{ \App\Filament\Admin\Pages\EdgeErrorPreviewPage::getUrl(['template' => $template, 'domain' => $this->domain]) }}"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition"
                        style="{{ $active
                            ? 'background: linear-gradient(135deg, #0891b2 0%, #22d3ee 100%); color: #ffffff;'
                            : 'border: 1px solid rgba(148, 163, 184, 0.22); background: #020617; color: #cbd5e1;' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <form method="post" action="{{ route('admin.edge-error-preview.render') }}" target="_blank" class="space-y-4">
            @csrf

            <div class="rounded-2xl border p-4 shadow-sm" style="border-color: rgba(148, 163, 184, 0.18); background: rgba(15, 23, 42, 0.92);">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-semibold" style="color: #f8fafc;">HTML source</h3>
                        <p class="text-xs" style="color: #cbd5e1;">Open the current draft in a new preview tab. Keep this editor page open while iterating.</p>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white transition"
                        style="background: linear-gradient(135deg, #0891b2 0%, #22d3ee 100%);"
                    >
                        Open preview
                    </button>
                </div>

                <textarea
                    name="html"
                    rows="28"
                    spellcheck="false"
                    class="w-full rounded-2xl px-4 py-4 font-mono text-[13px] leading-6 shadow-inner outline-none transition"
                    style="border: 1px solid rgba(148, 163, 184, 0.22); background: #020617; color: #cffafe; caret-color: #67e8f9;"
                >{{ $this->html }}</textarea>
            </div>
        </form>
    </div>
</x-filament-panels::page>
