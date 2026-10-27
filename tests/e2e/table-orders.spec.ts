import { test, expect as baseExpect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
const expect = baseExpect.configure({ timeout: 20000 });
const account = JSON.parse(readFileSync('storage/app/private/browser-services.json', 'utf8'));
async function orderAtTable(page: Page, name: string) {
    await page.getByTestId('menu-product-card').filter({ hasText: 'Preparat de test' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: /Adaugă în coș/ })
        .click();
    await page.getByRole('button', { name: /Coșul tău/ }).click();
    await page.getByRole('button', { name: /Continuă la finalizare/ }).click();
    await page.getByRole('dialog').getByRole('textbox', { name: 'Numele tău', exact: true }).fill(name);
    await page.getByRole('dialog').getByRole('textbox', { name: 'Număr de telefon', exact: true }).fill('0712345678');
    const sent = page.waitForResponse((r) => r.url().endsWith('/checkout') && r.request().method() === 'POST');
    await page.getByRole('dialog').getByRole('button', { name: 'Plasează comanda', exact: true }).click();
    const response = await sent;
    expect(response.status(), await response.text()).toBe(201);
    return (await response.json()).order_id as string;
}
async function status(page: Page, id: string, next: string, version: number) {
    const result = await page.evaluate(
        async ({ id, next, version }) => {
            const xsrf =
                document.cookie
                    .split('; ')
                    .find((c) => c.startsWith('XSRF-TOKEN='))
                    ?.split('=')
                    .slice(1)
                    .join('=') ?? '';
            const r = await fetch(`/api/v1/orders/${id}/status`, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(xsrf),
                },
                body: JSON.stringify({ status: next, version }),
            });
            return { status: r.status, body: await r.text() };
        },
        { id, next, version },
    );
    expect(result.status, result.body).toBe(200);
}
test('QR guests retain shared active products until ready orders are served and completed', async ({
    page,
    browser,
}) => {
    test.setTimeout(150000);
    page.setDefaultTimeout(20000);
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(`/app/locations/${account.location}/tables`);
    const table = page
        .locator('article')
        .filter({ has: page.getByRole('heading', { name: 'T1 · Salon', exact: true }) });
    const open = table.getByRole('button', { name: 'Deschide meniul mesei', exact: true });
    if (await open.isVisible()) await open.click();
    await table.locator('summary').click();
    const qr = await table.getByRole('link', { name: 'Deschide linkul QR', exact: true }).getAttribute('href');
    expect(qr).toBeTruthy();
    const first = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const second = await browser.newContext();
    const a = await first.newPage();
    const b = await second.newPage();
    const errors: string[] = [];
    a.on('pageerror', (e) => errors.push(e.message));
    b.on('pageerror', (e) => errors.push(e.message));
    await a.goto(qr!);
    await expect(a.getByText('Masa este conectată. Poți trimite comanda.', { exact: true })).toBeVisible();
    const id = await orderAtTable(a, 'QR Guest One');
    await expect(a).toHaveURL(/\/table\//);
    await expect(a.getByRole('dialog')).toHaveCount(0);
    const card = a.locator(`[data-order="${id}"]`);
    await expect(card).toContainText('Preparat de test');
    const repeat = await orderAtTable(a, 'QR Guest One');
    expect(repeat).not.toBe(id);
    await expect(a.locator(`[data-order="${repeat}"]`)).toBeVisible();
    await a.reload();
    await expect(card).toBeVisible();
    await b.goto(qr!);
    await expect(b.locator(`[data-order="${id}"]`)).toBeVisible();
    const other = await orderAtTable(b, 'QR Guest Two');
    await expect(a.locator(`[data-order="${other}"]`)).toBeVisible();
    await status(page, id, 'accepted', 1);
    await status(page, id, 'preparing', 2);
    await status(page, id, 'ready', 3);
    await expect(card.getByText('Pregătită', { exact: true })).toBeVisible();
    await a.screenshot({ path: 'test-results/table-orders-mobile.png', fullPage: true });
    expect(await a.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    // Closed ordering must not hide receipts already granted to these guests.
    await table.getByRole('button', { name: 'Închide meniul mesei', exact: true }).click();
    await a.reload();
    await expect(card).toBeVisible();
    await expect(a.getByTestId('menu-product-card')).toHaveCount(0);
    const fresh = await browser.newContext();
    const c = await fresh.newPage();
    await c.goto(qr!);
    await expect(c.getByTestId('table-active-orders')).toHaveCount(0);
    await fresh.close();
    await status(page, id, 'completed', 4);
    await expect(card).toHaveCount(0);
    await expect(a.locator(`[data-order="${other}"]`)).toBeVisible();
    await status(page, other, 'cancelled', 1);
    await expect(a.locator(`[data-order="${repeat}"]`)).toBeVisible();
    await status(page, repeat, 'cancelled', 1);
    await expect(a.getByTestId('table-active-orders')).toHaveCount(0);
    await expect(b.getByTestId('table-active-orders')).toHaveCount(0);
    await table.getByRole('button', { name: 'Deschide meniul mesei', exact: true }).click();
    await first.close();
    await second.close();
    expect(errors).toEqual([]);
});
