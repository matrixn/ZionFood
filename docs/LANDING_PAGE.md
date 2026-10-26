# Public ZionFood presentation page

The central-host `/` page uses `resources/js/pages/Welcome.vue`. Verified storefront hosts still serve their restaurant menu at `/`; marketing does not replace a restaurant's storefront. No new routes, subscriptions, payments or ordering actions were added.

## Content and interaction

- Romanian by default, English through the existing persistent flag/name selector. Both catalogues include the same landing keys; title, description, alternate text, navigation, FAQ and previews change reactively.
- Editorial hero with a generated restaurant-table image, branded typography and illustrative reservation/table cards.
- Feature tabs for menu, reservations, QR and mapped delivery. Native Vue/CSS/SVG preview interfaces use illustrative data, not live restaurant data. Tabs support click, arrow keys, Home and End.
- Interactive kitchen/order sequence. Its controls only change local display state: no orders, reservations, notifications or payment requests are created.
- Generated kitchen-service image, operational story, native details/summary FAQ and repeated primary calls to action.
- Guest primary CTA goes to `/register`, sign-in to `/login`. Signed-in visitors receive `/app` links instead of registration CTAs. Section links target real sections on the page. No fabricated customer testimonials, adoption counts, pricing or payment availability claims are used.
- The 27 cuisine choices, four optional services and RO/EN indicators describe implemented functionality. Online payments are explicitly described as unavailable in the FAQ; the trial copy reflects existing 14-day onboarding.

## Animation and accessibility

CSS float/orbit/pulse effects, path animation, hover feedback, preview transitions, a scroll-progress indicator and one-time viewport reveals. IntersectionObserver is disconnected and scroll animation frames/listeners are removed on unmount. No animation library or external font/image service was introduced.

`prefers-reduced-motion: reduce` disables animations/transitions and leaves revealed content visible. The page includes a skip link, semantic headings/navigation, focus styles, keyboard-operable tabs, live order-status feedback, an expanded-state mobile menu and localized image alternatives. Native browser/assistive-technology validation beyond the recorded Chromium checks remains open.

The hero has fixed image dimensions and high fetch priority; the secondary image uses native lazy loading. PNGs remain unchanged generated originals; next-generation encodings/performance audits may be evaluated separately. The page is responsive down to the tested 320px width. Global application styling and restaurant layouts are not replaced.

## Generated assets

Created with built-in image_gen using the imagegen skill, copied into the workspace:

- `public/images/marketing/restaurant-table-ai.png`
- `public/images/marketing/kitchen-service-ai.png`

Prompts and provenance: [landing-images.prompt.md](assets/landing-images.prompt.md). They are AI illustrations rather than images of an actual venue or product screenshots. The public footer and alternate descriptions identify them accordingly. The earlier README banner remains separate.

## Verification

`tests/e2e/landing.spec.ts` checks RO/EN persistence, actual registration/login targets, feature tabs and keyboard navigation, order-demo state transitions, the payments FAQ, image decoding, absence of ordering mutation requests, mobile navigation, 320px/390px overflow and reduced motion. PHP route checks distinguish Welcome on the central host from Menu on a storefront host. Exact executed results are recorded in CODEX_PROGRESS.md; production browser/performance/deployment results are not inferred from local checks.
