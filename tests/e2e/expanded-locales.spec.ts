import { explicitRonPricing } from './support/pricingPreference';
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const locales = [
    { code: 'it', name: 'Italiano', title: 'Il tuo ristorante.', basics: 'Nome e indirizzo', save: 'Salva' },
    { code: 'es', name: 'Español', title: 'Tu restaurante.', basics: 'Nombre y dirección', save: 'Guardar' },
    { code: 'nl', name: 'Nederlands', title: 'Jouw restaurant.', basics: 'Naam en adres', save: 'Opslaan' },
] as const;

test('new marketing languages have stable URLs, translated content, flags and reciprocal metadata', async ({
    page,
    browser,
}) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/');
    for (const locale of locales) {
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.getByTestId('language-selector').click();
        await page.getByRole('option', { name: locale.name, exact: true }).click();
        await expect(page).toHaveURL(new RegExp('/' + locale.code + '$'));
        await expect(page.locator('html')).toHaveAttribute('lang', locale.code);
        await expect(page.locator('h1')).toContainText(locale.title);
        await expect(page.locator('head link[rel="alternate"][hreflang]')).toHaveCount(6);
        await expect(page.locator('head link[rel="canonical"]')).toHaveAttribute(
            'href',
            new RegExp('/' + locale.code + '$'),
        );
        await expect(page.locator('.footer-languages a')).toHaveCount(5);
        await expect
            .poll(() =>
                page
                    .getByTestId('language-selector')
                    .locator('img')
                    .evaluate((n) => (n as HTMLImageElement).naturalWidth),
            )
            .toBeGreaterThan(0);
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('lang', locale.code);
        for (const width of [390, 360]) {
            await page.setViewportSize({ width, height: 844 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            await expect(page.getByTestId('language-selector')).toContainText(locale.name);
            const bounds = (await page.locator('.nav-actions').boundingBox())!;
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
            await page.screenshot({
                path: `test-results/localized-landing-${locale.code}-${width}.png`,
                animations: 'disabled',
            });
        }
    }
    const context = await browser.newContext({ javaScriptEnabled: false });
    const staticPage = await context.newPage();
    for (const locale of locales) {
        await staticPage.goto('/' + locale.code);
        await expect(staticPage.locator('h1')).toContainText(locale.title);
        await expect(staticPage.locator('#pricing article')).toHaveCount(3);
        await expect(staticPage.locator('head link[hreflang="x-default"]')).toHaveCount(1);
    }
    await context.close();
    expect(errors).toEqual([]);
});

test('restaurant locale persists through navigation while general administration stays independently RO EN', async ({
    page,
}) => {
    test.skip(process.env.ZF_LOCALES_E2E !== '1', 'Requires isolated multilingual workspace fixture.');
    test.setTimeout(90000);
    const account = JSON.parse(readFileSync(process.env.ZF_LOCALES_ACCOUNT!, 'utf8'));
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    const base = `/app/locations/${account.location}/setup/basics`;
    await page.goto(base);
    for (const locale of locales) {
        await page.getByTestId('language-selector').click();
        await page.getByRole('option', { name: locale.name, exact: true }).click();
        await expect(page.locator('html')).toHaveAttribute('lang', locale.code);
        await expect(page.getByTestId('setup-step-heading')).toHaveText(locale.basics);
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('lang', locale.code);
        await expect(page.getByTestId('setup-step-heading')).toHaveText(locale.basics);
        await page.screenshot({ path: `test-results/localized-restaurant-${locale.code}.png`, animations: 'disabled' });
    }
    await page.locator('header a[href="/admin"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ro');
    await page.getByTestId('language-selector').click();
    await expect(page.getByRole('option')).toHaveCount(2);
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await page.locator('header a[href="/app"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'nl');
    await expect(page.getByTestId('language-selector')).toContainText('Nederlands');
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.goto('/admin');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    expect(errors).toEqual([]);
});

// Preserve explicit language/currency intent in these existing regression scenarios.
test.beforeEach(async ({ page }) => {
    await explicitRonPricing(page);
});
