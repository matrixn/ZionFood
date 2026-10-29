import { test, expect as baseExpect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const expect = baseExpect.configure({ timeout: 20000 });
const account = JSON.parse(readFileSync('storage/app/private/browser-availability.json', 'utf8'));

test('owner saves ordering protection, closed menu blocks early, explicit override allows only cart preparation', async ({ page }) => {
    test.setTimeout(120000);
    const errors: string[] = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    const settings = `/app/locations/${account.location}/general-settings`;
    await page.goto(settings);
    const control = page.getByRole('switch', { name: 'Blochează adăugarea în coș când locația nu este disponibilă', exact: true });
    await expect(control).toBeChecked();
    await page.goto(`/store/${account.location}`);
    await expect(page.getByTestId('ordering-status')).toContainText('Locația este închisă');
    await page.getByTestId('menu-product-card').first().click();
    await expect(page.getByRole('dialog').getByRole('button', { name: /Adaugă în coș/ })).toBeDisabled();
    await expect(page.getByRole('dialog')).toContainText('Comenzile sunt închise în afara programului.');
    await page.goto(settings);
    await control.uncheck();
    await page.getByRole('button', { name: 'Salvează', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Setările generale au fost salvate.' })).toBeVisible();
    await page.reload();
    await expect(control).not.toBeChecked();
    await page.goto(`/store/${account.location}`);
    await expect(page.getByTestId('ordering-status')).toContainText('Locația este închisă');
    await expect(page.getByTestId('ordering-status')).toContainText('Poți pregăti coșul acum');
    await page.getByTestId('menu-product-card').first().click();
    await page.getByRole('dialog').getByRole('button', { name: /Adaugă în coș/ }).click();
    await page.getByRole('button', { name: /Coșul tău/ }).click();
    await expect(page.getByRole('button', { name: 'Continuă la finalizare', exact: true })).toBeDisabled();
    await expect(page.getByRole('dialog')).toContainText('Comenzile sunt închise în afara programului.');
    await page.goto(`/app/locations/${account.location}/setup/general`);
    await control.check();
    await page.getByRole('button', { name: 'Salvează', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Setările generale au fost salvate.' })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/ordering-protection-settings.png', fullPage: true });
    expect(errors).toEqual([]);
});

function windowStatus(mode: 'online' | 'table', closed = false) {
    const now = Date.now();
    return {
        schedule: { is_open: !closed, opens_at: new Date(now - 3600_000).toISOString(), closes_at: new Date(now + (closed ? -1000 : 3600_000)).toISOString(), next_opens_at: new Date(now + 86400_000).toISOString(), checked_at: new Date(now).toISOString(), timezone: 'Europe/Bucharest' },
        can_order: !closed, can_add_to_cart: !closed, reason: closed ? 'outside_hours' : 'open', guard_enabled: true, mode,
        deadline_at: new Date(now + (closed ? -1000 : mode === 'table' ? 1800_000 : 3000_000)).toISOString(),
    };
}

test('live notices update online and QR menus, preserve carts and expose the last ordering hour in both languages', async ({ page }) => {
    test.setTimeout(90000);
    const errors: string[] = [];
    page.on('pageerror', e => errors.push(e.message));
    let closed = false;
    await page.route(`**/api/v1/public/locations/${account.location}/availability`, route => route.fulfill({ json: windowStatus('online', closed) }));
    await page.goto(`/store/${account.location}`);
    await expect(page.getByTestId('ordering-last-hour')).toContainText('50 min.');
    await page.getByTestId('menu-product-card').first().click();
    await page.getByRole('dialog').getByRole('button', { name: /Adaugă în coș/ }).click();
    closed = true;
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await expect(page.getByTestId('ordering-status')).toContainText('Locația este închisă');
    await page.getByRole('button', { name: /Coșul tău/ }).click();
    await expect(page.getByTestId('cart-line')).toHaveCount(1);
    await expect(page.getByRole('button', { name: 'Continuă la finalizare', exact: true })).toBeDisabled();
    await page.route(`**/api/v1/public/tables/${account.table}/availability`, route => route.fulfill({ json: windowStatus('table') }));
    await page.goto(`/table/${account.table}#token=${account.qr_token}`);
    await expect(page.getByTestId('ordering-last-hour')).toContainText('30 min.');
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/ordering-qr-notice-mobile.png', fullPage: true });
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByTestId('ordering-last-hour')).toContainText('30 min remaining.');
    await page.unrouteAll({ behavior: 'wait' });
    expect(errors).toEqual([]);
});
