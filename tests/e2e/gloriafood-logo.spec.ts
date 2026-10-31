import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('previews both logos, keeps the original by default and applies only an approved replacement', async ({
    page,
}) => {
    test.skip(process.env.ZF_LOGO_E2E !== '1', 'Requires the isolated logo fixture and public-provider HTTP fake.');
    test.setTimeout(120000);
    const account = JSON.parse(readFileSync(process.env.ZF_MIGRATION_FIXTURE!, 'utf8'));
    const restaurant = JSON.parse(readFileSync(process.env.ZF_SETTINGS_SOURCE!, 'utf8')).restaurant;
    const source = { ...restaurant.menu, restaurant_id: restaurant.id };
    const base = `/app/locations/${account.location}/migration-center`;
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(base);
    await page.getByRole('combobox', { name: 'Sursa meniului', exact: true }).click();
    await page.getByRole('option', { name: 'Fișier JSON exportat anterior (max. 5 MB)', exact: true }).click();
    await page.locator('input[type=file]').setInputFiles({
        name: 'menu.json',
        mimeType: 'application/json',
        buffer: Buffer.from(JSON.stringify(source)),
    });
    await page
        .getByRole('textbox', { name: 'Link meniu public GloriaFood — opțional', exact: true })
        .fill(
            'https://www.foodbooking.com/ordering/restaurant/menu?restaurant_uid=ca5549f4-4c22-4fd1-9c51-3bd227b20297',
        );
    await page
        .getByRole('checkbox', { name: 'Am dreptul de a importa meniul acestui restaurant.', exact: true })
        .check();
    for (const selected of [false, true]) {
        const started = page.waitForResponse((r) => r.request().method() === 'POST' && r.url().endsWith(base));
        await page.getByRole('button', { name: 'Preia și verifică', exact: true }).click();
        const batch = (await (await started).json()).batch_id;
        await expect(page.getByRole('heading', { name: 'Pregătit pentru verificare', exact: true })).toBeVisible();
        const card = page.locator('[data-setting=logo]');
        const choice = card.getByRole('checkbox', { name: 'Preia logo-ul', exact: true });
        await expect(choice).not.toBeChecked();
        await expect(choice).toBeEnabled();
        const oldImage = card.getByRole('img', { name: 'Logo existent al restaurantului', exact: true });
        const newImage = card.getByRole('img', { name: 'Logo propus din GloriaFood', exact: true });
        for (const image of [oldImage, newImage])
            await expect.poll(() => image.evaluate((el) => (el as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);
        const before = await (await page.request.get(`${base}/${batch}`)).json();
        expect(before.settings_preview.find((f: { key: string }) => f.key === 'logo').current).toBe(
            'branding/test-existing.png',
        );
        await card.scrollIntoViewIfNeeded();
        if (selected) {
            await choice.check();
            await page.screenshot({ path: 'test-results/migration-logo-desktop.png' });
            await page.setViewportSize({ width: 390, height: 844 });
            await card.scrollIntoViewIfNeeded();
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
            await page.screenshot({ path: 'test-results/migration-logo-mobile.png' });
            await page.setViewportSize({ width: 1280, height: 720 });
        }
        await page
            .getByRole('checkbox', {
                name: 'Am verificat prețurile, dimensiunile, regulile, alergenii, nutriția și avertismentele.',
                exact: true,
            })
            .check();
        const label = selected ? 'Publică meniul și datele selectate' : 'Publică meniul';
        await page.getByRole('button', { name: label, exact: true }).click();
        const dialog = page.getByRole('dialog');
        if (selected) await expect(dialog).toContainText('Logo restaurant');
        await dialog.getByRole('button', { name: label, exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Publicat', exact: true })).toBeVisible();
        const after = await (await page.request.get(`${base}/${batch}`)).json();
        const logo = after.settings_preview.find((f: { key: string }) => f.key === 'logo');
        if (selected) {
            expect(logo.current).not.toBe('branding/test-existing.png');
            expect(Object.keys(after.report.settings_changes)).toEqual(['logo']);
            await page.getByRole('button', { name: 'Revino la meniul anterior', exact: true }).click();
            await page
                .getByRole('dialog')
                .getByRole('button', { name: 'Revino la meniul anterior', exact: true })
                .click();
            await expect(
                page.getByRole('heading', { name: 'Revenit la versiunea anterioară', exact: true }),
            ).toBeVisible();
            const reverted = await (await page.request.get(`${base}/${batch}`)).json();
            expect(reverted.settings_preview.find((f: { key: string }) => f.key === 'logo').current).toBe(
                'branding/test-existing.png',
            );
        } else {
            expect(logo.current).toBe('branding/test-existing.png');
            expect(after.report.settings_changes).toBeUndefined();
        }
    }
    expect(errors).toEqual([]);
});
