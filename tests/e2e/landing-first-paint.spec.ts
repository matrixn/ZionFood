import { test, expect as baseExpect } from '@playwright/test';
const expect = baseExpect.configure({ timeout: 20000 });

test('landing CSS is present without JavaScript and all graphics retain bounded dimensions', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false, reducedMotion: 'reduce' });
    const page = await context.newPage();
    for (const path of ['/', '/en']) {
        await page.goto(path);
        await expect(page.locator('head link[data-landing-style]')).toHaveCount(1);
        expect(await page.locator('.hero').evaluate((element) => getComputedStyle(element).display)).toBe('grid');
        expect(
            await page.locator('h1').evaluate((element) => parseFloat(getComputedStyle(element).fontSize)),
        ).toBeGreaterThan(40);
        expect(
            await page
                .locator('svg')
                .evaluateAll((nodes) => nodes.every((node) => node.getBoundingClientRect().height < 400)),
        ).toBe(true);
    }
    await page.screenshot({ path: 'test-results/first-paint-no-js.png', animations: 'disabled' });
    await context.close();
});

test('blocked CSS and JavaScript leave readable text without giant decorative SVGs or images', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    await context.route('**/*', (route) =>
        ['stylesheet', 'script', 'font'].includes(route.request().resourceType()) ? route.abort() : route.continue(),
    );
    const page = await context.newPage();
    for (const path of ['/', '/en']) {
        await page.goto(path);
        await expect(page.locator('h1')).toBeVisible();
        await expect(page.locator('a[href="/register"]').first()).toBeVisible();
        expect(
            await page
                .locator('svg[data-decoration]')
                .evaluateAll((nodes) => nodes.every((node) => getComputedStyle(node).visibility === 'hidden')),
        ).toBe(true);
        expect(
            await page
                .locator('.landing img')
                .evaluateAll((nodes) => nodes.every((node) => node.getBoundingClientRect().width <= innerWidth)),
        ).toBe(true);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    await page.screenshot({ path: 'test-results/first-paint-no-css-mobile.png', animations: 'disabled' });
    await context.close();
});

test('failed icon fonts never reveal fallback glyphs while the interactive page remains usable', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.route('**/*', (route) => (route.request().resourceType() === 'font' ? route.abort() : route.continue()));
    await page.goto('/');
    await expect(page.locator('.landing')).toHaveAttribute('data-enhanced', 'true');
    await page.evaluate(() => document.fonts.ready);
    await expect(page.locator('.landing')).not.toHaveClass(/icons-ready/);
    expect(
        await page
            .locator('.landing .pi')
            .evaluateAll((nodes) => nodes.every((node) => getComputedStyle(node, '::before').visibility === 'hidden')),
    ).toBe(true);
    await page.locator('[role="tab"]').last().click();
    await expect(page.locator('[role="tab"]').last()).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('.delivery-map-art svg')).toBeVisible();
    await page.screenshot({ path: 'test-results/first-paint-no-font.png', animations: 'disabled' });
    await page.unrouteAll({ behavior: 'wait' });
    await page.reload();
    await expect(page.locator('.landing')).toHaveClass(/icons-ready/);
    expect(
        await page
            .locator('.landing .pi')
            .first()
            .evaluate((node) => getComputedStyle(node, '::before').visibility),
    ).toBe('visible');
});
