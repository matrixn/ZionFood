import { afterEach, expect, it, vi } from 'vitest';
import { i18n, tr, localeTag, localeCodes, primeLocale } from '../../resources/js/i18n';
import en from '../../resources/js/i18n/en.json';
import itCatalog from '../../resources/js/i18n/it.json';
import es from '../../resources/js/i18n/es.json';
import nl from '../../resources/js/i18n/nl.json';

afterEach(() => {
    i18n.global.locale.value = 'ro';
    vi.restoreAllMocks();
});

it('preserves every message key and interpolation while compiling the new complete catalogues', () => {
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});
    for (const [locale, catalog] of [
        ['it', itCatalog],
        ['es', es],
        ['nl', nl],
    ] as const) {
        expect(Object.keys(catalog).sort()).toEqual(Object.keys(en).sort());
        i18n.global.locale.value = locale;
        for (const [key, value] of Object.entries(catalog)) {
            expect(value.match(/\{[^{}]*\}/g)?.sort() ?? [], key).toEqual(
                en[key as keyof typeof en].match(/\{[^{}]*\}/g)?.sort() ?? [],
            );
            expect(value).not.toMatch(/ZXQPH|\uFFFD/);
            expect(typeof tr(key)).toBe('string');
        }
    }
    expect(error).not.toHaveBeenCalled();
});

it('localizes primary restaurant actions, formatting and component accessibility', () => {
    for (const [locale, save, tag, close] of [
        ['it', 'Salva', 'it-IT', 'Chiudi'],
        ['es', 'Guardar', 'es-ES', 'Cerrar'],
        ['nl', 'Opslaan', 'nl-NL', 'Sluiten'],
    ] as const) {
        i18n.global.locale.value = locale;
        expect(tr('save')).toBe(save);
        expect(localeTag()).toBe(tag);
        expect(primeLocale(locale).aria.close).toBe(close);
        expect(primeLocale(locale).dayNames).toHaveLength(7);
        expect(primeLocale(locale).monthNames).toHaveLength(12);
    }
    expect(localeCodes).toEqual(['ro', 'en', 'it', 'es', 'nl']);
});
