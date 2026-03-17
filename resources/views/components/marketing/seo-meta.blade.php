@props([
    'title',
    'description',
    'canonical' => null,
    'ogType' => 'website',
    'ogTitle' => null,
    'ogDescription' => null,
    'ogUrl' => null,
    'ogImage' => null,
    'robots' => 'index,follow',
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
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $ogTitle ?: $title }}">
<meta name="twitter:description" content="{{ $ogDescription ?: $description }}">
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif
<meta name="theme-color" content="#030712">
<link rel="canonical" href="{{ $canonical ?: request()->url() }}">

@foreach ($structuredData as $schema)
    @if (! empty($schema))
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endforeach
