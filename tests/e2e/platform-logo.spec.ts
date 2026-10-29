import { test, expect as baseExpect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const expect = baseExpect.configure({ timeout: 20000 });
const account = JSON.parse(readFileSync('storage/app/private/browser-account.json', 'utf8'));

test('the supplied logo renders across public, authentication, tenant, kitchen and storefront layouts', async ({
    page,
}) => {
    test.setTimeout(120000);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    async function visibleLogo() {
        const logo = page.getByTestId('zionfood-logo').filter({ visible: true }).first();
        await expect(logo).toBeVisible();
        await expect
            .poll(() => logo.evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0))
            .toBe(true);
        await expect(logo).toHaveAttribute('srcset', /128x128.png 128w/);
    }
    for (const path of ['/', '/en', '/login', '/register', '/forgot-password']) {
        await page.goto(path);
        await visibleLogo();
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await visibleLogo();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/logo-auth-mobile.png', animations: 'disabled' });
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.locator('form button[type="submit"]').click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    for (const suffix of ['', '/orders', '/kitchen']) {
        await page.goto(`/app/locations/${account.location}${suffix}`);
        await visibleLogo();
        await expect(page.getByRole('link', { name: 'ZionFood', exact: true })).toBeVisible();
    }
    await page.screenshot({ path: 'test-results/logo-kitchen-mobile.png', animations: 'disabled' });
    await page.goto(`/store/${account.location}`);
    await page.getByTestId('zionfood-logo').scrollIntoViewIfNeeded();
    await visibleLogo();
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/');
    await visibleLogo();
    await expect(page.locator('.landing')).toHaveAttribute('data-enhanced', 'true');
    await page.screenshot({ path: 'test-results/logo-landing.png', animations: 'disabled' });
    expect(errors).toEqual([]);
});
