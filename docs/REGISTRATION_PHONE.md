# Registration telephone — 0.0.57

New account registration, including staff invitations, requires a country/region and a valid telephone number. Existing accounts are not backfilled or blocked at login.

## Interface

The two-part control starts with Romania (`RO`, `+40`). Its closed country selector shows a locally served flag and calling prefix; the country name remains available to assistive technology. Options show flag, prefix and the international English name. Search also accepts the current UI language's country name, country code and calling prefix. Countries that share a prefix remain distinct options.

The number accepts national formatting or a complete international number matching the selected country. Spaces, parentheses, periods and hyphens are allowed; extensions, text, malformed values and foreign-region numbers are rejected. Validation feedback uses reserved space so that an error appearing on blur does not move a button during the user's click. The country and number controls have bilingual contextual help, explicit accessible names and direct Tab navigation. Password preservation and external hold-to-reveal behavior remain unchanged.

## Validation and storage

- Server: `giggsey/libphonenumber-for-php` 9.0.42; parse using the selected region, require `isValidNumberForRegion`, reject extensions and normalize to E.164.
- Browser: `libphonenumber-js` 1.13.15, maximum metadata; require a valid number belonging to the selected country. Client checks improve feedback; the server independently validates every request before consuming CAPTCHA proof or admitting account mail.
- `users.phone` is nullable encrypted text, storing the normalized international number through Laravel's encrypted cast. `users.phone_country` is the canonical two-letter selected region. Both are hidden from default User serialization and existing Inertia/account DTOs.
- New registration requires both values even when client validation is bypassed. Existing database users can retain nulls. Numbers are not unique: multiple staff can legitimately share a contact number. No restaurant public contact field is changed by signup.
- This validates numbering rules; it does not perform SMS verification or establish ownership/reachability. No telephone lookup provider or external flag request is used at runtime.

The included migration adds the two nullable columns. Deploying the updated code requires applying it. Keep the two metadata libraries updated together and rerun cross-region vectors when updating them; this release adds these dependencies without upgrading other existing packages.

## Flag sources and licenses

Most flags are bundled from `flag-icons` 7.6.0 (MIT; Panayiotis Lipiridis), with notice at `/licenses/flag-icons.txt`. Ascension (`AC`) and Tristan da Cunha (`TA`) are supported telephone regions absent from that package's flag set. Their local SVGs come from HatScripts circle-flags `flags/sh-ac.svg` and `flags/sh-ta.svg`; MIT notice at `/licenses/circle-flags.txt`. They are displayed as images, with no SVG scripts or external resources.

The frontend telephone library MIT notice is also distributed at `/licenses/libphonenumber-js.txt`. Backend dependency licenses remain in their Composer packages.

Primary references:

- https://github.com/giggsey/libphonenumber-for-php
- https://github.com/catamphetamine/libphonenumber-js
- https://github.com/lipis/flag-icons
- https://github.com/HatScripts/circle-flags

## Operational scope

The new phone fields do not introduce SMS, change invitation proof/email verification, expose phone contacts to staff/platform screens, or modify authentication/tenant permissions. Follow-up work may add an explicitly authorized account contact editor or phone verification workflow.

## Shared contact capture from 0.0.50

The marketing contact card reuses the same client selector/number validation and backend `RegistrationPhone` normalizer. Component IDs are configurable (`phone` for registration, `lead-phone` for contact capture), and both controls can be disabled while a request is in progress. Contacts require the selected country and store an encrypted E.164 phone through ServiceLead; no User or SMS is created. Existing nullable user fields and lead records are unchanged.
