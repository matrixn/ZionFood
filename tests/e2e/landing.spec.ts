import { test, expect as baseExpect } from '@playwright/test';
const expect = baseExpect.configure({ timeout: 20000 });

test('landing page presents both languages, real CTAs and isolated interactive previews', async ({ page }) => {
    test.setTimeout(90000);
    await page.setViewportSize({ width: 1440, height: 1000 });
    const errors: string[] = [];
    const mutations: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('request', (request) => {
        if (request.method() !== 'GET' && request.method() !== 'HEAD' && !request.url().endsWith('/locale'))
            mutations.push(request.url());
    });
    await page.goto('/');
    await expect(page.locator('.landing')).toHaveAttribute('data-enhanced', 'true');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Restaurantul tău.');
    await expect(page.getByRole('link', { name: 'Începe cu ZionFood' }).first()).toHaveAttribute('href', '/register');
    await expect(page.locator('.nav-login')).toHaveAttribute('href', '/login');
    await expect(page.locator('.hero-photo img')).toHaveJSProperty('complete', true);
    expect(
        await page.locator('.hero-photo img').evaluate((image: HTMLImageElement) => image.naturalWidth),
    ).toBeGreaterThan(0);
    await page.screenshot({ path: 'test-results/landing-hero-desktop.png', animations: 'disabled' });
    await page.getByRole('tab', { name: /Un loc pentru fiecare/ }).click();
    await expect(page.getByRole('tabpanel')).toContainText('O masă, pregătită pentru ei.');
    await page.getByRole('tab', { name: /Un loc pentru fiecare/ }).press('End');
    await expect(page.getByRole('tab', { name: /Livrare în zona ta/ })).toBeFocused();
    await expect(page.getByRole('tabpanel')).toContainText('Tu trasezi limitele.');
    await page.getByRole('tab', { name: /Livrare în zona ta/ }).press('ArrowUp');
    await expect(page.getByRole('tabpanel')).toContainText('Un cod permanent');
    await page.getByRole('tab', { name: /Meniul tău, online/ }).click();
    await expect(page.getByRole('tabpanel')).toContainText('Meniul de astăzi');
    await page.locator('#platform').screenshot({ path: 'test-results/landing-platform.png', animations: 'disabled' });
    await page.getByTestId('landing-next-step').click();
    await expect(page.getByRole('status')).toHaveText('Bucătăria pregătește comanda.');
    await page.getByTestId('landing-next-step').click();
    await expect(page.getByRole('status')).toHaveText('Gata de servire. Poftă bună!');
    await page.getByTestId('landing-next-step').click();
    await expect(page.getByRole('status')).toContainText('O comandă nouă');
    await page.locator('#workflow').screenshot({ path: 'test-results/landing-workflow.png', animations: 'disabled' });
    await page.locator('summary').filter({ hasText: 'Pot încasa plăți online acum?' }).click();
    await expect(page.getByText('Nu încă. Procesarea plăților online', { exact: false })).toBeVisible();
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Your restaurant.');
    await page.reload();
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Your restaurant.');
    await expect(page).toHaveTitle(/Restaurant digital menus, QR orders and reservations/);
    expect(await page.locator('html').getAttribute('lang')).toBe('en');
    await page.locator('.kitchen-story').scrollIntoViewIfNeeded();
    await expect(page.locator('.kitchen-story img')).toHaveJSProperty('complete', true);
    expect(
        await page.locator('.kitchen-story img').evaluate((image: HTMLImageElement) => image.naturalWidth),
    ).toBeGreaterThan(0);
    await page
        .locator('.kitchen-story')
        .screenshot({ path: 'test-results/landing-kitchen.png', animations: 'disabled' });
    await page.getByRole('link', { name: 'Start with ZionFood' }).last().click();
    await expect(page).toHaveURL(/\/register$/);
    expect(errors).toEqual([]);
    expect(mutations).toEqual([]);
});

test('mobile landing has working navigation and respects reduced motion', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    await page.goto('/');
    await expect(page.locator('.landing')).toHaveAttribute('data-enhanced', 'true');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Restaurantul tău.');
    expect(await page.locator('.floating-table').evaluate((element) => getComputedStyle(element).animationName)).toBe(
        'none',
    );
    await expect(page.getByTestId('language-selector')).toBeVisible();
    await page.getByRole('button', { name: 'Deschide sau închide navigarea' }).click();
    await expect(page.locator('#mobile-navigation')).toBeVisible();
    await page.locator('#mobile-navigation a[href="#platform"]').click();
    await expect(page.locator('#mobile-navigation')).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page
        .locator('#platform')
        .screenshot({ path: 'test-results/landing-platform-mobile.png', animations: 'disabled' });
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: 'test-results/landing-hero-mobile.png', animations: 'disabled' });
    await page.setViewportSize({ width: 320, height: 800 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Your restaurant.');
    await context.close();
});
