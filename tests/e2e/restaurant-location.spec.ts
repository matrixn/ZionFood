import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('restaurant address lookup explains missing fields and saves a map pin without coordinate inputs', async ({
    page,
}) => {
    test.setTimeout(90000);
    const account = JSON.parse(readFileSync('storage/app/private/browser-account.json', 'utf8'));
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(`/app/locations/${account.location}/setup/basics`);
    await expect(page.getByRole('spinbutton', { name: 'Latitudine', exact: true })).toHaveCount(0);
    await expect(page.getByRole('spinbutton', { name: 'Longitudine', exact: true })).toHaveCount(0);
    let searches = 0;
    await page.route('**/setup/geocode', async (route) => {
        searches++;
        expect(route.request().postDataJSON()).toMatchObject({
            address: 'Calea Victoriei 49',
            city: 'București',
            country: 'RO',
        });
        await route.fulfill({
            json: {
                results: [
                    { label: 'Calea Victoriei 49, București', latitude: 44.439, longitude: 26.096, approximate: false },
                ],
            },
        });
    });
    await page.getByRole('textbox', { name: 'Adresa', exact: true }).fill('');
    await page.getByRole('textbox', { name: 'Localitate', exact: true }).fill('');
    await page.getByRole('button', { name: 'Localizează restaurantul', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('Adresa, Localitate');
    expect(searches).toBe(0);
    await page.getByRole('textbox', { name: 'Adresa', exact: true }).fill('Calea Victoriei 49');
    await page.getByRole('textbox', { name: 'Localitate', exact: true }).fill('București');
    await page.getByRole('button', { name: 'Localizează restaurantul', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Adresa a fost localizată.');
    const map = page.getByTestId('restaurant-map');
    await expect(map.locator('.zf-map-pin')).toBeVisible();
    await page.getByRole('textbox', { name: 'Telefonul locației', exact: true }).fill('0712345678');
    await page.getByRole('button', { name: 'Salvează pasul', exact: true }).click();
    await expect(
        page.getByRole('status').filter({ hasText: 'Configurarea restaurantului a fost salvată.' }),
    ).toBeVisible();
    await page.reload();
    await expect(map.locator('.zf-map-pin')).toBeVisible();
    await map.scrollIntoViewIfNeeded();
    const box = await map.boundingBox();
    if (!box) throw new Error('Missing map');
    await page.mouse.click(box.x + box.width * 0.6, box.y + box.height * 0.5);
    await expect(page.getByRole('status')).toContainText('Pinul a fost ajustat.');
    await page.getByRole('button', { name: 'Salvează pasul', exact: true }).click();
    await expect(
        page.getByRole('status').filter({ hasText: 'Configurarea restaurantului a fost salvată.' }),
    ).toBeVisible();
    await map.screenshot({ path: 'test-results/restaurant-location-map.png' });
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Locate restaurant', exact: true })).toBeVisible();
    expect(errors).toEqual([]);
});

test('ambiguous and unavailable address searches do not silently select a restaurant', async ({ page }) => {
    const account = JSON.parse(readFileSync('storage/app/private/browser-account.json', 'utf8'));
    await page.goto('/login');
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(`/app/locations/${account.location}/setup/basics`);
    await page.route('**/setup/geocode', async (route) =>
        route.fulfill({
            json: {
                results: [
                    { label: 'First address', latitude: 44.439, longitude: 26.096, approximate: false },
                    { label: 'Second address', latitude: 44.44, longitude: 26.1, approximate: true },
                ],
            },
        }),
    );
    await page.getByRole('textbox', { name: 'Street address', exact: true }).fill('New address 20');
    await page.getByRole('textbox', { name: 'City', exact: true }).fill('București');
    await page.getByRole('button', { name: 'Locate restaurant', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('several matches');
    await expect(page.getByTestId('restaurant-map').locator('.zf-map-pin')).toHaveCount(0);
    await page.getByRole('button', { name: 'Second address', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('approximate position');
    await expect(page.getByTestId('restaurant-map').locator('.zf-map-pin')).toBeVisible();
    await page.getByRole('textbox', { name: 'Street address', exact: true }).fill('Another address 30');
    await expect(page.getByTestId('restaurant-map').locator('.zf-map-pin')).toHaveCount(0);
    await page.unroute('**/setup/geocode');
    await page.route('**/setup/geocode', (route) => route.fulfill({ json: { results: [] } }));
    await page.getByRole('button', { name: 'Locate restaurant', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('could not find this address');
    await page.unroute('**/setup/geocode');
    await page.route('**/setup/geocode', (route) => route.fulfill({ status: 422, json: { errors: { location: ['Address search is temporarily unavailable.'] } } }));
    await page.getByRole('button', { name: 'Locate restaurant', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('temporarily unavailable');
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'Română', exact: true }).click();
});
