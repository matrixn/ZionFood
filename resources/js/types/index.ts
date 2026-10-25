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
}
export interface BusinessHour {
    weekday: number;
    opens_at: string;
    closes_at: string;
}
export interface Location {
    id: number;
    public_id: string;
    organization_id: number;
    name: string;
    address: string;
    currency: string;
    timezone: string;
    accepting_orders: boolean;
    restaurant: { name: string; slug: string };
    hours: BusinessHour[];
    fulfillment: { pickup_enabled: boolean; delivery_enabled: boolean };
    zones: Zone[];
}
export type OrderStatus =
    'pending' | 'accepted' | 'preparing' | 'ready' | 'out_for_delivery' | 'completed' | 'rejected' | 'cancelled';
export interface Order {
    public_id: string;
    status: OrderStatus;
    version: number;
    fulfillment: string;
    customer_name: string;
    customer_phone: string;
    delivery_address: string | null;
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
export interface WorkspaceProps {
    location: Location;
    locations: Location[];
    canManage: boolean;
}
