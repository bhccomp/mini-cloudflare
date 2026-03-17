@vite(['resources/css/app.css', 'resources/js/app.js'])

@php
    $host = strtolower((string) request()->getHost());
    $demoHost = strtolower((string) config('demo.host', 'demo.firephage.com'));
@endphp

@if ($host === $demoHost)
    <script>
        document.documentElement.setAttribute('data-firephage-light-palette', 'mist-navy');
    </script>
@endif
