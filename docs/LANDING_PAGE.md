# Public ZionFood presentation and SEO — 0.0.27

The central-host presentation uses Welcome.vue, with Romanian `/` and English `/en`. A language URL always renders its declared language, independently of cookies, browser language or crawler user agent. It sets the matching preference cookie; the flag/name selector also preserves the existing preference endpoint and navigates to the language URL. Footer language links work without JavaScript. Verified restaurant domains still serve their own restaurant at `/` and do not expose `/en` or the marketing sitemap.

## HTML available before JavaScript

`npm run build` performs the existing type check/client Vite build, builds the small SEO renderer and runs scripts/build-seo.mjs. Vue's existing server renderer generates four HTML fragments (RO/EN × guest/member) from the **same Welcome component and translations** under public/build/seo, with a release-version manifest written last. No new JavaScript dependency or persistent Node SSR daemon is required. Intermediate renderer code is ignored under .bootstrap/seo-renderer. Node is a build-time requirement as before.

LandingGateway returns that trusted build HTML through Inertia's SSR gateway only for Welcome. It embeds the current request's properly escaped Inertia JSON, never a cached user's session data. Runtime Vue enhances the document using the existing application setup; it does not depend on a runtime SSR service. Other pages retain the framework's existing HTTP gateway behavior. Guest/member fragments select the right CTA, contain no personal account values and are immutable per build. `data-enhanced` indicates that browser interactions have mounted; content is visible beforehand, including with JavaScript disabled. Missing/stale fragments fall back to the existing client-rendered UI **with noindex**, rather than advertising an empty page as indexable. Activate releases only after a successful full build.

## Canonical URLs and metadata

LandingSeo produces metadata once for both Blade's initial HTML head and the Vue Head component: descriptive localized title/description, a single absolute canonical, reciprocal RO/EN/x-default hreflang, Open Graph, large Twitter card and JSON-LD Organization/WebSite/WebPage/Service. The graph describes the implemented service and contains no fabricated ratings, prices, adoption statistics or affiliation. FAQs are visible native details/summary content; no FAQ rich-result eligibility is promised. Head keys use Inertia 3's data-inertia markers so metadata updates without duplicates and is removed when navigating into account pages.

The canonical origin comes from `ZF_PUBLIC_URL` (default APP_URL), not the request Host header, path query or tracking parameters. It must be an HTTP(S) root URL on a configured central ZF_APP_HOSTS host, without credentials/query/fragment. Deployment example:

```dotenv
APP_URL=https://food.zion3d.ro
ZF_PUBLIC_URL=https://food.zion3d.ro
ZF_SEO_INDEXABLE=true
# Keep this hostname in the existing comma-separated ZF_APP_HOSTS list.
```

Set ZF_SEO_INDEXABLE=false on staging. HTTP/localhost/IP/test/invalid origins and missing/current-version-mismatched SEO fragments are nonindexable even when the flag is true. APP_ENV is not used as an indexing switch because Forge deployments may use a custom environment name. Validate the actual public HTTPS origin, DNS and certificate separately.

`/sitemap.xml` contains only the two indexable marketing URLs with reciprocal language alternates; no private pages, tenant IDs or fabricated lastmod timestamps. If indexing is disabled or the generated build is absent, the sitemap contains no locations. `/robots.txt` advertises the configured canonical sitemap when ready; staging/API hosts disallow crawling, while verified restaurant-domain public-menu behavior remains allowed. Private/account/tracking/reservation/table/API/diagnostic responses use X-Robots-Tag noindex; robots.txt is not an access-control mechanism. Tenant authorization remains unchanged.

The former static public/robots.txt is intentionally replaced by the Laravel route. **Forge/Nginx must forward a missing robots.txt to Laravel.** If the site has the common exact static-only robots location, adapt it during an explicitly authorized deployment, for example:

```nginx
location = /robots.txt {
    try_files $uri /index.php?$query_string;
    access_log off;
    log_not_found off;
}
```

Use the existing site's PHP/front-controller configuration; do not create a second site or bypass TLS. Before submitting the sitemap, verify that the public `/robots.txt` and `/sitemap.xml` actually return 200 and the intended content. Source changes alone do not certify the live Forge proxy configuration.

`php artisan zionfood:seo-check` prints the configured public origin, RO/EN render readiness, indexing policy and sitemap URL without sending requests. It exits nonzero for a disabled/local/missing build. It does not prove DNS/TLS, crawler access or indexation. After deployment, verify the property in Google Search Console/Bing Webmaster Tools, submit the sitemap and inspect `/` and `/en`. Search engines decide crawling/indexing/ranking; neither a Lighthouse score nor valid JSON-LD guarantees inclusion or rich results.

## GloriaFood migration content

Navigation, a dedicated three-step section and FAQ describe the implemented Migration Center: Fetch Menu key or JSON upload, staged preview, verification report, explicit publication and reimport conflict review. The copy explains that photos, hours, delivery zones and historical orders are not automatically transferred by this menu importer; restaurant review of prices and food declarations is required. It links the official Fetch Menu documentation, never requests the GloriaFood password and states that ZionFood is independent. No unverified shutdown date, transfer-time promise or lossless migration guarantee is advertised. See MIGRATION_CENTER.md for the actual import contract and real-provider acceptance still needed.

## Visuals, interaction and accessibility

Existing generated artworks remain unchanged source PNGs with provenance in assets/landing-images.prompt.md. scripts/build-marketing-images.php creates committed 640/960/1440 WebP encodings from those originals, without new imagery. Responsive srcset/sizes and reserved aspect ratios avoid unnecessary image transfer/layout shifts; the hero has high fetch priority and the kitchen image lazy loads. Largest WebP hero/kitchen files are about 259/101 kB versus 2.84/2.05 MB source PNGs. The supplied ZionFood PNG icon set now supplies responsive platform marks, multi-size PNG favicons/touch icon and the Organization logo in JSON-LD. Marketing photo/share artwork stays independent of the logo icon. See PLATFORM_BRANDING.md.

Feature tabs and the local order demo remain illustrative and create no real orders, reservations, email or payment mutations. Reduced-motion behavior, skip link, native headings, mobile navigation, image alternatives and actual registration/login/dashboard CTAs remain. Payments are explicitly unavailable. The 14-day trial reflects current onboarding. Detailed screenshots remain private test artifacts rather than product claims.

## Validation boundaries

PHP tests check raw text outside the Inertia JSON, language/cookie determinism, canonical host/query isolation, reciprocal sitemap/hreflang, safe structured data, readiness/noindex behavior, personalized CTA separation, invalid configuration and tenant-domain preservation. Playwright checks JavaScript-disabled content and language navigation, client metadata uniqueness/removal, existing interactions, RO/EN persistence, responsive overflow and reduced motion. Exact run results and Lighthouse conditions are in CODEX_PROGRESS.md. Live Forge/Nginx/DNS/TLS, Search Console/Bing ownership/submission, actual indexation, production Core Web Vitals and assistive-technology coverage are not inferred from local results.

Primary references: [Google JavaScript SEO](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics), [localized versions](https://developers.google.com/search/docs/specialty/international/localized-versions), [robots.txt](https://developers.google.com/search/docs/crawling-indexing/robots/intro), [official GloriaFood Fetch Menu API](https://github.com/GlobalFood/integration_docs/blob/master/fetch_menu/README.md).
