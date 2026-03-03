<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contact FirePhage</title>
        <meta name="description" content="Contact FirePhage for Business plan onboarding and support.">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
        <main class="mx-auto w-full max-w-4xl px-6 py-16 lg:px-8">
            <a href="{{ url('/') }}" class="text-sm text-cyan-300 hover:text-cyan-200">&larr; Back to homepage</a>
            <h1 class="mt-6 text-4xl font-semibold text-white">Contact Sales</h1>
            <p class="mt-4 text-sm leading-7 text-slate-300">For Business plan questions and assisted onboarding, email us at <a href="mailto:contact@firephage.com" class="text-cyan-300 hover:text-cyan-200">contact@firephage.com</a>.</p>
        </main>
    </body>
</html>
