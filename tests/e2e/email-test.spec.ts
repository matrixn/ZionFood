import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('platform admin can inspect email test page and switch languages without an external send', async ({ page }) => {
    const account = JSON.parse(readFileSync('storage/app/private/browser-email-admin.json', 'utf8')) as { email: string; password: string };
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/test-email');
    await expect(page).toHaveURL(/\/login/);
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/test-email$/);
    await expect(page.getByRole('heading', { name: 'Testare email', exact: true })).toBeVisible();
    await expect(page.getByText('dummy@dummy.com', { exact: false })).toBeVisible();
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Test email', exact: true })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/email-test-mobile.png', fullPage: true });
    expect(errors).toEqual([]);
});
