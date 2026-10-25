import { i18n, localeTag, tr } from '@/i18n';
export class ApiError extends Error {
    constructor(
        public status: number,
        message: string,
    ) {
        super(message);
    }
}
export function csrfHeaders(): Record<string, string> {
    const xsrf = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=');
    if (xsrf) return { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) };
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    return token ? { 'X-CSRF-TOKEN': token } : {};
}
export async function api<T>(
    url: string,
    method = 'GET',
    body?: unknown,
    headers: Record<string, string> = {},
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Accept-Language': i18n.global.locale.value,
            ...(body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            ...csrfHeaders(),
            ...headers,
        },
        body: body === undefined ? undefined : body instanceof FormData ? body : JSON.stringify(body),
    });
    const data = response.status === 204 ? null : await response.json();
    if (!response.ok) {
        throw new ApiError(
            response.status,
            data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message ?? tr('request_failed')),
        );
    }
    return data as T;
}
export const money = (value: number, currency = 'RON'): string =>
    new Intl.NumberFormat(localeTag(), { style: 'currency', currency }).format(value / 100);
