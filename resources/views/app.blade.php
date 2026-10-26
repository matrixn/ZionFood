<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title inertia>{{ $storefrontMeta['title'] ?? 'ZionFood' }}</title>
@isset($storefrontMeta)
<meta name="description" content="{{ $storefrontMeta['description'] }}" data-storefront-meta>
<meta property="og:title" content="{{ $storefrontMeta['title'] }}" data-storefront-meta>
<meta property="og:description" content="{{ $storefrontMeta['description'] }}" data-storefront-meta>
<meta property="og:site_name" content="{{ $storefrontMeta['name'] }}" data-storefront-meta>
<meta property="og:type" content="website" data-storefront-meta>
<meta property="og:url" content="{{ $storefrontMeta['url'] }}" data-storefront-meta>
<meta property="og:locale" content="{{ $storefrontMeta['locale'] }}" data-storefront-meta>
<link rel="canonical" href="{{ $storefrontMeta['url'] }}" data-storefront-meta>
<meta name="twitter:card" content="summary" data-storefront-meta>
<meta name="twitter:title" content="{{ $storefrontMeta['title'] }}" data-storefront-meta>
@if($storefrontMeta['image'])
<meta property="og:image" content="{{ $storefrontMeta['image'] }}" data-storefront-meta>
<meta property="og:image:alt" content="{{ $storefrontMeta['name'] }}" data-storefront-meta>
<meta name="twitter:image" content="{{ $storefrontMeta['image'] }}" data-storefront-meta>
@endif
@endisset
@vite('resources/js/app.ts')@inertiaHead</head><body>@inertia</body></html>
