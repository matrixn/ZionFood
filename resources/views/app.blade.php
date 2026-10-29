<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title data-inertia="">{{ $landingMeta['title'] ?? $storefrontMeta['title'] ?? 'ZionFood' }}</title>
@isset($landingMeta)
@include('marketing.head', ['meta' => $landingMeta])
@include('marketing.first-paint')
@endisset
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
<meta property="og:image:width" content="600" data-storefront-meta>
<meta property="og:image:height" content="600" data-storefront-meta>
<meta property="og:image:type" content="image/png" data-storefront-meta>
<meta property="og:image:alt" content="{{ $storefrontMeta['name'] }}" data-storefront-meta>
<meta name="twitter:image" content="{{ $storefrontMeta['image'] }}" data-storefront-meta>
@endif
@endisset
@foreach([16, 32, 48, 96, 256] as $iconSize)
<link rel="icon" type="image/png" sizes="{{ $iconSize }}x{{ $iconSize }}" href="/images/logo/food.zion3d.ro_icons-{{ $iconSize }}x{{ $iconSize }}.png">
@endforeach
<link rel="apple-touch-icon" sizes="256x256" href="/images/logo/food.zion3d.ro_icons-256x256.png">
@vite('resources/js/app.ts')@inertiaHead</head><body>@inertia</body></html>
