import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const fixture = () =>
    JSON.parse(readFileSync('storage/app/private/browser-services.json', 'utf8')) as {
        email: string;
        password: string;
        location: string;
    };

test('guest reserves a real table and cancels with its private receipt', async ({ page }) => {
    test.setTimeout(60000);
    const account = fixture();
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`/reserve/${account.location}`);
    await expect(page.getByRole('heading', { name: 'Rezervă o masă', exact: true })).toBeVisible();
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const day = `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, '0')}-${String(tomorrow.getDate()).padStart(2, '0')}`;
    await page.getByLabel('Data rezervării', { exact: true }).fill(day);
    const slots = page.waitForResponse((response) => response.url().includes(`reservation-slots?date=${day}`));
    await page.getByRole('button', { name: 'Caută locuri disponibile', exact: true }).click();
    await slots;
    await page.getByRole('combobox', { name: 'Ora sosirii', exact: true }).click();
    await page.getByRole('option', { name: '12:00', exact: true }).click();
    await page.getByRole('textbox', { name: 'Numele tău', exact: true }).fill('Browser Guest');
    await page.getByRole('textbox', { name: 'Număr de telefon', exact: true }).fill('0712345678');
    await page.getByRole('button', { name: 'Trimite rezervarea', exact: true }).click();
    await expect(page).toHaveURL(/\/reservations\//);
    await expect(page.getByText('Confirmată', { exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByText('Confirmată', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Anulează rezervarea', exact: true }).click();
    await expect(page.getByText('Anulată', { exact: true })).toBeVisible();
    await page.screenshot({ path: 'test-results/reservation-receipt.png', fullPage: true });
    expect(errors).toEqual([]);
});

test('staff opens a table and its QR submits a real order with the table identity', async ({ page, browser }) => {
    test.setTimeout(90000);
    const account = fixture();
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(`/app/locations/${account.location}/tables`);
    const card = page
        .locator('article')
        .filter({ has: page.getByRole('heading', { name: 'T1 · Salon', exact: true }) });
    await expect(card.locator('img')).toBeVisible();
    await card.getByRole('button', { name: 'Închide meniul mesei', exact: true }).click();
    await expect(card.getByRole('button', { name: 'Deschide meniul mesei', exact: true })).toBeVisible();
    await card.getByRole('button', { name: 'Deschide meniul mesei', exact: true }).click();
    await expect(card.getByRole('button', { name: 'Închide meniul mesei', exact: true })).toBeVisible();
    await card.locator('summary').click();
    const qrUrl = await card.getByRole('link', { name: 'Deschide linkul QR', exact: true }).getAttribute('href');
    expect(qrUrl).toMatch(/\/table\/.*#token=/);
    const guest = await browser.newContext();
    const tab = await guest.newPage();
    await tab.route('**/address-from-pin', route => route.fulfill({ json: { address: null, incomplete: true } }));
    await tab.goto(qrUrl!);
    await expect(tab.getByText('Masa este conectată. Poți trimite comanda.', { exact: true })).toBeVisible();
    await tab.getByRole('button').filter({ hasText: 'Preparat de test' }).click();
    await tab.getByRole('button', { name: /Adaugă în coș/ }).click();
    await tab.getByRole('button', { name: /Coș/ }).click();
    await tab.getByRole('button', { name: /Continuă/ }).click();
    await tab.getByRole('textbox', { name: 'Numele tău', exact: true }).fill('Table Guest');
    await tab.getByRole('textbox', { name: 'Număr de telefon', exact: true }).fill('0712345678');
    await tab.getByRole('button', { name: 'Plasează comanda', exact: true }).click();
    await expect(tab).toHaveURL(/\/track\//);
    await page.goto(`/app/locations/${account.location}/kitchen`);
    await expect(page.getByText('Masa T1 · Salon', { exact: true })).toBeVisible();
    await page.screenshot({ path: 'test-results/qr-kitchen.png', fullPage: true });
    await page.goto(`/app/locations/${account.location}/tables`);
    await card.getByRole('button', { name: 'Închide meniul mesei', exact: true }).click();
    await expect(card.getByRole('button', { name: 'Deschide meniul mesei', exact: true })).toBeVisible();
    await tab.goto(qrUrl!);
    await expect(tab.getByText('Personalul a închis meniul de comandă pentru această masă.', { exact: false })).toBeVisible();
    await expect(tab.getByRole('button').filter({ hasText: 'Preparat de test' })).toHaveCount(0);
    await guest.close();
    expect(errors).toEqual([]);
});

test('manager draws a delivery zone and a guest checks out using its map pin', async ({ page, browser }) => {
    test.setTimeout(90000);
    const account = fixture();
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(`/app/locations/${account.location}/settings`);
    await page.getByRole('spinbutton', { name: 'Oprește comenzile QR înainte de închidere (minute)', exact: true }).fill('60');
    await page.getByText('Livrare', { exact: true }).click();
    await page.getByRole('button', { name: 'Adaugă zonă', exact: true }).click();
    const name = page.getByRole('textbox', { name: 'Nume', exact: true });
    await name.last().fill('Zona pe hartă');
    const map = page.getByTestId('zone-map');
    await expect(map.locator('.leaflet-pm-icon-polygon')).toBeVisible();
    await map.scrollIntoViewIfNeeded();
    await map.locator('.leaflet-pm-icon-polygon').click();
    await expect(map.locator('.action-finish')).toBeVisible();
    const box = await map.boundingBox();
    if (!box) throw new Error('Map missing');
    for (const point of [
        [0.35, 0.5],
        [0.7, 0.5],
        [0.7, 0.85],
        [0.35, 0.85],
    ]) {
        await page.mouse.move(box.x + box.width * point[0], box.y + box.height * point[1]);
        await page.mouse.click(box.x + box.width * point[0], box.y + box.height * point[1], { delay: 150 });
    }
    await map.locator('.action-finish').click();
    await expect(page.getByText('Poligon definit', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Salvează setările', exact: true }).click();
    await expect(page.getByRole('status')).toBeVisible();
    await page.reload();
    await expect(page.getByText('Poligon definit', { exact: true })).toBeVisible();
    await expect(page.getByRole('spinbutton', { name: 'Oprește comenzile QR înainte de închidere (minute)', exact: true })).toHaveValue('60');
    await expect(map.locator('path.leaflet-interactive:not(.zf-restaurant-reference)')).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/delivery-zone-mobile.png', fullPage: true });
    const guest = await browser.newContext();
    const tab = await guest.newPage();
    await tab.route('**/address-from-pin', route => route.fulfill({ json: { address: null, incomplete: true } }));
    await tab.goto(`/store/${account.location}`);
    await tab.getByRole('button').filter({ hasText: 'Preparat de test' }).click();
    await tab.getByRole('button', { name: /Adaugă în coș/ }).click();
    await tab.getByRole('button', { name: /Coș/ }).click();
    await tab.getByRole('button', { name: /Continuă/ }).click();
    await tab.getByRole('textbox', { name: 'Numele tău', exact: true }).fill('Delivery Guest');
    await tab.getByRole('textbox', { name: 'Număr de telefon', exact: true }).fill('0712345678');
    await tab.getByRole('combobox').last().click();
    await tab.getByRole('option', { name: 'Livrare', exact: true }).click();
    await tab.getByRole('textbox', { name: 'Adresă de livrare', exact: true }).fill('Punctul de pe hartă');
    const deliveryMap = tab.getByTestId('delivery-map');
    await expect(deliveryMap.locator('path.leaflet-interactive')).toBeVisible();
    await deliveryMap.scrollIntoViewIfNeeded();
    const deliveryBox = await deliveryMap.boundingBox();
    if (!deliveryBox) throw new Error('Delivery map missing');
    await tab.mouse.click(deliveryBox.x + deliveryBox.width * 0.9, deliveryBox.y + deliveryBox.height * 0.9);
    await expect(tab.getByText('Pinul este în afara zonelor de livrare.', { exact: true })).toBeVisible();
    await expect(tab.getByRole('button', { name: 'Plasează comanda', exact: true })).toBeDisabled();
    await tab.mouse.click(deliveryBox.x + deliveryBox.width * 0.5, deliveryBox.y + deliveryBox.height * 0.5);
    await expect(tab.getByRole('button', { name: 'Plasează comanda', exact: true })).toBeEnabled();
    await tab.getByRole('button', { name: 'Plasează comanda', exact: true }).click();
    await expect(tab).toHaveURL(/\/track\//);
    await guest.close();
    expect(errors).toEqual([]);
});
