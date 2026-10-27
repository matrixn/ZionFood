import type { Menu, MenuContent } from '@/types';
export function normalizeMenuContent(value: MenuContent): MenuContent {
    const copy: MenuContent = JSON.parse(JSON.stringify(value));
    // PHP represents an empty associative array as []; normalize maps before editing.
    copy.translations = { ...copy.translations };
    return copy;
}
export function localizeMenus(menus: Menu[], language: string): Menu[] {
    if (language === 'ro') return menus;
    return menus.map((menu) => {
        const menuText = menu.categories
            .flatMap((c) => c.items)
            .map((p) => p.content?.translations[language])
            .find((t) => t?.menu_name);
        return {
            ...menu,
            name: menuText?.menu_name || menu.name,
            categories: menu.categories.map((category) => {
                const categoryText = category.items
                    .map((p) => p.content?.translations[language])
                    .find((t) => t?.category_name);
                return {
                    ...category,
                    name: categoryText?.category_name || category.name,
                    items: category.items.map((product) => {
                        const text = product.content?.translations[language];
                        if (!text) return product;
                        return {
                            ...product,
                            name: text.name || product.name,
                            description: text.description || product.description,
                            portion_size: text.portion_size || product.portion_size,
                            translated_ingredients: text.ingredients_text || undefined,
                            variants: product.variants.map((v) => ({
                                ...v,
                                name: text.variants?.[String(v.id)] || v.name,
                                modifier_groups: v.modifier_groups?.map((g) => ({
                                    ...g,
                                    name: text.groups?.[String(g.id)]?.name || g.name,
                                    options: g.options.map((o) => ({
                                        ...o,
                                        name: text.groups?.[String(g.id)]?.options?.[String(o.id)] || o.name,
                                    })),
                                })),
                            })),
                            modifier_groups: product.modifier_groups.map((g) => ({
                                ...g,
                                name: text.groups?.[String(g.id)]?.name || g.name,
                                options: g.options.map((o) => ({
                                    ...o,
                                    name: text.groups?.[String(g.id)]?.options?.[String(o.id)] || o.name,
                                })),
                            })),
                        };
                    }),
                };
            }),
        };
    });
}
