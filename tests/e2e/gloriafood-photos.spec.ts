import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

test('optional public menu URL stages real photos in queued chunks with automatic preview progress', async ({
    page,
}) => {
    test.skip(
        process.env.ZF_PHOTOS_E2E !== '1',
        'Requires a dedicated private database, public source fixture and worker.',
    );
    test.setTimeout(180000);
    const account = JSON.parse(readFileSync(process.env.ZF_MIGRATION_FIXTURE!, 'utf8'));
    const restaurant = JSON.parse(readFileSync(process.env.ZF_PHOTOS_SOURCE!, 'utf8')).restaurant;
    const source = { ...restaurant.menu, restaurant_id: restaurant.id };
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(`/app/locations/${account.location}/migration-center`);
    await page.getByRole('combobox', { name: 'Sursa meniului', exact: true }).click();
    await page.getByRole('option', { name: 'Fișier JSON exportat anterior (max. 5 MB)', exact: true }).click();
    await page.locator('input[type=file]').setInputFiles({
        name: 'public-menu-fixture.json',
        mimeType: 'application/json',
        buffer: Buffer.from(JSON.stringify(source)),
    });
    const link = page.getByRole('textbox', { name: 'Link meniu public GloriaFood — opțional', exact: true });
    await expect(link).toHaveValue('');
    await link.fill('https://example.com/menu');
    await page
        .getByRole('checkbox', { name: 'Am dreptul de a importa meniul acestui restaurant.', exact: true })
        .check();
    await page.getByRole('button', { name: 'Preia și verifică', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('FoodBooking');
    await link.fill(
        'https://www.foodbooking.com/ordering/restaurant/menu?restaurant_uid=ca5549f4-4c22-4fd1-9c51-3bd227b20297',
    );
    await link.focus();
    await page.keyboard.press('F1');
    await expect(page.getByRole('tooltip')).toContainText('restaurant_uid');
    await page.keyboard.press('Escape');
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('combobox', { name: 'Cum sunt exprimate prețurile dimensiunilor?', exact: true }),
    ).toBeFocused();
    await page.getByRole('button', { name: 'Preia și verifică', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Pregătim fotografiile', exact: true })).toBeVisible({
        timeout: 30000,
    });
    const worker = promisify(execFile)(
        'php',
        ['-d', `curl.cainfo=${process.env.ZF_PHOTOS_CA!}`, process.env.ZF_PHOTOS_WORKER!],
        { timeout: 120000 },
    );
    await expect(page.getByRole('heading', { name: 'Pregătit pentru verificare', exact: true })).toBeVisible({
        timeout: 120000,
    });
    await worker;
    const photos = page.getByTestId('migration-photos');
    await expect(photos.locator('dd').nth(0)).toHaveText('48');
    await expect(photos.locator('dd').nth(1)).toHaveText('48');
    await expect(photos.locator('dd').nth(3)).toHaveText('0');
    await expect(photos).toContainText('La Trattoria Valcea');
    const photo = page.getByRole('img', { name: 'Bruschete clasice', exact: true });
    await photo.scrollIntoViewIfNeeded();
    await expect.poll(() => photo.evaluate((image) => (image as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);
    await page.screenshot({ path: 'test-results/gloriafood-photos-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await photos.scrollIntoViewIfNeeded();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await page.screenshot({ path: 'test-results/gloriafood-photos-mobile.png', fullPage: true });
    expect(errors).toEqual([]);
});
