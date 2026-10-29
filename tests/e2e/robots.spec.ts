import { test, expect } from '@playwright/test';

test('robots exposes public rendering rules and the canonical sitemap without blocking noindex account pages', async ({
    page,
    request,
}) => {
    const response = await request.get('/robots.txt');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('text/plain');
    const lines = (await response.text()).split('\n');
    for (const rule of [
        'User-agent: *',
        'Allow: /',
        'Allow: /build/',
        'Allow: /images/',
        'Allow: /api/v1/public/locations/*/menu$',
        'Disallow: /admin/',
        'Disallow: /api/',
        'Sitemap: https://food.zion3d.ro/sitemap.xml',
    ]) {
        expect(lines).toContain(rule);
    }
    expect(lines).not.toContain('Disallow: /');
    const sitemap = await request.get('/sitemap.xml');
    expect(sitemap.status()).toBe(200);
    expect(await sitemap.text()).toContain('https://food.zion3d.ro/en');
    const login = await page.goto('/login');
    expect(login?.headers()['x-robots-tag']).toBe('noindex, nofollow');
    expect((await request.get('/images/logo/food.zion3d.ro_icons-32x32.png')).status()).toBe(200);
});
