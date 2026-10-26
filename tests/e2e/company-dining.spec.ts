import { test, expect as baseExpect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
const expect = baseExpect.configure({ timeout: 15000 });
const fixture = () => JSON.parse(readFileSync('storage/app/private/browser-services.json', 'utf8'));

async function login(page: Page) {
    const account = fixture();
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    return account;
}

const company = {
    tax_id: '14837428', company_name: 'Firma Browser SRL', company_address: 'Strada Test, 10, București', registration_number: 'J40/123/2000',
    company_city: 'București', company_county: 'București', company_country: 'România', company_postal_code: '010101', company_phone: '0211234567',
    company_caen: '5611', company_legal_form: 'SRL', company_status: 'INREGISTRAT', company_iban: '', company_vat: true, company_inactive: false, company_efactura: true,
};

test('CUI comes first and company autofill is editable and persists only on save', async ({ page }) => {
    const account = await login(page);
    const errors: string[] = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/setup/company', route => route.fulfill({ json: { company, source: 'ANAF' } }));
    await page.goto(`/app/locations/${account.location}/setup/legal`);
    await expect(page.locator('form.setup-form input').first()).toHaveAttribute('placeholder', 'RO12345678');
    await page.getByRole('textbox', { name: 'CUI / identificator fiscal', exact: true }).fill('RO14837428');
    await page.getByRole('button', { name: 'Preia datele firmei', exact: true }).click();
    await expect(page.getByRole('textbox', { name: 'Denumirea firmei', exact: true })).toHaveValue('Firma Browser SRL');
    await expect(page.getByRole('textbox', { name: 'Adresa firmei', exact: true })).toHaveValue(company.company_address);
    await page.getByTestId('company-details').locator('summary').click();
    await expect(page.getByRole('textbox', { name: 'Localitate sediu', exact: true })).toHaveValue('București');
    await page.getByRole('textbox', { name: 'Adresa firmei', exact: true }).fill('Strada Test, 10A, București');
    await page.getByRole('button', { name: 'Salvează pasul', exact: true }).click();
    await expect(page.getByText('Configurarea restaurantului a fost salvată.', { exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByRole('textbox', { name: 'Adresa firmei', exact: true })).toHaveValue('Strada Test, 10A, București');
    await page.setViewportSize({ width: 390, height: 844 });
    const cuiBox = (await page.getByRole('textbox', { name: 'CUI / identificator fiscal', exact: true }).boundingBox())!;
    const lookupBox = (await page.getByRole('button', { name: 'Preia datele firmei', exact: true }).boundingBox())!;
    expect(lookupBox.y).toBeGreaterThanOrEqual(cuiBox.y + cuiBox.height);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/company-lookup-mobile.png', fullPage: true });
    expect(errors).toEqual([]);
});

test('late company data does not overwrite manual changes', async ({ page }) => {
    const account = await login(page);
    let release!: () => void;
    const gate = new Promise<void>(resolve => release = resolve);
    await page.route('**/setup/company', async route => { await gate; await route.fulfill({ json: { company } }); });
    await page.goto(`/app/locations/${account.location}/setup/legal`);
    await page.getByRole('textbox', { name: 'CUI / identificator fiscal', exact: true }).fill('RO14837428');
    const pending = page.waitForRequest('**/setup/company');
    await page.getByRole('button', { name: 'Preia datele firmei', exact: true }).click();
    await pending;
    const name = page.getByRole('textbox', { name: 'Denumirea firmei', exact: true });
    await name.fill('Denumire completată manual');
    const response = page.waitForResponse('**/setup/company');
    release();
    await response;
    await expect(page.getByText('Ai modificat formularul în timpul căutării.', { exact: false })).toBeVisible();
    await expect(name).toHaveValue('Denumire completată manual');
});

test('disabled table menus explain the reason and reopen after enabling the location service', async ({ page, browser }) => {
    test.setTimeout(90000);
    const account = await login(page);
    const base = `/app/locations/${account.location}`;
    await page.goto(base + '/tables');
    const card = page.locator('article').filter({ has: page.getByRole('heading', { name: 'T1 · Salon', exact: true }) });
    await card.getByRole('button', { name: 'Închide meniul mesei', exact: true }).click();
    await expect(card.getByRole('button', { name: 'Deschide meniul mesei', exact: true })).toBeVisible();
    await page.goto(base + '/setup/services');
    await page.getByRole('switch', { name: 'Activează comenzile la masă prin QR', exact: true }).uncheck();
    await page.getByRole('button', { name: 'Salvează pasul', exact: true }).click();
    await expect(page.getByRole('status')).toBeVisible();
    await page.goto(base + '/tables');
    const open = card.getByRole('button', { name: 'Deschide meniul mesei', exact: true });
    await expect(open).toBeDisabled();
    await open.locator('..').hover();
    await expect(page.getByRole('tooltip')).toContainText('Regenerarea QR-ului nu activează serviciul');
    await card.locator('summary').click();
    const before = await card.getByRole('link', { name: 'Deschide linkul QR', exact: true }).getAttribute('href');
    await card.getByRole('button', { name: 'Regenerează QR', exact: true }).click();
    await expect.poll(async () => card.getByRole('link', { name: 'Deschide linkul QR', exact: true }).getAttribute('href')).not.toBe(before);
    await expect(open).toBeDisabled();
    const qr = (await card.getByRole('link', { name: 'Deschide linkul QR', exact: true }).getAttribute('href'))!;
    const guest = await browser.newContext();
    const tab = await guest.newPage();
    const disabled = await tab.goto(qr);
    expect(disabled!.status()).toBe(200);
    await expect(tab.getByRole('heading', { name: 'Comanda la masă nu este disponibilă momentan', exact: true })).toBeVisible();
    await tab.getByRole('link', { name: 'Vezi meniul restaurantului', exact: true }).click();
    await expect(tab).toHaveURL(/\/restaurant\/browser-/);
    await page.getByRole('link', { name: 'Activează comenzile la masă', exact: true }).click();
    await page.getByRole('switch', { name: 'Activează comenzile la masă prin QR', exact: true }).check();
    await page.getByRole('button', { name: 'Salvează pasul', exact: true }).click();
    await expect(page.getByRole('status')).toBeVisible();
    await tab.goto(qr);
    await expect(tab.getByText('Personalul a închis meniul de comandă pentru această masă.', { exact: false })).toBeVisible();
    await page.goto(base + '/tables');
    await expect(open).toBeEnabled();
    await open.click();
    await expect(card.getByRole('button', { name: 'Închide meniul mesei', exact: true })).toBeVisible();
    await page.reload();
    await expect(card.getByRole('button', { name: 'Închide meniul mesei', exact: true })).toBeVisible();
    await guest.close();
});
