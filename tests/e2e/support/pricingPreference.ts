import { expect, type Page } from '@playwright/test';

// Existing landing regressions exercise explicit RO/EN URLs, independently of first-visit detection.
export async function explicitRonPricing(page: Page) {
    await page.request.get('/en');
    const token = (await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')?.value;
    const result = await page.request.post('/pricing/preference', {
        data: { currency: 'RON' },
        headers: token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {},
    });
    expect(result.ok()).toBe(true);
    const language = await page.request.post('/locale', {
        data: { locale: 'ro' },
        headers: token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {},
    });
    expect(language.ok()).toBe(true);
}
