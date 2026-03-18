@vite(['resources/css/app.css', 'resources/js/app.js'])

@php
    $host = strtolower((string) request()->getHost());
    $demoHost = strtolower((string) config('demo.host', 'demo.firephage.com'));
@endphp

@if ($host === $demoHost)
    <script>
        (() => {
            const aliases = {
                'mist-navy': 'slate-electric-cyan',
                'stone-cyan': 'warm-infra-stone',
            };
            const allowed = ['frost-ice-infra', 'slate-electric-cyan', 'warm-infra-stone'];
            const params = new URLSearchParams(window.location.search);
            const requested = aliases[params.get('palette')] ?? params.get('palette');

            if (allowed.includes(requested)) {
                localStorage.setItem('firephage-demo-light-palette', requested);
            }

            const stored = localStorage.getItem('firephage-demo-light-palette');
            const active = aliases[stored] ?? stored ?? 'frost-ice-infra';

            if (allowed.includes(active)) {
                document.documentElement.setAttribute('data-firephage-light-palette', active);
            }
        })();
    </script>
@endif
