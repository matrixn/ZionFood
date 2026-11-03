import { test, expect } from '@playwright/test';

test('contact card is above the fold over the hero photo with separate floating examples and country search', async ({
    page,
}) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.emulateMedia({ reducedMotion: 'reduce' });
    for (const path of ['/', '/en']) {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(path);
        await expect(page.locator('.landing')).toHaveAttribute('data-enhanced', 'true');
        const contact = page.locator('.hero-stage #contact');
        await expect(contact).toHaveCount(1);
        await expect(page.locator('#contact')).toHaveCount(1);
        const card = (await contact.boundingBox())!;
        const button = (await contact.locator('button[type="submit"]').boundingBox())!;
        expect(card.y).toBeGreaterThan(0);
        expect(button.y + button.height).toBeLessThan(900);
        for (const selector of ['.floating-table', '.floating-reservation']) {
            const box = (await page.locator(selector).boundingBox())!;
            expect(box.y + box.height <= card.y || box.y >= card.y + card.height).toBe(true);
        }
        const country = contact.getByRole('combobox');
        await expect(country).toContainText('+40');
        await expect
            .poll(() => country.locator('img').evaluate((img) => (img as HTMLImageElement).naturalWidth))
            .toBeGreaterThan(0);
        await country.click();
        await page.getByRole('searchbox').fill('Germany');
        await page.getByRole('option', { name: 'Germany (+49)', exact: true }).click();
        await contact.locator('#lead-phone').fill('123');
        await contact.locator('#lead-email').focus();
        await expect(contact.locator('#lead-phone-error')).toBeVisible();
        await contact.locator('#lead-phone').fill('030901820');
        await expect(contact.locator('#lead-phone-error')).toHaveCount(0);
        await page.screenshot({
            path: `test-results/lead-hero-${path === '/' ? 'ro' : 'en'}-desktop.png`,
            animations: 'disabled',
        });
        for (const width of [768, 390, 360]) {
            await page.setViewportSize({ width, height: 844 });
            await page.evaluate(() => window.scrollTo(0, 0));
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            const name = (await contact.locator('#lead-name').boundingBox())!;
            expect(name.y + name.height).toBeLessThan(844);
            await page.screenshot({
                path: `test-results/lead-hero-${path === '/' ? 'ro' : 'en'}-${width}.png`,
                animations: 'disabled',
            });
        }
    }
    expect(errors).toEqual([]);
});
