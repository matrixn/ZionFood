import { test, expect, request } from '@playwright/test';
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
const account = JSON.parse(
    readFileSync(process.env.ZF_WP_FIXTURE ?? 'storage/app/private/browser-wordpress.json', 'utf8'),
);

async function login(page: import('@playwright/test').Page) {
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
}

test('WordPress key issuance, real local API call, restaurant logs and revocation', async ({ page, baseURL }) => {
    test.setTimeout(120000);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await login(page);
    await page.goto(`/app/locations/${account.location}/integrations`);
    const section = page
        .locator('section')
        .filter({ has: page.getByRole('heading', { name: 'Conectare WordPress', exact: true }) })
        .first();
    const connectionName = 'WP browser ' + Date.now();
    await section.getByRole('textbox', { name: 'Numele integrării', exact: true }).fill(connectionName);
    await section
        .getByRole('textbox', { name: 'Adresa site-ului WordPress', exact: true })
        .fill('https://restaurant.example');
    await section.getByRole('button', { name: 'Generează cheia WordPress', exact: true }).click();
    const token = page.getByTestId('wp-token');
    await expect(token).toBeVisible();
    const apiKey = (await token.textContent())!.trim();
    const client = await request.newContext({
        baseURL,
        extraHTTPHeaders: { Authorization: `Bearer ${apiKey}`, Accept: 'application/json' },
    });
    const bootstrap = await client.get('/api/v1/wordpress/bootstrap');
    expect(bootstrap.status()).toBe(200);
    expect((await bootstrap.json()).capabilities.payments).toBe(false);
    const menu = await client.get(`/api/v1/wordpress/locations/${account.location}/menu`);
    expect(menu.status()).toBe(200);
    expect(menu.headers()['x-zionfood-request-id']).toBeTruthy();
    await page.goto(`/app/locations/${account.location}/general-settings`);
    await page.getByRole('switch', { name: 'Afișează jurnalul de comunicații al restaurantului', exact: true }).check();
    await page.getByRole('switch', { name: 'Permite comenzi programate', exact: true }).check();
    await page.getByRole('button', { name: 'Salvează', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Setările generale au fost salvate.');
    const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
    const slots = await client.get(
        `/api/v1/wordpress/locations/${account.location}/schedule-slots?date=${tomorrow}&fulfillment=pickup`,
    );
    expect(slots.status()).toBe(200);
    const scheduledFor = (await slots.json()).slots[0];
    const publicMenu = await menu.json();
    const product = publicMenu.menus[0].categories[0].items[0];
    const input = {
        customer_name: 'Local browser customer',
        customer_phone: '0712345678',
        fulfillment: 'pickup',
        scheduled_for: scheduledFor,
        items: [{ product_id: product.public_id, quantity: 1 }],
    };
    const quote = await client.post(`/api/v1/wordpress/locations/${account.location}/quote`, { data: input });
    expect(quote.status()).toBe(200);
    expect((await quote.json()).total).toBe(product.price);
    const checkout = await client.post(`/api/v1/wordpress/locations/${account.location}/checkout`, {
        data: input,
        headers: { 'Idempotency-Key': randomUUID() },
    });
    expect(checkout.status()).toBe(201);
    const receipt = await checkout.json();
    expect(
        (
            await client.post(`/api/v1/wordpress/locations/${account.location}/orders/${receipt.order_id}/track`, {
                data: { tracking_token: receipt.tracking_token },
            })
        ).status(),
    ).toBe(200);
    await page.getByRole('link', { name: 'Jurnal comunicații', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Jurnal comunicații', exact: true })).toBeVisible();
    await expect(page.getByRole('cell', { name: /wordpress\/bootstrap/ }).first()).toBeVisible();
    await page.getByRole('textbox', { name: 'HTTP', exact: true }).fill('403');
    await page.getByRole('button', { name: 'Aplică filtrele', exact: true }).click();
    await expect(page.getByText('Nu există comunicații pentru filtrele alese.', { exact: true })).toBeVisible();
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Communication logs', exact: true })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByRole('button', { name: 'Apply filters', exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await page.screenshot({ path: 'test-results/wordpress-logs-mobile.png', fullPage: true });
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'Română', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Jurnal comunicații', exact: true })).toBeVisible();
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto(`/app/locations/${account.location}/integrations`);
    await section
        .getByTestId('wp-connection')
        .filter({ hasText: connectionName })
        .getByRole('button', { name: 'Revocă', exact: true })
        .click();
    await expect(section.getByTestId('wp-connection').filter({ hasText: connectionName })).toContainText('Dezactivată');
    const revoked = await client.get('/api/v1/wordpress/bootstrap');
    expect(revoked.status()).toBe(401);
    await client.dispose();
    expect(errors).toEqual([]);
});

test('promotion form and platform diagnostic filters work with contextual help', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await login(page);
    await page.goto(`/app/locations/${account.location}/promotions`);
    const promotionName = 'Promoție browser ' + Date.now();
    await page.getByRole('textbox', { name: 'Nume promoție', exact: true }).fill(promotionName);
    await page.getByRole('textbox', { name: 'Cod cupon (opțional)', exact: true }).fill('BROWSER' + Date.now());
    await page.getByRole('button', { name: /Informa.*Valoare/i }).hover();
    await expect(page.getByRole('tooltip')).toContainText('1000 = 10%');
    await page.getByRole('button', { name: 'Salvează', exact: true }).click();
    await expect(page.getByRole('article').filter({ hasText: promotionName })).toBeVisible();
    expect((await page.request.get('/api/v1/wordpress/bootstrap')).status()).toBe(401);
    await page.goto('/admin/communication-logs');
    await expect(page.getByRole('heading', { name: 'Jurnal comunicații', exact: true })).toBeVisible();
    await page.getByRole('textbox', { name: 'HTTP', exact: true }).fill('401');
    await page.getByRole('button', { name: 'Aplică filtrele', exact: true }).click();
    await expect(page.getByRole('cell', { name: '401', exact: true }).first()).toBeVisible();
    expect(errors).toEqual([]);
});
