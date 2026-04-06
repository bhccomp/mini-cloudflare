@php
    /** @var \App\Services\Auth\AdminImpersonationService $impersonation */
    $impersonation = app(\App\Services\Auth\AdminImpersonationService::class);
    $impersonator = $impersonation->impersonator();
@endphp

@if ($impersonation->isImpersonating() && $impersonator)
    <div class="mr-3 hidden md:flex items-center gap-3 rounded-xl border border-amber-300/40 bg-amber-400/10 px-3 py-2 text-xs text-amber-100">
        <span>Impersonating as {{ auth()->user()?->email }} from {{ $impersonator->email }}</span>
        <form method="POST" action="{{ route('admin.impersonation.stop') }}">
            @csrf
            <button type="submit" class="rounded-lg border border-amber-300/40 px-2 py-1 font-medium text-amber-50 transition hover:bg-amber-300/10">
                Return to Admin
            </button>
        </form>
    </div>
@endif
