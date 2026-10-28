<meta name="description" content="{{ $meta['description'] }}" data-inertia="description">
<meta name="robots" content="{{ $meta['robots'] }}" data-inertia="robots">
<link rel="canonical" href="{{ $meta['url'] }}" data-inertia="canonical">
@foreach($meta['alternates'] as $locale => $url)
<link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}" data-inertia="alternate-{{ $locale }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $meta['alternates']['ro'] }}" data-inertia="alternate-default">
@foreach(['title', 'description', 'url', 'locale', 'image'] as $field)
<meta property="og:{{ $field }}" content="{{ $meta[$field] }}" data-inertia="og-{{ $field }}">
@endforeach
<meta property="og:type" content="website" data-inertia="og-type">
<meta property="og:site_name" content="ZionFood" data-inertia="og-site">
<meta property="og:image:width" content="1440" data-inertia="og-width">
<meta property="og:image:height" content="1080" data-inertia="og-height">
<meta property="og:image:type" content="image/webp" data-inertia="og-image-type">
<meta property="og:image:alt" content="{{ $meta['image_alt'] }}" data-inertia="og-alt">
<meta name="twitter:card" content="summary_large_image" data-inertia="twitter-card">
<meta name="twitter:title" content="{{ $meta['title'] }}" data-inertia="twitter-title">
<meta name="twitter:description" content="{{ $meta['description'] }}" data-inertia="twitter-description">
<meta name="twitter:image" content="{{ $meta['image'] }}" data-inertia="twitter-image">
<meta name="twitter:image:alt" content="{{ $meta['image_alt'] }}" data-inertia="twitter-alt">
<script type="application/ld+json" data-inertia="schema">{!! json_encode($meta['schema'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
