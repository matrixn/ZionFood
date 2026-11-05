export const localeCodes = ['ro', 'en', 'it', 'es', 'nl'] as const;
export type Locale = (typeof localeCodes)[number];
export const localeTags: Record<Locale, string> = { ro: 'ro-RO', en: 'en-GB', it: 'it-IT', es: 'es-ES', nl: 'nl-NL' };
export const languageOptions: { code: Locale; name: string; flag: string }[] = [
    { code: 'ro', name: 'Română', flag: '/flags/ro.svg' },
    { code: 'en', name: 'English', flag: '/flags/en.svg' },
    { code: 'it', name: 'Italiano', flag: '/flags/it.svg' },
    { code: 'es', name: 'Español', flag: '/flags/es.svg' },
    { code: 'nl', name: 'Nederlands', flag: '/flags/nl.svg' },
];
export function normalizeLocale(value: unknown): Locale {
    return localeCodes.includes(value as Locale) ? (value as Locale) : 'ro';
}
export const localePath = (locale: Locale): string => (locale === 'ro' ? '/' : '/' + locale);
