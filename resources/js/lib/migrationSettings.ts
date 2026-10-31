import type { DeliveryPolygon } from './deliveryGeometry';
import type { BusinessHour } from '@/types';

export interface ImportedZone {
    id?: number;
    source_id?: string;
    name: string;
    fee: number;
    minimum_order: number;
    is_active: boolean;
    geometry: DeliveryPolygon | null;
    postal_prefix?: string;
}
export interface ImportedAddress {
    address: string;
    city: string | null;
    postal_code: string | null;
    country: string | null;
    latitude: number | null;
    longitude: number | null;
}
export interface ImportedHours {
    timezone: string;
    hours: BusinessHour[];
}
export type ImportedSettingValue = string | null | ImportedAddress | ImportedHours | ImportedZone[];
export interface ImportedSettingPreview {
    key: string;
    available: boolean;
    error: string | null;
    current: ImportedSettingValue;
    proposed: ImportedSettingValue;
    changed: boolean;
    added_zones: number;
    affected_locations: string[];
    current_setup_phone?: string | null;
    current_image_url?: string | null;
    proposed_image_url?: string | null;
}
