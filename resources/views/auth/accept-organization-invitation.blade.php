<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Join {{ $invitation->organization->name }} | FirePhage</title>
        <meta name="description" content="Accept your FirePhage organization invitation and finish setting up your account.">
        <meta name="theme-color" content="#0b1020">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#0a1020] text-slate-100 antialiased">
        <div class="relative isolate min-h-screen overflow-hidden">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.18),transparent_28%),radial-gradient(circle_at_80%_15%,rgba(124,58,237,0.18),transparent_24%),linear-gradient(180deg,#08101f_0%,#0b1326_55%,#08111c_100%)]"></div>

            <div class="mx-auto grid min-h-screen max-w-7xl grid-cols-1 lg:grid-cols-[1.05fr_0.95fr]">
                <section class="flex flex-col justify-between px-6 py-10 sm:px-10 lg:px-12 lg:py-12">
                    <div>
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-sm font-medium text-cyan-200 hover:text-cyan-100">
                            <img src="{{ asset('images/logo-shield-phage-wordmark.svg') }}" alt="FirePhage" class="h-8 w-auto">
                        </a>

                        <div class="mt-16 max-w-2xl">
                            <p class="inline-flex rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-cyan-200">
                                Team Invitation
                            </p>
                            <h1 class="mt-6 max-w-xl text-4xl font-semibold tracking-tight text-white sm:text-5xl">
                                Join {{ $invitation->organization->name }} on FirePhage.
                            </h1>
                            <p class="mt-5 max-w-xl text-base leading-7 text-slate-300">
                                Finish your account setup, choose a password, and you will land directly inside the organization dashboard.
                            </p>
                        </div>

                        <div class="mt-10 rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur max-w-2xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200">Invitation details</p>
                            <div class="mt-4 space-y-2 text-sm text-slate-300">
                                <p><span class="text-slate-400">Organization:</span> {{ $invitation->organization->name }}</p>
                                <p><span class="text-slate-400">Invited email:</span> {{ $invitation->email }}</p>
                                <p><span class="text-slate-400">Access level:</span> {{ ucfirst((string) $invitation->role) }}</p>
                                <p><span class="text-slate-400">Expires:</span> {{ $invitation->expires_at?->toDayDateTimeString() ?? 'n/a' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-12 max-w-2xl text-sm text-slate-400">
                        Already have an account for this email?
                        <a href="{{ url('/app/login') }}" class="font-medium text-cyan-200 hover:text-cyan-100">Sign in instead</a>
                    </div>
                </section>

                <section class="flex items-center px-6 py-10 sm:px-10 lg:px-12 lg:py-12">
                    <div class="w-full rounded-[2rem] border border-white/10 bg-slate-950/60 p-6 shadow-2xl shadow-cyan-950/20 backdrop-blur sm:p-8">
                        <div class="mb-8">
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-200">Accept Invitation</p>
                            <h2 class="mt-3 text-2xl font-semibold text-white">Set up your account</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-400">Use the invited email and define a password so you can access the organization immediately.</p>
                        </div>

                        @if (session('status'))
                            <div class="mb-6 rounded-2xl border border-cyan-400/20 bg-cyan-500/10 p-4 text-sm text-cyan-100">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if ($existingUser)
                            <div class="rounded-2xl border border-amber-400/20 bg-amber-500/10 p-4 text-sm text-amber-100">
                                An account already exists for {{ $invitation->email }}.
                                <a href="{{ url('/app/login') }}" class="font-semibold underline underline-offset-2">Sign in to accept the invitation</a>.
                            </div>
                        @else
                            <form method="POST" action="{{ route('app.invitations.accept.setup.store', ['token' => $invitation->token]) }}" class="space-y-5">
                                @csrf

                                <div>
                                    <label for="name" class="mb-2 block text-sm font-medium text-slate-200">Full name</label>
                                    <input
                                        id="name"
                                        name="name"
                                        type="text"
                                        value="{{ old('name') }}"
                                        required
                                        autofocus
                                        autocomplete="name"
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none ring-0 placeholder:text-slate-500 focus:border-cyan-300"
                                        placeholder="Jane Teammate"
                                    >
                                    @error('name')
                                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="email" class="mb-2 block text-sm font-medium text-slate-200">Invited email</label>
                                    <input
                                        id="email"
                                        type="email"
                                        value="{{ $invitation->email }}"
                                        readonly
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300 outline-none"
                                    >
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <div class="mb-2 flex items-center justify-between gap-3">
                                            <label for="password" class="block text-sm font-medium text-slate-200">Password</label>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    id="toggle-password-visibility"
                                                    class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-slate-300 transition hover:border-cyan-300/60 hover:text-white"
                                                >
                                                    Show
                                                </button>
                                                <button
                                                    type="button"
                                                    id="generate-password"
                                                    class="inline-flex items-center rounded-full border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-xs font-semibold text-cyan-200 transition hover:border-cyan-300/60 hover:text-white"
                                                >
                                                    Generate
                                                </button>
                                            </div>
                                        </div>
                                        <input
                                            id="password"
                                            name="password"
                                            type="password"
                                            required
                                            autocomplete="new-password"
                                            class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none ring-0 placeholder:text-slate-500 focus:border-cyan-300"
                                            placeholder="Create a strong password"
                                        >
                                        @error('password')
                                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <div class="mb-2 flex items-center justify-between gap-3">
                                            <label for="password_confirmation" class="block text-sm font-medium text-slate-200">Confirm password</label>
                                            <button
                                                type="button"
                                                id="toggle-password-confirmation-visibility"
                                                class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-slate-300 transition hover:border-cyan-300/60 hover:text-white"
                                            >
                                                Show
                                            </button>
                                        </div>
                                        <input
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            type="password"
                                            required
                                            autocomplete="new-password"
                                            class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none ring-0 placeholder:text-slate-500 focus:border-cyan-300"
                                            placeholder="Repeat password"
                                        >
                                    </div>
                                </div>

                                <p class="-mt-2 text-xs text-slate-400">Use at least 12 characters with uppercase, lowercase, a number, and a symbol.</p>

                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300">
                                    Join organization
                                </button>
                            </form>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        <script>
            (() => {
                const trigger = document.getElementById('generate-password');
                const password = document.getElementById('password');
                const confirmation = document.getElementById('password_confirmation');
                const togglePassword = document.getElementById('toggle-password-visibility');
                const toggleConfirmation = document.getElementById('toggle-password-confirmation-visibility');

                if (! password || ! confirmation) {
                    return;
                }

                const bindVisibilityToggle = (button, field) => {
                    if (! button || ! field) {
                        return;
                    }

                    button.addEventListener('click', () => {
                        const nextType = field.type === 'password' ? 'text' : 'password';
                        field.type = nextType;
                        button.textContent = nextType === 'password' ? 'Show' : 'Hide';
                    });
                };

                bindVisibilityToggle(togglePassword, password);
                bindVisibilityToggle(toggleConfirmation, confirmation);

                if (! trigger || ! window.crypto?.getRandomValues) {
                    return;
                }

                const uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
                const lowercase = 'abcdefghijkmnopqrstuvwxyz';
                const numbers = '23456789';
                const symbols = '!@#$%^&*()-_=+[]{}?';
                const all = uppercase + lowercase + numbers + symbols;

                const randomChar = (chars) => {
                    const bytes = new Uint32Array(1);
                    window.crypto.getRandomValues(bytes);

                    return chars[bytes[0] % chars.length];
                };

                const shuffle = (chars) => {
                    for (let index = chars.length - 1; index > 0; index -= 1) {
                        const bytes = new Uint32Array(1);
                        window.crypto.getRandomValues(bytes);
                        const swapIndex = bytes[0] % (index + 1);
                        [chars[index], chars[swapIndex]] = [chars[swapIndex], chars[index]];
                    }

                    return chars;
                };

                trigger.addEventListener('click', () => {
                    const generated = shuffle([
                        randomChar(uppercase),
                        randomChar(lowercase),
                        randomChar(numbers),
                        randomChar(symbols),
                        ...Array.from({ length: 12 }, () => randomChar(all)),
                    ]).join('');

                    password.value = generated;
                    confirmation.value = generated;
                    password.type = 'text';
                    confirmation.type = 'text';

                    if (togglePassword) {
                        togglePassword.textContent = 'Hide';
                    }

                    if (toggleConfirmation) {
                        toggleConfirmation.textContent = 'Hide';
                    }

                    password.focus();
                    password.select();
                });
            })();
        </script>
    </body>
</html>
