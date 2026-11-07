export type RewardMode = 'customer_qr' | 'venue_qr' | 'online';

export interface RewardCampaign {
    public_id: string;
    name: string;
    mode: RewardMode;
    active: boolean;
    current: boolean;
    starts_on: string | null;
    ends_on: string | null;
    buy_products: string[];
    buy_quantity: number;
    reward_name: string;
    reward_quantity: number;
    progress: number;
    available: number;
    redeemed: number;
}

export interface RewardCode {
    code: string;
    expires_at: string;
}

const cardKey = (location: string) => 'zf-rewards:' + location;

export function readRewardCard(location: string): string {
    try {
        return localStorage.getItem(cardKey(location)) ?? '';
    } catch {
        return '';
    }
}

export function saveRewardCard(location: string, token: string): boolean {
    try {
        localStorage.setItem(cardKey(location), token);
        return true;
    } catch {
        return false;
    }
}
