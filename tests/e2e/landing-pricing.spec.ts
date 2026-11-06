import { explicitRonPricing } from './support/pricingPreference';
import { test, expect } from '@playwright/test';

test('pricing follows the hero and is linked from responsive bilingual navigation with real trial entry', async ({
    page,
    browser,
}) => {
    test.setTimeout(90000);
    const errors: string[] = [];
    const mutations: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('request', (request) => {
        if (!['GET', 'HEAD'].includes(request.method()) && !request.url().endsWith('/locale'))
            mutations.push(request.url());
    });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    for (const [path, locale] of [
        ['/', 'ro'],
        ['/en', 'en'],
    ]) {
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto(path);
        await expect(page.locator('.landing')).toHaveAttribute('data-enhanced', 'true');
        await expect(page.locator('.hero + #pricing')).toHaveCount(1);
        const pricing = page.locator('#pricing');
        await expect(pricing.locator('article')).toHaveCount(3);
        for (const [key, name, price] of [
            ['menu', 'Menu', '49'],
            ['orders', 'Orders', '149'],
            ['business', 'Business', '249'],
        ]) {
            const card = pricing.locator(`[data-plan="${key}"]`);
            await expect(card.getByRole('heading', { name, exact: true })).toBeVisible();
            await expect(card.locator('data')).toHaveText(price);
            await expect(card.locator('.pricing-period')).toHaveText(
                locale === 'ro' ? '/ lună / locație' : '/ month / location',
            );
            await expect(card.locator('li')).toHaveCount(6);
        }
        await expect(pricing.locator('[data-plan="orders"] .pricing-popular')).toHaveText(
            locale === 'ro' ? '✧Cel mai popular' : '✧Most popular',
        );
        await page.locator('.desktop-links a[href="#pricing"]').click();
        await expect(page).toHaveURL(/#pricing$/);
        const headerBottom = await page.locator('.site-header').evaluate((n) => n.getBoundingClientRect().bottom);
        expect((await pricing.boundingBox())!.y).toBeGreaterThanOrEqual(headerBottom - 1);
        const cards = await pricing
            .locator('article')
            .evaluateAll((nodes) => nodes.map((n) => n.getBoundingClientRect().top));
        expect(Math.max(...cards) - Math.min(...cards)).toBeLessThan(2);
        await pricing.screenshot({ path: `test-results/pricing-${locale}-desktop.png`, animations: 'disabled' });
        for (const width of [1200, 1100, 900, 768, 390, 360]) {
            await page.setViewportSize({ width, height: 844 });
            await page.evaluate(() => window.scrollTo(0, 0));
            await page.locator('.mobile-toggle').click();
            await page.locator('#mobile-navigation a[href="#pricing"]').click();
            await expect(page.locator('#mobile-navigation')).toHaveCount(0);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            if (width === 390)
                await pricing.screenshot({ path: `test-results/pricing-${locale}-mobile.png`, animations: 'disabled' });
        }
        await expect(page.locator('.hero-stage #contact')).toHaveCount(1);
        await expect(pricing.locator('.pricing-cta')).toHaveAttribute('href', '/register');
    }
    expect(mutations).toEqual([]);
    await page.locator('#pricing .pricing-cta').click();
    await expect(page).toHaveURL(/\/register$/);
    expect(errors).toEqual([]);
    const context = await browser.newContext({ javaScriptEnabled: false, reducedMotion: 'reduce' });
    const staticPage = await context.newPage();
    await explicitRonPricing(staticPage);
    for (const path of ['/', '/en']) {
        await staticPage.goto(path);
        await expect(staticPage.locator('#pricing article')).toHaveCount(3);
        await expect(staticPage.locator('#pricing')).toContainText('249');
        await expect(staticPage.locator('#pricing')).toContainText('GloriaFood');
        await expect(staticPage.locator('#pricing .pricing-cta')).toHaveAttribute('href', '/register');
    }
    await context.close();
});

// Preserve explicit language/currency intent in these existing regression scenarios.
test.beforeEach(async ({ page }) => {
    await explicitRonPricing(page);
});
