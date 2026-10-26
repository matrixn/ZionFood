import { i18n } from '@/i18n';
import type { Ingredient, Product } from '@/types';
export function marks(allergens: boolean | undefined, frozen: boolean | undefined): string {
    return allergens && frozen ? ' * / **' : allergens ? ' *' : frozen ? ' **' : '';
}
export function ingredientName(ingredient: Ingredient): string {
    return (
        (i18n.global.locale.value === 'en' && ingredient.name_en ? ingredient.name_en : ingredient.name) +
        marks(ingredient.contains_allergens, ingredient.from_frozen)
    );
}
export function productMarks(product: Product): string {
    return marks(
        product.contains_allergens || product.ingredients?.some((i) => i.contains_allergens),
        product.from_frozen || product.ingredients?.some((i) => i.from_frozen),
    );
}
