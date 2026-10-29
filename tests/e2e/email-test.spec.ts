import { test, expect } from '@playwright/test';

test('guest can inspect the temporary public email test page and switch languages without an external send', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/test-email');
    await expect(page).toHaveURL(/\/test-email$/);
    await expect(page.getByRole('heading', { name: 'Testare email', exact: true })).toBeVisible();
    await expect(page.getByText('dummy@dummy.com', { exact: false })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Trimite email de test', exact: false })).toBeVisible();
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Test email', exact: true })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/email-test-mobile.png', fullPage: true });
    expect(errors).toEqual([]);
});
