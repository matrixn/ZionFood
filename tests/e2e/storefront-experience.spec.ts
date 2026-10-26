import { test, expect as baseExpect, type Page, type Locator } from '@playwright/test';
import { readFileSync } from 'node:fs';

const expect = baseExpect.configure({ timeout: 15000 });
const fixture = () => JSON.parse(readFileSync('storage/app/private/browser-storefront.json', 'utf8'));
test.use({ viewport: { width: 390, height: 844 } });

async function inViewport(locator: Locator) {
    const box = await locator.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.y).toBeGreaterThanOrEqual(0);
    expect(box!.y + box!.height).toBeLessThanOrEqual(844);
}

async function addProducts(page: Page) {
    await page.goto(`/store/${fixture().location}`);
    const card = page.getByTestId('menu-product-card').filter({ hasText: 'Preparat de test' });
    await expect(card.getByRole('heading')).toHaveText('Preparat de test');
    await expect(card).toContainText('Porție · 350 g');
    await expect(card.locator('img')).toBeVisible();
    await card.click();
    const dialog = page.getByRole('dialog');
    await expect(dialog.locator('img')).toBeVisible();
    await page.screenshot({ animations: 'disabled', path: 'test-results/product-photo-mobile.png' });
    await expect(dialog.getByText('Preparat de test', { exact: true })).toHaveCount(1);
    await expect(dialog.getByRole('button', { name: /Adaugă în coș/ })).toBeDisabled();
    await dialog.getByRole('checkbox').first().check();
    await dialog.locator('.p-dialog-content').evaluate(el => el.scrollTop = el.scrollHeight);
    await inViewport(dialog.getByRole('button', { name: /Adaugă în coș/ }));
    await page.screenshot({ animations: 'disabled', path: 'test-results/product-mobile.png' });
    await dialog.getByRole('button', { name: /Adaugă în coș/ }).click();
    await page.getByTestId('menu-product-card').filter({ hasText: 'Burger de test' }).click();
    await dialog.getByRole('button', { name: /Adaugă în coș/ }).click();
    await page.getByRole('button', { name: /Coșul tău/ }).click();
    const rows = page.getByTestId('cart-line');
    await expect(rows).toHaveCount(2);
    for (const row of await rows.all()) {
        await expect(row.locator('img')).toBeVisible();
        expect(await row.locator('img').evaluate((el: HTMLImageElement) => el.complete && el.naturalWidth > 0)).toBe(true);
        expect((await row.boundingBox())!.height).toBeLessThan(100);
    }
    await inViewport(page.getByRole('button', { name: /Continuă la finalizare/ }));
    await page.screenshot({ animations: 'disabled', path: 'test-results/cart-mobile.png' });
    await page.getByRole('button', { name: /Continuă la finalizare/ }).click();
}

test('mobile product, compact cart and delivery checkout keep actions visible', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/address-from-pin', route => route.fulfill({ json: { address: 'Strada Test, 8, Râmnicu Vâlcea', incomplete: false } }));
    await addProducts(page);
    const dialog = page.getByRole('dialog');
    const submit = dialog.getByRole('button', { name: 'Plasează comanda', exact: true });
    await expect(submit).toBeDisabled();
    await expect(page.getByTestId('checkout-footer')).toContainText('59,00');
    await dialog.getByRole('textbox', { name: 'Numele tău', exact: true }).fill('UX Guest');
    await dialog.getByRole('textbox', { name: 'Număr de telefon', exact: true }).fill('0712345678');
    await expect(submit).toBeEnabled();
    await dialog.getByRole('combobox').click();
    await page.getByRole('option', { name: 'Livrare', exact: true }).click();
    await expect(submit).toBeDisabled();
    const map = page.getByTestId('delivery-map');
    await expect(map.locator('path.leaflet-interactive')).toBeVisible();
    await map.scrollIntoViewIfNeeded();
    const box = (await map.boundingBox())!;
    await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
    await expect(map.locator('.zf-map-pin')).toBeVisible();
    const address = dialog.getByRole('textbox', { name: 'Adresă de livrare', exact: true });
    await expect(address).toHaveValue('Strada Test, 8, Râmnicu Vâlcea');
    await address.fill('Strada Test, 8A, Râmnicu Vâlcea');
    await expect(submit).toBeEnabled();
    await expect(page.getByTestId('checkout-footer')).toContainText('66,00');
    await dialog.locator('.p-dialog-content').evaluate(el => el.scrollTop = el.scrollHeight);
    await inViewport(submit);
    await inViewport(page.getByTestId('checkout-footer'));
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ animations: 'disabled', path: 'test-results/checkout-mobile.png' });
    const request = page.waitForRequest(r => r.url().endsWith('/checkout') && r.method() === 'POST');
    await submit.click();
    const payload = (await request).postDataJSON();
    expect(payload.delivery_address).toBe('Strada Test, 8A, Râmnicu Vâlcea');
    expect(payload.items[0]).not.toHaveProperty('unit_price');
    expect(payload.items[0]).not.toHaveProperty('image_path');
    await expect(page).toHaveURL(/\/track\//);
    expect(errors).toEqual([]);
});

test('late map replies never overwrite a manually edited address', async ({ page }) => {
    let release!: () => void;
    const gate = new Promise<void>(resolve => release = resolve);
    await page.route('**/address-from-pin', async route => {
        await gate;
        await route.fulfill({ json: { address: 'Stale address', incomplete: false } });
    });
    await addProducts(page);
    await page.getByRole('dialog').getByRole('combobox').click();
    await page.getByRole('option', { name: 'Livrare', exact: true }).click();
    const map = page.getByTestId('delivery-map');
    await expect(map.locator('path.leaflet-interactive')).toBeVisible();
    const request = page.waitForRequest('**/address-from-pin');
    await map.scrollIntoViewIfNeeded();
    const box = (await map.boundingBox())!;
    await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
    await expect(map.locator('.zf-map-pin')).toBeVisible();
    await request;
    const address = page.getByRole('textbox', { name: 'Adresă de livrare', exact: true });
    await address.fill('Adresa mea, 15, București');
    const response = page.waitForResponse('**/address-from-pin');
    release();
    await response;
    await expect(address).toHaveValue('Adresa mea, 15, București');
});

test('share metadata is in raw HTML and product information switches languages', async ({ page }) => {
    const url = `/store/${fixture().location}`;
    const raw = await (await page.request.get(url)).text();
    expect(raw).toContain('property="og:title" content="Restaurant Test Meniu Digital"');
    expect(raw).toMatch(/property="og:image" content="http[^"]+\/shares\//);
    await page.goto(url);
    await expect(page.locator('meta[property="og:title"]')).toHaveCount(1);
    await expect(page.locator('meta[property="og:image"]')).toHaveCount(1);
    await expect(page.locator('meta[property="og:image:width"]')).toHaveCount(1);
    await expect(page.locator('meta[property="og:image:width"]')).toHaveAttribute('content', '600');
    await expect(page.locator('meta[property="og:image:height"]')).toHaveAttribute('content', '600');
    const shareImage = await page.request.get((await page.locator('meta[property="og:image"]').getAttribute('content'))!);
    expect(shareImage.ok()).toBe(true);
    expect(shareImage.headers()['content-type']).toContain('image/png');
    const png = await shareImage.body();
    expect(png.readUInt32BE(16)).toBe(600);
    expect(png.readUInt32BE(20)).toBe(600);
    const notice = page.getByTestId('allergen-notice');
    await expect(notice).toContainText('1169/2011');
    await expect(notice).not.toContainText('2000/13');
    await notice.locator('summary').click();
    await expect(notice.locator('li')).toHaveCount(14);
    await expect(notice.getByText('Moluște', { exact: true })).toBeVisible();
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ animations: 'disabled', path: 'test-results/menu-mobile.png', fullPage: true });
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByTestId('menu-product-card').first()).toContainText('Serving · 350 g');
    await expect(notice).toContainText('Molluscs');
    await expect(page.locator('meta[property="og:title"]')).toHaveAttribute('content', 'Restaurant Test Digital Menu');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.screenshot({ animations: 'disabled', path: 'test-results/menu-desktop.png', fullPage: true });
});

test('restaurant editors submit through their always visible footer', async ({ page }) => {
    const account = fixture();
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(`/app/locations/${account.location}/setup/menu`);
    await page.getByRole('button', { name: 'Editează Preparat de test', exact: true }).click();
    const dialog = page.getByRole('dialog');
    const save = dialog.getByRole('button', { name: 'Salvează produsul', exact: true });
    const name = dialog.getByRole('textbox', { name: 'Nume', exact: true });
    await name.fill('');
    await expect(save).toBeDisabled();
    await name.fill('Preparat de test');
    await dialog.locator('.p-dialog-content').evaluate(el => el.scrollTop = el.scrollHeight);
    await inViewport(save);
    await save.click();
    await expect(dialog).toHaveCount(0);
    await expect(page).toHaveURL(new RegExp('/setup/menu$'));
    await page.getByRole('button', { name: 'Editează Alege sosul', exact: true }).click();
    await expect(dialog.getByRole('textbox', { name: 'Numele opțiunii', exact: true })).toHaveCount(12);
    await dialog.locator('.p-dialog-content').evaluate(el => el.scrollTop = el.scrollHeight);
    const groupSave = dialog.getByRole('button', { name: 'Salvează grupul', exact: true });
    await inViewport(groupSave);
    await groupSave.click();
    await expect(dialog).toHaveCount(0);
});
