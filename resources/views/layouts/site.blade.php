@php
    $siteName = 'Fidelity Southview Park Residents Association';
    $title = isset($title) ? $title.' | Southview Park Residents' : $siteName.' | Amalinda, Harare';
    $description = $description ?? 'Verify your stand, recover your Agreement of Sale, track your title deed and get official notices for Fidelity Southview Park, Amalinda, Harare.';
    $canonical = $canonical ?? url()->current();
    $ogImage = $ogImage ?? asset('images/og-default.jpg');
@endphp
<!DOCTYPE html>
<html lang="en-ZW" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    @if (!app()->isProduction() || ($noindex ?? false))<meta name="robots" content="noindex, nofollow">@else<meta name="robots" content="index, follow, max-image-preview:large">@endif
    <meta name="theme-color" content="#073320">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">

    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="en_ZW">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <link rel="preload" href="{{ Vite::asset('node_modules/@fontsource-variable/manrope/files/manrope-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/site.js'])
    @stack('head')

    <script type="application/ld+json" nonce="{{ app('csp-nonce') }}">{!! json_encode([
        '@context' => 'https://schema.org',
        '@graph' => array_values(array_filter([
            [
                '@type' => 'Organization', '@id' => url('/#org'), 'name' => $siteName, 'alternateName' => 'Southview Park Residents',
                'url' => url('/'), 'logo' => asset('images/logo-512.png'), 'slogan' => 'Stronger Together. A Better Community.',
                'areaServed' => ['@type' => 'Place', 'name' => 'Fidelity Southview Park, Amalinda, Harare, Zimbabwe'],
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Harare', 'addressRegion' => 'Harare', 'addressCountry' => 'ZW', 'streetAddress' => 'Southview Park, Amalinda'],
            ],
            ['@type' => 'WebSite', '@id' => url('/#site'), 'url' => url('/'), 'name' => $siteName, 'publisher' => ['@id' => url('/#org')], 'inLanguage' => 'en-ZW'],
            isset($breadcrumbs) ? ['@type' => 'BreadcrumbList', 'itemListElement' => collect($breadcrumbs)->values()->map(fn ($b, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $b[0], 'item' => $b[1]])->all()] : null,
        ])),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @stack('jsonld')
</head>
<body class="min-h-dvh flex flex-col" x-data="page">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[100] btn btn-gold">Skip to content</a>

    @include('partials.header')

    <main id="main" class="flex-1" tabindex="-1">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.toasts')
    @include('partials.assistant-launcher')
</body>
</html>
