export class ApiError extends Error {
    constructor(
        public status: number,
        message: string,
    ) {
        super(message);
    }
}
export async function api<T>(
    url: string,
    method = 'GET',
    body?: unknown,
    headers: Record<string, string> = {},
): Promise<T> {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    const xsrf = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=');
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
            ...headers,
        },
        body: body === undefined ? undefined : body instanceof FormData ? body : JSON.stringify(body),
    });
    const data = response.status === 204 ? null : await response.json();
    if (!response.ok) {
        throw new ApiError(
            response.status,
            data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message ?? 'Request failed.'),
        );
    }
    return data as T;
}
export const money = (value: number, currency = 'RON'): string =>
    new Intl.NumberFormat('ro-RO', { style: 'currency', currency }).format(value / 100);
