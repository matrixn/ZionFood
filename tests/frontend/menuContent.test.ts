import { describe, expect, it } from 'vitest';
import { localizeMenus, normalizeMenuContent } from '../../resources/js/lib/menuContent';
import { foodVocabularies } from '../../resources/js/lib/foodLabels';
import type { Menu, Product } from '../../resources/js/types';

function menu(): Menu[] {
    const product = { id: 1, public_id: 'product', category_id: 1, name: 'Preparat', description: 'Descriere', price: 5100, portion_size: '200 g', tax_basis_points: 900, is_available: true, image_path: null, variants: [{ id: 2, name: 'Mic', price: 5500, is_available: true }], modifier_groups: [{ id: 3, name: 'Sosuri', min_choices: 1, max_choices: 2, options: [{ id: 4, name: 'Roșii', price: 300, is_available: true }] }], content: { allergens: ['milk'], may_contain: [], labels: [], nutrition: null, translations: { de: { name: 'Gericht', description: 'Beschreibung', ingredients_text: 'Milch *', portion_size: '200 g', category_name: 'Kategorie', menu_name: 'Speisekarte', variants: { '2': 'Klein' }, groups: { '3': { name: 'Soßen', options: { '4': 'Tomaten' } } } } } } } as Product;
    return [{ id: 1, name: 'Meniu', categories: [{ id: 1, menu_id: 1, name: 'Categorie', items: [product] }] }];
}
describe('locally stored menu translations', () => {
    it('preserves language keys when an empty PHP map was serialized as an array', () => {
        const content = normalizeMenuContent({ allergens: [], may_contain: [], labels: [], nutrition: null, translations: [], recipe: [], yield_grams: null, servings: null } as unknown as import('../../resources/js/types').MenuContent);
        content.translations.de = menu()[0].categories[0].items[0].content!.translations.de;
        expect(JSON.parse(JSON.stringify(content)).translations.de.name).toBe('Gericht');
        for (const vocabulary of Object.values(foodVocabularies)) {
            expect(Object.keys(vocabulary).length).toBe(37);
            expect(Object.keys(vocabulary).some(k => k.includes('undefined'))).toBe(false);
        }
    });
    it('changes presentation labels without altering prices, ids or modifier rules', () => {
        const source = menu(); const localized = localizeMenus(source, 'de'); const product = localized[0].categories[0].items[0];
        expect(localized[0].name).toBe('Speisekarte'); expect(localized[0].categories[0].name).toBe('Kategorie'); expect(product.name).toBe('Gericht'); expect(product.translated_ingredients).toBe('Milch *');
        expect(product.price).toBe(5100); expect(product.variants[0]).toEqual({ id: 2, name: 'Klein', price: 5500, is_available: true });
        expect(product.modifier_groups[0]).toMatchObject({ id: 3, min_choices: 1, max_choices: 2, options: [{ id: 4, name: 'Tomaten', price: 300 }] });
        expect(source[0].categories[0].items[0].name).toBe('Preparat');
    });
    it('falls back to the source for missing translations and preserves the source language', () => {
        const source = menu(); expect(localizeMenus(source, 'ro')).toBe(source); expect(localizeMenus(source, 'fr')[0].categories[0].items[0].name).toBe('Preparat');
    });
});
