import { localeTag } from '@/i18n';
export interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
export interface Subscription {
    plan: string | null;
    status: string;
    trial_ends_at: string | null;
    expired: boolean;
}
const utcDate = (value: string) =>
    new Date(
        value.length === 10
            ? value + 'T00:00:00Z'
            : value.endsWith('Z') || /[+-]\d\d:\d\d$/.test(value)
              ? value
              : value.replace(' ', 'T') + 'Z',
    );
export const adminDate = (value: string | null) =>
    value ? new Intl.DateTimeFormat(localeTag(), { dateStyle: 'medium', timeZone: 'UTC' }).format(utcDate(value)) : '�';
export const adminDateTime = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(localeTag(), { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' }).format(
              utcDate(value),
          )
        : '�';
export const adminMoney = (value: number, currency: string) =>
    new Intl.NumberFormat(localeTag(), { style: 'currency', currency }).format(value / 100);
export const attentionLinks: Record<string, string> = {
    imports: '/admin/operations?status=failed',
    webhooks: '/admin/communication-logs?direction=outbound',
    outbox: '/admin/operations',
    emails: '/admin/emails?state=failed',
    deferred_emails: '/admin/emails?state=deferred',
    ai: '/admin/menu-ai',
};
