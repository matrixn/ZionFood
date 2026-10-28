<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($urls as $url)
<url><loc>{{ $url }}</loc>
@foreach($urls as $locale => $alternate)
<xhtml:link rel="alternate" hreflang="{{ $locale }}" href="{{ $alternate }}"/>
@endforeach
<xhtml:link rel="alternate" hreflang="x-default" href="{{ $urls['ro'] }}"/>
</url>
@endforeach
</urlset>
