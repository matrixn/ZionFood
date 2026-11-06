# Interface localization — 0.0.54

Romanian remains the default. English, Italian, Spanish and Dutch are available in marketing and restaurant management, including the common authentication/onboarding entry. Selectors show local flag assets and native names: Română, English, Italiano, Español, Nederlands. Language preference persists through the existing protected POST /locale endpoint, user locale, httponly preference cookie and both full reload/Inertia navigation.

| Surface | Interface languages | Preference |
| --- | --- | --- |
| Marketing | RO/EN/IT/ES/NL | URL language, then its matching cookie |
| Restaurant workspace/auth entry | RO/EN/IT/ES/NL | zf_locale / account locale |
| General platform administration | RO/EN | independent zf_admin_locale cookie, validated admin-only scope |
| Customer ordering/QR/tracking/booking storefront | RO/EN | existing restricted storefront selector and effective response locale |

`UiLocale` defines backend codes, default normalization, marketing paths and OpenGraph tags; `i18n/locales.ts` defines matching frontend tags/names. General-admin routes normalize an unsupported restaurant preference to Romanian and preserve their independent cookie. Choosing English in the platform panel does not overwrite a restaurant's Italian/Spanish/Dutch preference. The Vue application synchronizes language from Inertia response props when moving between those surfaces. Existing personal-data authorization and feature/location permissions remain in effect.

Each new Vue catalogue includes all1,960 existing message keys. Matching Laravel JSON/domain messages plus validation/auth/password/pagination files cover server feedback. PrimeVue filtering/calendar/accessibility strings and Intl number/date locale tags support the new languages. Existing terms, identifiers, placeholders and numeric values are preserved; API keys, enum values, event names, IDs and database business records are not translated. Original restaurant/product names, descriptions, ingredients and cuisine catalogue records remain data rather than interface copy; menu-content translation is its separate reviewed workflow.

The new static catalogues were produced from repository English interface text using automated draft translation, with protected interpolation/identifier/URL tokens, then reviewed and corrected for primary marketing, authentication, restaurant, seating and kitchen terminology. No runtime records, credentials or private account/menu data were sent for translation. The temporary generation/checking helpers and drafts remain private local artifacts. Runtime uses committed local messages only, without an external translation dependency. Language compilation/key/placeholder parity is covered by frontend tests; native editorial proofreading of all long-form strings remains a possible future quality pass rather than a claimed verification.

The added country flags use the existing flag-icons assets/license already bundled in public/licenses. No dependency, database schema or environment variable was added. FieldHelp/ZFFormField, F1 explanations, direct Tab navigation and password retry/reveal behavior remain available. New locales do not change money authority, subscription plans, permissions or ordering semantics.

## Marketing URLs and search engines

The canonical pages are /, /en, /it, /es and /nl on the configured public origin. Build prerendering produces ten variants: five languages × guest/member. Each response has translated metadata and its own canonical, correct language header/HTML tag and reciprocal hreflang links. The sitemap lists five URLs, each containing all five alternates plus x-default to Romanian. Indexing stays disabled for local/staging or an incomplete language cluster. Admin and restaurant management routes retain noindex headers. See LANDING_PAGE.md and the linked official Google guidance.

## Checks and scope

Version 0.0.54 adds approximate first-visit country suggestions from browser timezone/region. Unsupported locations fall back to English/EUR; explicit language URLs and saved/manual preferences take priority. The independent RO/EN platform-admin boundary is unchanged. See PRICING_CURRENCIES.md.

See CODEX_PROGRESS.md for actual executed PHP/frontend/browser/formatting checks and limitations. Language URLs do not prove live deployment, search-engine indexing, native editorial review, public ordering-language expansion or automatic menu translation. Existing source RO/EN copy and historical documentation are retained where they describe earlier releases.
