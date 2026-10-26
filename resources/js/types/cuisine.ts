export interface Cuisine {
    id: number;
    slug: string;
    name_ro: string;
    name_en: string;
    description_ro: string;
    description_en: string;
    image_path: string;
    image_credit: string | null;
    image_source_url: string | null;
    image_license: string | null;
    image_license_url: string | null;
    is_active: boolean;
    sort_order: number;
}
export interface CuisineSelection {
    id: number;
    selected_at: string;
}
