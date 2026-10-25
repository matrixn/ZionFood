import { onUnmounted, watch } from 'vue';
import type { RestaurantBrand } from '@/types';
export function useStorefrontBrand(getBrand: () => RestaurantBrand | undefined) {
    const names = [
        '--color-brand',
        '--p-primary-color',
        '--p-primary-hover-color',
        '--p-primary-active-color',
        '--p-primary-500',
        '--p-primary-600',
        '--p-primary-700',
        '--p-primary-contrast-color',
    ];
    const previous = names.map((name) => document.documentElement.style.getPropertyValue(name));
    const icon = document.createElement('link');
    icon.rel = 'icon';
    icon.dataset.zionfoodBrand = 'true';
    watch(
        getBrand,
        (brand) => {
            const color = /^#[0-9a-f]{6}$/i.test(brand?.primary_color ?? '') ? brand!.primary_color! : '#635BFF';
            const rgb = [1, 3, 5]
                .map((offset) => parseInt(color.slice(offset, offset + 2), 16) / 255)
                .map((value) => (value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4));
            const contrast = rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722 > 0.179 ? '#171C2C' : '#ffffff';
            names.forEach((name) =>
                document.documentElement.style.setProperty(
                    name,
                    name === '--p-primary-contrast-color' ? contrast : color,
                ),
            );
            if (brand?.logo_path) {
                icon.href = '/storage/' + brand.logo_path;
                if (!icon.parentNode) document.head.appendChild(icon);
            } else icon.remove();
        },
        { immediate: true, deep: true },
    );
    onUnmounted(() => {
        names.forEach((name, index) =>
            previous[index]
                ? document.documentElement.style.setProperty(name, previous[index])
                : document.documentElement.style.removeProperty(name),
        );
        icon.remove();
    });
}
