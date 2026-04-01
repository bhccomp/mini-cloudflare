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

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $robots }}">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $ogTitle ?: $title }}">
<meta property="og:description" content="{{ $ogDescription ?: $description }}">
<meta property="og:url" content="{{ $ogUrl ?: $canonical ?: request()->url() }}">
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
<link rel="canonical" href="{{ $canonical ?: request()->url() }}">

@foreach ($structuredData as $schema)
    @if (! empty($schema))
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endforeach
