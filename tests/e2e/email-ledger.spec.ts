import { test, expect as baseExpect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const expect = baseExpect.configure({ timeout: 20000 });
const account = JSON.parse(readFileSync('storage/app/private/browser-email-admin.json', 'utf8'));

test('platform admin filters outgoing emails and inspects metadata in both languages without sending', async ({ page }) => {
    test.setTimeout(90000);
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto('/admin/emails');
    await expect(page.getByRole('heading', { name: /^(Emailuri și coadă|Emails and queue)$/ })).toBeVisible();
    if ((await page.locator('html').getAttribute('lang')) !== 'ro') {
        await page.getByTestId('language-selector').click();
        await page.getByRole('option', { name: 'Română', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Emailuri și coadă', exact: true })).toBeVisible();
    }
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Emailuri și coadă', exact: true })).toBeVisible();
    await page.getByRole('textbox', { name: 'Destinatar exact', exact: true }).fill('ledger-fixture@example.test');
    await page.getByRole('button', { name: 'Aplică filtrele', exact: true }).click();
    await expect(page).toHaveURL(/recipient=ledger-fixture/);
    await page.getByRole('combobox', { name: 'Sursă', exact: true }).click();
    await page.getByRole('option', { name: 'Rezervare', exact: true }).click();
    await page.getByRole('button', { name: 'Aplică filtrele', exact: true }).click();
    await expect(page).toHaveURL(/source=reservation/);
    await expect(page.getByTestId('email-delivery-row').first()).toContainText('Local email fixture deferred');
    await page.getByRole('button', { name: 'Detalii email', exact: true }).first().click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('ledger-fixture@example.test');
    await expect(dialog).toContainText('Local email fixture deferred');
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(dialog.getByRole('button', { name: 'Închide', exact: true }).last()).toBeInViewport();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/email-ledger-mobile.png', fullPage: true });
    await dialog.getByRole('button', { name: 'Închide', exact: true }).last().click();
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Emails and queue', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Refresh', exact: true }).click();
    await expect(page.getByTestId('email-delivery-row').first()).toContainText('Local email fixture deferred');
    // Exercise quota feedback without contacting a real provider or changing its server configuration.
    const response = await page.request.get('/admin/emails/data?source=reservation&recipient=ledger-fixture%40example.test&page=1');
    const quotaData = await response.json();
    quotaData.provider.reason = 'daily_quota_exceeded';
    quotaData.provider.paused_until = quotaData.provider.reset_at;
    quotaData.provider.reserved = 100;
    await page.route('**/admin/emails/data?*', (route) => route.fulfill({ json: quotaData }));
    await page.getByRole('button', { name: 'Refresh', exact: true }).click();
    await expect(page.getByText('Resend confirmed daily quota exhaustion.', { exact: true }).first()).toBeVisible();
    await page.screenshot({ path: 'test-results/email-ledger-desktop.png', fullPage: true });
    await page.unrouteAll({ behavior: 'wait' });
    expect(errors).toEqual([]);
});
