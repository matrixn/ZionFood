# robots.txt policy

The canonical endpoint is `/robots.txt`, served by LandingController. Its reviewable UTF-8/LF source is `resources/seo/robots.txt`; BuildRobotsTxt applies it only when crawling is enabled. Do not copy it to public/robots.txt: a static file would bypass host-aware API/staging restrictions and canonical sitemap selection in this multi-host app.

## Public-site policy

One `User-agent: *` group allows public pages, including both marketing languages, restaurant menus and legacy menu redirects. Explicit Allow entries retain build assets, images, public storage, fonts and flags. The broader `/api/` exclusion has longer, specific read-only exceptions for public location menu and availability JSON, including query variants, so crawlers can render the storefront. No general query-string block is used; canonical page metadata handles tracking parameters.

Protected workspace/admin/kitchen, Horizon/Telescope/Pulse, broadcasting/Sanctum, general API and health paths discourage crawling. Root, child and query forms of operational paths are covered without accidentally blocking future similarly prefixed public names. Public account, invitation, reservation, QR and tracking pages remain crawlable so their existing X-Robots-Tag noindex headers can be read. Robots is not authorization and a Disallow rule alone does not guarantee removal of a linked URL from search results. Existing auth/tenant controls remain authoritative.

The canonical marketing sitemap is appended only for configured central hosts with indexable current marketing HTML. Its origin is validated configuration, never a request Host echo. Verified restaurant domains receive the public rules without the marketing sitemap. Dedicated API hosts, globally disabled indexing and incomplete/nonindexable marketing builds receive exactly `User-agent: *` plus `Disallow: /`. Global `ZF_SEO_INDEXABLE=false` applies to restaurant domains as well. Responses are UTF-8 plain text with no-store/private caching so a proxy does not reuse a policy across hosts or readiness changes.

## Deployment and verification

Publish source and rebuilt 0.0.31 marketing artifacts through the normal deployment, preserve the existing ZF_PUBLIC_URL and indexing configuration, and verify actual GET `/robots.txt` and `/sitemap.xml` on each relevant host. A known exact Nginx robots location must use Laravel front-controller fallback, as shown in LANDING_PAGE.md. No server configuration is changed automatically. The public food.zion3d.ro robots endpoint returned 404 during this work; local passing tests do not certify that live deployment has occurred or diagnose the reason for that 404.

Once the public endpoint returns its intended policy, verify Search Console/Bing ownership, submit the canonical sitemap and inspect rendering/indexability. Search engines choose crawling/indexing/ranking; no source file guarantees placement. No unsupported Noindex, Host or Crawl-delay fields, competing per-bot groups or invented sitemap priorities are introduced.

## Primary references

- [Google: robots.txt fields, matching, host scope and status handling](https://developers.google.com/crawling/docs/robots-txt/robots-txt-spec)
- [Google: noindex requires crawl access and does not belong in robots.txt](https://developers.google.com/search/docs/crawling-indexing/block-indexing)
