import { afterEach, expect, test } from 'vitest';
import { i18n } from '../../resources/js/i18n';
import { ingredientName, productMarks } from '../../resources/js/lib/ingredients';
import type { Ingredient, Product } from '../../resources/js/types';
afterEach(() => { i18n.global.locale.value = 'ro'; });
const ingredient: Ingredient = { id: 1, name: 'Creveti', name_en: 'Shrimp', contains_allergens: false, from_frozen: true };
test('frozen markers do not imply the single-star allergen flag', () => {
    expect(ingredientName(ingredient)).toBe('Creveti **');
    expect(productMarks({ ingredients: [ingredient] } as Product)).toBe(' **');
});
test('product markers combine explicit and recipe flags and tolerate older cached menus', () => {
    expect(productMarks({ contains_allergens: true, ingredients: [ingredient] } as Product)).toBe(' * / **');
    expect(productMarks({} as Product)).toBe('');
});
test('English ingredient names fall back to the source name without losing flags', () => {
    i18n.global.locale.value = 'en';
    expect(ingredientName(ingredient)).toBe('Shrimp **');
    expect(ingredientName({ ...ingredient, name_en: null, contains_allergens: true })).toBe('Creveti * / **');
});
