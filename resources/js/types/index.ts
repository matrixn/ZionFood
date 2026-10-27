export interface MenuTranslation {
    name: string;
    description: string;
    ingredients_text: string;
    portion_size: string;
    category_name: string;
    menu_name: string;
    variants: Record<string, string>;
    groups: Record<string, { name: string; options: Record<string, string> }>;
    source_hash?: string;
}
export interface Nutrition {
    basis: string;
    values: Record<string, number | null>;
    reference?: string;
    estimated: boolean;
}
export interface MenuContent {
    allergens: string[];
    may_contain: string[];
    labels: string[];
    nutrition: Nutrition | null;
    translations: Record<string, MenuTranslation>;
    recipe: { name: string; grams: number; reference: string; values: Record<string, number | null> }[];
    yield_grams: number | null;
    servings: number | null;
}
export interface Ingredient {
    id: number;
    name: string;
    name_en?: string | null;
    restaurant_id?: number | null;
    contains_allergens: boolean;
    from_frozen: boolean;
    is_active?: boolean;
    needs_review?: boolean;
    deleted_at?: string | null;
}
export interface Variant {
    id: number;
    name: string;
    price: number;
    is_available: boolean;
}
export interface ModifierOption {
    id: number;
    name: string;
    price: number;
    is_available: boolean;
}
export interface ModifierGroup {
    id: number;
    name: string;
    min_choices: number;
    max_choices: number;
    options: ModifierOption[];
}
export interface Product {
    content?: Pick<MenuContent, 'allergens' | 'may_contain' | 'labels' | 'nutrition' | 'translations'>;
    translated_ingredients?: string;
    portion_size?: string | null;
    contains_allergens?: boolean;
    from_frozen?: boolean;
    ingredients?: Ingredient[];
    id: number;
    public_id: string;
    category_id: number;
    name: string;
    description: string | null;
    price: number;
    tax_basis_points: number;
    is_available: boolean;
    image_path: string | null;
    variants: Variant[];
    modifier_groups: ModifierGroup[];
}
export interface Category {
    id: number;
    menu_id: number;
    name: string;
    items: Product[];
}
export interface Menu {
    id: number;
    name: string;
    categories: Category[];
}
export interface Zone {
    id?: number;
    name: string;
    postal_prefix: string;
    fee: number;
    minimum_order: number;
    geometry?: import('@/lib/deliveryGeometry').DeliveryPolygon | null;
    priority?: number;
}
export interface BusinessHour {
    weekday: number;
    opens_at: string;
    closes_at: string;
}
export interface Location {
    storefront_path: string;
    restaurant_id: number;
    id: number;
    public_id: string;
    organization_id: number;
    name: string;
    address: string;
    currency: string;
    timezone: string;
    accepting_orders: boolean;
    restaurant: RestaurantBrand;
    hours: BusinessHour[];
    fulfillment: {
        pickup_enabled: boolean;
        delivery_enabled: boolean;
        dine_in_enabled?: boolean;
        dine_in_cutoff_minutes?: number;
        reservations_enabled?: boolean;
    };
    zones: Zone[];
}
export type OrderStatus =
    'pending' | 'accepted' | 'preparing' | 'ready' | 'out_for_delivery' | 'completed' | 'rejected' | 'cancelled';
export interface Order {
    public_id: string;
    status: OrderStatus;
    version: number;
    fulfillment: string;
    table_code?: string | null;
    table_area?: string | null;
    customer_name: string;
    customer_phone: string;
    delivery_address: string | null;
    delivery_latitude?: number | null;
    delivery_longitude?: number | null;
    notes: string | null;
    subtotal: number;
    delivery_fee: number;
    total: number;
    currency: string;
    created_at: string;
    transitions: OrderStatus[];
    items: {
        id: number;
        name: string;
        variant_name: string | null;
        quantity: number;
        total: number;
        modifiers: { id: number; name: string; price: number }[];
    }[];
    history: { id: number; to_status: OrderStatus; created_at: string; reason: string | null }[];
}
export type StaffFeature = 'orders' | 'reservations' | 'tables' | 'kitchen' | 'menu';
export type AccessLevel = 'none' | 'view' | 'manage';
export type FeaturePermissions = Record<StaffFeature, AccessLevel>;
export interface WorkspaceProps {
    permissions: FeaturePermissions;
    embedded?: boolean;
    location: Location;
    locations: Location[];
    canManage: boolean;
}

export interface RestaurantBrand {
    name: string;
    slug: string;
    description?: string | null;
    tagline?: string | null;
    primary_color?: string;
    logo_path?: string | null;
    cover_path?: string | null;
    contact_email?: string | null;
    contact_phone?: string | null;
    website?: string | null;
}
