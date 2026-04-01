@props([
    'title',
    'description',
    'canonical' => null,
    'ogType' => 'website',
    'ogTitle' => null,
    'ogDescription' => null,
    'ogUrl' => null,
    'ogImage' => asset('images/social/firephage-x-header.png'),
    'ogImageAlt' => 'FirePhage edge security for WordPress and WooCommerce',
    'robots' => 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1',
    'structuredData' => [],
])

@php
    use App\Support\MarketingSeo;

    $resolvedCanonical = MarketingSeo::preferredUrl($canonical ?: request()->url());
    $resolvedOgUrl = MarketingSeo::preferredUrl($ogUrl ?: $resolvedCanonical);
@endphp

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $robots }}">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $ogTitle ?: $title }}">
<meta property="og:description" content="{{ $ogDescription ?: $description }}">
<meta property="og:url" content="{{ $resolvedOgUrl }}">
<meta property="og:site_name" content="FirePhage">
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $ogTitle ?: $title }}">
<meta name="twitter:description" content="{{ $ogDescription ?: $description }}">
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1500">
    <meta property="og:image:height" content="500">
    <meta property="og:image:alt" content="{{ $ogImageAlt }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="twitter:image:alt" content="{{ $ogImageAlt }}">
@endif
<meta name="theme-color" content="#030712">
<link rel="canonical" href="{{ $resolvedCanonical }}">
@if (filled(config('marketing.google_analytics_measurement_id')))
    <meta name="firephage-ga-measurement-id" content="{{ config('marketing.google_analytics_measurement_id') }}">
    <script>
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function gtag() {
            window.dataLayer.push(arguments);
        };
        window.firephageGaMeasurementId = @js(config('marketing.google_analytics_measurement_id'));
        window.gtag('consent', 'default', {
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            analytics_storage: 'denied',
            wait_for_update: 500,
        });
        window.gtag('js', new Date());
        window.gtag('config', window.firephageGaMeasurementId);
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode(config('marketing.google_analytics_measurement_id')) }}"></script>
@endif

@foreach ($structuredData as $schema)
    @if (! empty($schema))
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endforeach
