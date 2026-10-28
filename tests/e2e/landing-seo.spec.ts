import { test, expect as baseExpect } from '@playwright/test';
const expect = baseExpect.configure({ timeout: 20000 });

test('search engines receive useful content without JavaScript in both language URLs', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    for (const [path, lang] of [['/', 'ro'], ['/en', 'en']]) {
        const response = await page.goto(path);
        expect(response?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('lang', lang);
        await expect(page.locator('h1')).toHaveCount(1);
        await expect(page.locator('h1')).toContainText(lang === 'en' ? 'Your restaurant.' : 'Restaurantul tău.');
        await expect(page.locator('#migration')).toContainText('GloriaFood');
        await expect(page.locator('#migration a[href="/register"]')).toBeVisible();
        await expect(page.locator('head link[rel="canonical"]')).toHaveCount(1);
        const graph = JSON.parse(await page.locator('script[type="application/ld+json"]').innerText());
        expect(graph['@graph'][2].inLanguage).toBe(lang);
        await page.locator('summary').filter({ hasText: /GloriaFood/ }).click();
        await expect(page.locator('details[open]')).toContainText('JSON');
    }
    await page.locator('footer a[hreflang="ro"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ro');
    await context.close();
});

test('client enhancement keeps a single matching canonical, description and structured-data graph', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/en');
    await expect(page.locator('.landing')).toHaveAttribute('data-enhanced', 'true');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Your restaurant.');
    await expect(page.locator('head meta[name="description"]')).toHaveCount(1);
    await expect(page.locator('head link[rel="canonical"]')).toHaveCount(1);
    await expect(page.locator('script[type="application/ld+json"]')).toHaveCount(1);
    const graph = JSON.parse(await page.locator('script[type="application/ld+json"]').innerText());
    expect(graph['@graph'][2].inLanguage).toBe('en');
    expect(await page.locator('head link[rel="canonical"]').getAttribute('href')).toMatch(/\/en$/);
    await page.locator('#migration').scrollIntoViewIfNeeded();
    await expect(page.locator('#migration')).toHaveClass(/is-visible/);
    await expect(page.locator('#migration')).toHaveCSS('opacity', '1');
    await page.locator('#migration').screenshot({ path: 'test-results/seo-migration-desktop.png', animations: 'disabled' });
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.locator('#migration').screenshot({ path: 'test-results/seo-migration-mobile.png', animations: 'disabled' });
    // Metadata managed by the landing page must not survive an Inertia navigation into account creation.
    await page.locator('#migration a[href="/register"]').click();
    await expect(page).toHaveURL(/\/register$/);
    await expect(page.locator('head link[rel="canonical"]')).toHaveCount(0);
    await expect(page.locator('script[type="application/ld+json"]')).toHaveCount(0);
    expect(errors).toEqual([]);
});
