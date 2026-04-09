<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-marketing.seo-meta
            title="About FirePhage | Built by a Decade of WordPress Security Experience"
            description="Meet the founder of FirePhage. Nearly 10 years building WAF and cloud security systems, 140+ completed security projects, 100% Upwork Job Success Score."
            :canonical="route('about')"
            :og-url="route('about')"
            :structured-data="[
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'AboutPage',
                    'name' => 'About FirePhage',
                    'url' => route('about'),
                    'description' => 'Background, track record, and founder story behind FirePhage.',
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Person',
                    'name' => 'Nikola Jocic',
                    'jobTitle' => 'Founder of FirePhage',
                    'image' => route('about.founder-photo'),
                    'worksFor' => [
                        '@type' => 'Organization',
                        'name' => 'Dialbotics LLC',
                    ],
                ],
            ]"
        />
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/marketing.css', 'resources/js/marketing.js'])
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(34,211,238,0.16),transparent_34%),linear-gradient(180deg,rgba(2,8,23,0.96),rgba(2,6,23,1))]"></div>
                <div class="absolute left-1/2 top-20 h-80 w-80 -translate-x-1/2 rounded-full bg-cyan-500/10 blur-3xl"></div>
            </div>

            <div class="relative z-10">
                <x-marketing.site-header />

                <main>
                    <section class="mx-auto w-full max-w-7xl px-6 pb-8 pt-10 lg:px-8 lg:pb-10 lg:pt-16">
                        <div class="rounded-[2rem] border border-white/10 bg-slate-900/78 p-8 shadow-[0_30px_80px_rgba(2,8,23,0.55)] backdrop-blur lg:p-12">
                            <p class="inline-flex rounded-full border border-cyan-400/25 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">About FirePhage</p>
                            <h1 class="mt-6 max-w-5xl text-balance text-4xl font-semibold leading-tight text-white lg:text-6xl">Built by someone who has seen what happens when WordPress security goes wrong</h1>
                            <p class="mt-6 max-w-4xl text-lg leading-8 text-slate-300">FirePhage exists because the problem is real and the existing solutions are either too complex, too noisy, or built for infrastructure teams - not the agencies and store owners who actually need protection. I built FirePhage to fix that.</p>
                        </div>
                    </section>

                    <section class="mx-auto w-full max-w-7xl px-6 py-6 lg:px-8 lg:py-8">
                        <div class="grid gap-8 rounded-[2rem] border border-white/10 bg-slate-900/75 p-6 shadow-[0_24px_70px_rgba(2,8,23,0.42)] lg:grid-cols-[200px_minmax(0,1fr)] lg:items-center lg:gap-10 lg:p-8">
                            <div class="mx-auto w-[120px] lg:mx-0 lg:w-[180px]">
                                <div class="overflow-hidden rounded-full border border-cyan-400/20 bg-slate-950/80 shadow-[0_24px_60px_rgba(2,8,23,0.4)]">
                                    <img
                                        src="{{ route('about.founder-photo') }}"
                                        alt="Nikola Jocic - Founder of FirePhage"
                                        class="h-[120px] w-[120px] object-cover [object-position:center_top] lg:h-[180px] lg:w-[180px]"
                                        loading="eager"
                                        decoding="async"
                                    >
                                </div>
                                <div class="mt-4 text-center lg:text-left">
                                    <p class="text-sm font-semibold text-white">Nikola Jocic</p>
                                    <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-400">Founder, FirePhage</p>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Who is behind FirePhage</p>
                                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white lg:text-4xl">Who is behind FirePhage</h2>

                                <div class="mt-6 space-y-5 text-base leading-8 text-slate-300">
                                    <p>My name is Nikola Jocic. I'm a Senior Cyber Security Developer, and I've spent the last decade working on exactly the kind of problems FirePhage is built to solve.</p>

                                    <p>From 2016 onwards I worked as a Senior Cyber Security Developer at a cloud-based WAF and website security platform, where I was part of the team building and maintaining firewall systems, malware cleanup tooling, and cloud security infrastructure for websites across four continents. That experience gave me a deep understanding of how hostile traffic actually behaves, what WordPress sites are consistently vulnerable to, and where most security products fail their operators.</p>

                                    <p>Before that I spent nearly five years as a Network Administrator, which means I approach security from both the application and infrastructure layer - not just one side of the stack.</p>

                                    <p>I'm also a Palo Alto Networks Accredited Systems Engineer (certified through 2027), with additional certifications in firewall and threat defense.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="mx-auto w-full max-w-7xl px-6 py-8 lg:px-8 lg:py-10">
                        <div class="rounded-[2rem] border border-white/10 bg-slate-900/75 p-6 shadow-[0_24px_70px_rgba(2,8,23,0.42)] lg:p-8">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Track record</p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white lg:text-4xl">A track record you can verify</h2>
                            <p class="mt-5 max-w-3xl text-base leading-8 text-slate-300">Before building FirePhage I spent years taking on WordPress and WooCommerce security work independently. That work is publicly verifiable.</p>

                            <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach ([
                                    ['value' => '100%', 'label' => 'Job Success Score on Upwork'],
                                    ['value' => 'Top Rated Plus', 'label' => "Upwork's highest freelancer tier"],
                                    ['value' => '140+', 'label' => 'Completed security projects'],
                                    ['value' => '10 years', 'label' => 'In web security'],
                                ] as $stat)
                                    <article class="rounded-[1.5rem] border border-white/10 bg-slate-950/55 p-5">
                                        <p class="text-3xl font-semibold tracking-tight text-white">{{ $stat['value'] }}</p>
                                        <p class="mt-3 text-sm leading-6 text-slate-300">{{ $stat['label'] }}</p>
                                    </article>
                                @endforeach
                            </div>

                            <div class="mt-8 grid gap-4 xl:grid-cols-2">
                                @foreach ([
                                    'Nikola FAR exceeded my imagination. I will absolutely be back anytime I need help with security related tasks.',
                                    'Absolutely amazing — fixed my crashed site in 10 minutes when no one else could. I would give 100 stars if I could.',
                                    'Nikola is one of those rare contractors you find who handles the job PERFECTLY. I had complete confidence in his work.',
                                    'Knowledge, skill and expertise is second to none. Nikola pointed out several extra steps we could take along the way.',
                                ] as $quote)
                                    <blockquote class="rounded-[1.5rem] border border-white/10 bg-slate-950/55 p-6">
                                        <p class="text-base leading-8 text-slate-200">“{{ $quote }}”</p>
                                        <footer class="mt-5 text-sm font-medium text-cyan-200">Verified Upwork Client</footer>
                                    </blockquote>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    <section class="mx-auto w-full max-w-7xl px-6 py-8 lg:px-8 lg:py-10">
                        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(280px,0.85fr)]">
                            <article class="rounded-[2rem] border border-white/10 bg-slate-900/75 p-6 shadow-[0_24px_70px_rgba(2,8,23,0.42)] lg:p-8">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Why FirePhage</p>
                                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white lg:text-4xl">Why FirePhage and why now</h2>

                                <div class="mt-6 space-y-5 text-base leading-8 text-slate-300">
                                    <p>After years of doing this work manually for individual clients, the pattern was always the same:</p>

                                    <ul class="space-y-3 pl-5 text-slate-300 marker:text-cyan-300 list-disc">
                                        <li>Sites were getting hit by predictable, repeatable attacks</li>
                                        <li>Existing tools were either too plugin-heavy, too complex to operate, or required teams to piece together five different dashboards to understand what was happening</li>
                                        <li>WooCommerce stores in particular were losing real money to bot pressure, fake orders, and login abuse — often without realising it until the damage was done</li>
                                    </ul>

                                    <p>FirePhage is the product I wished existed when I was working on those sites. Edge-first protection, a human-readable dashboard, guided DNS onboarding, and workflows built specifically around how WordPress and WooCommerce sites actually get attacked.</p>

                                    <p>It's not built for security researchers. It's built for the agency owner managing 12 client sites, and the WooCommerce store operator who just wants checkout to stay clean.</p>
                                </div>
                            </article>

                            <article class="rounded-[2rem] border border-cyan-400/18 bg-cyan-500/[0.07] p-6 shadow-[0_24px_70px_rgba(2,8,23,0.32)] lg:p-8">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Infrastructure note</p>
                                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white">Built on solid infrastructure, focused on the right layer</h2>
                                <p class="mt-6 text-base leading-8 text-slate-200">FirePhage runs on enterprise-grade global edge infrastructure, placing protection in front of your origin server — not inside WordPress itself. Every plan includes a 30-day free trial with full protection for one site.</p>
                                <p class="mt-5 text-base leading-8 text-slate-200">If you want to talk through your specific situation before signing up, feel free to <a href="{{ route('contact') }}" class="font-semibold text-cyan-200 hover:text-white">get in touch</a>.</p>
                            </article>
                        </div>
                    </section>

                    <section class="mx-auto w-full max-w-7xl px-6 pb-20 pt-8 lg:px-8 lg:pb-24 lg:pt-10">
                        <div class="rounded-[2rem] border border-white/10 bg-slate-900/75 p-8 text-center shadow-[0_24px_70px_rgba(2,8,23,0.42)] lg:p-12">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Start here</p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white lg:text-4xl">See how FirePhage fits your site before you commit anything permanent.</h2>
                            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-xl bg-cyan-500 px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400">Start your free trial</a>
                                <a href="{{ route('services.index') }}" class="inline-flex items-center justify-center rounded-xl border border-white/12 px-6 py-3 text-sm font-semibold text-slate-100 transition hover:border-cyan-300/60 hover:text-white">View FirePhage services</a>
                            </div>
                        </div>
                    </section>
                </main>

                <x-marketing.footer-variant-1 />
            </div>
        </div>
    </body>
</html>
