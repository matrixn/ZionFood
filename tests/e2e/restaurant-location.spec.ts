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


test('pin fills the address without moving it, saves it and centers delivery zones on the restaurant', async ({ page }) => {
    test.setTimeout(90000);
    const account = JSON.parse(readFileSync('storage/app/private/browser-account.json', 'utf8'));
    await page.goto('/login');
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'Română', exact: true }).click();
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(`/app/locations/${account.location}/setup/basics`);
    await page.getByRole('textbox', { name: 'Adresa', exact: true }).fill('');
    await page.getByRole('textbox', { name: 'Localitate', exact: true }).fill('');
    const button = page.getByRole('button', { name: 'Preia adresa de la pin', exact: true });
    await expect(button).toBeDisabled();
    const map = page.getByTestId('restaurant-map');
    await map.scrollIntoViewIfNeeded();
    const box = await map.boundingBox();
    if (!box) throw new Error('Map missing');
    await page.mouse.click(box.x + box.width * .55, box.y + box.height * .55);
    await expect(button).toBeEnabled();
    let pin: {latitude: number; longitude: number} | undefined;
    await page.route('**/setup/reverse-geocode', async route => {
        pin = route.request().postDataJSON();
        await route.fulfill({ json: { address: { address: 'Calea Victoriei 49', city: 'București', country: 'RO', postal_code: '010063', missing: [] } } });
    });
    await button.click();
    await expect(page.getByRole('textbox', { name: 'Adresa', exact: true })).toHaveValue('Calea Victoriei 49');
    await expect(page.getByRole('textbox', { name: 'Localitate', exact: true })).toHaveValue('București');
    await expect(page.getByRole('textbox', { name: 'Cod poștal', exact: true })).toHaveValue('010063');
    await expect(map.locator('.zf-map-pin')).toBeVisible();
    await page.getByRole('textbox', { name: 'Telefonul locației', exact: true }).fill('0712345678');
    const saved = page.waitForRequest(r => r.method() === 'PUT' && r.url().endsWith('/setup/basics'));
    await page.getByRole('button', { name: 'Salvează pasul', exact: true }).click();
    expect((await saved).postDataJSON()).toMatchObject(pin!);
    await expect(page.getByRole('status').filter({ hasText: 'Configurarea restaurantului a fost salvată.' })).toBeVisible();
    await page.reload();
    await expect(page.getByRole('textbox', { name: 'Adresa', exact: true })).toHaveValue('Calea Victoriei 49');
    await expect(map.locator('.zf-map-pin')).toBeVisible();
    await page.goto(`/app/locations/${account.location}/settings`);
    let forwardCalls = 0;
    await page.route('**/setup/geocode', route => { forwardCalls++; return route.fulfill({ json: { results: [] } }); });
    await page.getByRole('button', { name: 'Adaugă zonă', exact: true }).click();
    const zone = page.getByTestId('zone-map');
    await expect(zone.locator('.zf-restaurant-reference')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Centrează pe restaurant', exact: true })).toBeVisible();
    const initial = await zone.locator('.leaflet-tile').first().getAttribute('src');
    expect(initial).toContain('/16/');
    await page.getByRole('button', { name: 'Centrează pe restaurant', exact: true }).click();
    expect(forwardCalls).toBe(0);
    await page.screenshot({ path: 'test-results/pin-delivery-center.png', fullPage: true });
});

test('pin lookup handles partial, absent and stale addresses and address-only delivery centering', async ({ page }) => {
    test.setTimeout(90000);
    const account = JSON.parse(readFileSync('storage/app/private/browser-account.json', 'utf8'));
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(`/app/locations/${account.location}/setup/basics`);
    const map = page.getByTestId('restaurant-map');
    await expect(map.locator('.zf-map-pin')).toBeVisible();
    await page.route('**/setup/reverse-geocode', route => route.fulfill({ json: { address: { address: 'Calea Victoriei', city: 'București', country: 'RO', postal_code: '', missing: ['house_number', 'postal_code'] } } }));
    const button = page.getByRole('button', { name: 'Preia adresa de la pin', exact: true });
    await button.click();
    await expect(page.getByRole('status').filter({ hasText: 'Număr stradal, Cod poștal' })).toBeVisible();
    await expect(map.locator('.zf-map-pin')).toBeVisible();
    await page.unroute('**/setup/reverse-geocode');
    await page.route('**/setup/reverse-geocode', route => route.fulfill({ json: { address: null } }));
    await button.click();
    await expect(page.getByRole('alert')).toContainText('Nu am găsit o adresă');
    await expect(page.getByRole('textbox', { name: 'Adresa', exact: true })).toHaveValue('Calea Victoriei');
    await page.unroute('**/setup/reverse-geocode');
    let release!: () => void;
    const gate = new Promise<void>(resolve => { release = resolve; });
    let requested!: () => void;
    const started = new Promise<void>(resolve => { requested = resolve; });
    await page.route('**/setup/reverse-geocode', async route => { requested(); await gate; await route.fulfill({ json: { address: { address: 'Stale address', city: 'Wrong city', country: 'DE', postal_code: '', missing: [] } } }); });
    await button.click();
    await started;
    await map.scrollIntoViewIfNeeded();
    const box = await map.boundingBox();
    if (!box) throw new Error('Map missing');
    await page.mouse.click(box.x + box.width * .6, box.y + box.height * .6);
    const response = page.waitForResponse('**/setup/reverse-geocode');
    release();
    await response;
    await expect(button).toBeEnabled();
    await expect(page.getByRole('textbox', { name: 'Adresa', exact: true })).toHaveValue('Calea Victoriei');
    await page.getByRole('button', { name: 'Elimină pinul', exact: true }).click();
    await page.getByRole('button', { name: 'Salvează pasul', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Configurarea restaurantului a fost salvată.' })).toBeVisible();
    let calls = 0;
    await page.route('**/setup/geocode', async route => {
        calls++;
        expect(route.request().postDataJSON()).toMatchObject({ address: 'Calea Victoriei', city: 'București' });
        await route.fulfill({ json: { results: [{ latitude: 45.1, longitude: 24.36, label: 'Restaurant address' }, { latitude: 44.4, longitude: 26.1, label: 'Other address' }] } });
    });
    await page.goto(`/app/locations/${account.location}/settings`);
    await page.getByRole('button', { name: 'Adaugă zonă', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Alege adresa restaurantului');
    await expect(page.getByRole('button', { name: 'Centrează pe restaurant', exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Restaurant address', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Centrează pe restaurant', exact: true })).toBeVisible();
    await expect(page.getByRole('status')).toContainText('Harta este centrată pe restaurant.');
    expect(calls).toBe(1);
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Center on restaurant', exact: true })).toBeVisible();
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'Română', exact: true }).click();
});
