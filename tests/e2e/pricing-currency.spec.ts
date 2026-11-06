import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('IP country controls defaults independently of browser timezone with English EUR fallback and manual priority', async ({
    browser,
}) => {
    test.skip(process.env.ZF_PRICING_E2E !== '1', 'Requires isolated pricing database and saved provider quotes.');
    for (const [ip, timezoneId, locale, path, currency] of [
        ['151.24.0.1', 'Europe/Bucharest', 'en-US', '/it', 'EUR'],
        ['81.2.69.160', 'Europe/Rome', 'it-IT', '/en', 'GBP'],
        ['8.8.8.8', 'Europe/Amsterdam', 'nl-NL', '/en', 'EUR'],
        ['86.120.0.1', 'America/New_York', 'en-US', '/', 'RON'],
    ]) {
        const context = await browser.newContext({ timezoneId, locale, extraHTTPHeaders: { 'X-ZF-Test-IP': ip } });
        const page = await context.newPage();
        await page.goto('/', { waitUntil: 'commit' });
        await expect(page).toHaveURL(new RegExp(path === '/' ? '/$' : path + '$'));
        await expect(page.getByTestId('currency-selector')).toContainText(currency);
        await expect(page.getByTestId('currency-selector')).not.toHaveAttribute('data-p-disabled', 'true');
        await page.reload();
        await expect(page).toHaveURL(new RegExp(path === '/' ? '/$' : path + '$'));
        await expect(page.getByTestId('currency-selector')).toContainText(currency);
        if (ip === '151.24.0.1') {
            await context.setExtraHTTPHeaders({ 'X-ZF-Test-IP': '81.2.69.160' });
            await page.goto('/', { waitUntil: 'commit' });
            await expect(page).toHaveURL(/\/en$/);
            await expect(page.getByTestId('currency-selector')).toContainText('GBP');
        }
        if (ip === '81.2.69.160') {
            await page.getByTestId('language-selector').click();
            await Promise.all([
                page.waitForResponse(
                    (response) => response.url().endsWith('/locale') && response.request().method() === 'POST',
                ),
                page.getByRole('option', { name: 'English', exact: true }).click(),
            ]);
            await expect(page).toHaveURL(/\/en$/);
            await context.setExtraHTTPHeaders({ 'X-ZF-Test-IP': '151.24.0.1' });
            await page.goto('/', { waitUntil: 'commit' });
            await expect(page).toHaveURL(/\/en$/);
            await expect(page.getByTestId('currency-selector')).toContainText('EUR');
        }
        await context.close();
    }
    const context = await browser.newContext({
        timezoneId: 'Europe/Rome',
        locale: 'it-IT',
        viewport: { width: 390, height: 844 },
        extraHTTPHeaders: { 'X-ZF-Test-IP': '151.24.0.1' },
    });
    const page = await context.newPage();
    await page.goto('/en'); // explicit language URLs must never be redirected by country detection
    await expect(page.getByTestId('currency-selector')).toContainText('EUR');
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'Nederlands', exact: true }).click();
    await expect(page).toHaveURL(/\/nl$/);
    await page.getByTestId('currency-selector').click();
    const response = page.waitForResponse(
        (r) => r.url().endsWith('/pricing/preference') && r.request().method() === 'POST',
    );
    await page.getByRole('option', { name: 'GBP', exact: true }).click();
    const quote = await (await response).json();
    await expect(page.locator('[data-plan="menu"] data')).toHaveAttribute(
        'value',
        String(quote.plans[0].display_minor / 100),
    );
    await context.setExtraHTTPHeaders({ 'X-ZF-Test-IP': '86.120.0.1' });
    await page.goto('/', { waitUntil: 'commit' });
    await expect(page).toHaveURL(/\/nl$/);
    await expect(page.getByTestId('currency-selector')).toContainText('GBP');
    await expect(page.locator('.pricing-quote-note')).toContainText('RON');
    for (const width of [390, 360]) {
        await page.setViewportSize({ width, height: 844 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    await page.locator('#pricing').screenshot({ path: 'test-results/pricing-currency-mobile.png' });
    await page.locator('.footer-languages a[lang="es"]').click();
    await expect(page).toHaveURL(/\/es$/);
    await context.setExtraHTTPHeaders({ 'X-ZF-Test-IP': '81.2.69.160' });
    await page.goto('/', { waitUntil: 'commit' });
    await expect(page).toHaveURL(/\/es$/);
    await expect(page.getByTestId('currency-selector')).toContainText('GBP');
    await context.close();
});

test('platform admin edits package names and RON prices, then readers see current values without rebuilding', async ({
    page,
    browser,
}) => {
    test.skip(process.env.ZF_PRICING_E2E !== '1', 'Requires private administrator fixture.');
    const account = JSON.parse(readFileSync(process.env.ZF_LOCALES_ACCOUNT!, 'utf8'));
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto('/login');
    await page.locator('input[type="email"]').fill(account.email);
    await page.locator('input[type="password"]').fill(account.password);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(/\/app/);
    await page.goto('/admin/pricing');
    await expect(page.locator('#plan-name-menu')).toHaveValue('Menu');
    await page.locator('#plan-name-menu').fill('Menu Starter');
    await page.locator('#plan-price-menu').fill('59,95');
    await page.locator('button[type="submit"]').click();
    await expect(page.getByRole('alert')).toContainText(/salvate|saved/i);
    await page.reload();
    await expect(page.locator('#plan-name-menu')).toHaveValue('Menu Starter');
    await expect(page.locator('#plan-price-menu')).toHaveValue('59.95');
    await page.screenshot({ path: 'test-results/pricing-admin-desktop.png', fullPage: true });
    const context = await browser.newContext({ javaScriptEnabled: false });
    const reader = await context.newPage();
    const { explicitRonPricing } = await import('./support/pricingPreference');
    await explicitRonPricing(reader);
    await reader.goto('/en');
    await expect(reader.locator('[data-plan="menu"] h3')).toHaveText('Menu Starter');
    await expect(reader.locator('[data-plan="menu"] data')).toHaveAttribute('value', '59.95');
    await context.close();
    // Restore the fixture for the existing 49/149/249 presentation regression.
    await page.locator('#plan-name-menu').fill('Menu');
    await page.locator('#plan-price-menu').fill('49.00');
    await page.locator('button[type="submit"]').click();
    await expect(page.getByRole('alert')).toContainText(/salvate|saved/i);
    expect(errors).toEqual([]);
});
