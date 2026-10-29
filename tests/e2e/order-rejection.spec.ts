import { test, expect as baseExpect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const expect = baseExpect.configure({ timeout: 20000 });
const account = JSON.parse(readFileSync('storage/app/private/browser-account.json', 'utf8'));

test('kitchen rejection reason survives reload and is visible in the orders tooltip and details in both languages', async ({
    page,
    request,
}) => {
    test.setTimeout(120000);
    page.setDefaultTimeout(20000);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const menu = await (await request.get(`/api/v1/public/locations/${account.location}/menu`)).json();
    const placed = await request.post(`/api/v1/public/locations/${account.location}/checkout`, {
        headers: { 'Idempotency-Key': crypto.randomUUID() },
        data: {
            customer_name: 'Rejection regression guest',
            customer_phone: '0712345678',
            fulfillment: 'pickup',
            items: [{ product_id: menu.menus[0].categories[0].items[0].public_id, quantity: 1 }],
        },
    });
    expect(placed.status()).toBe(201);
    const { order_id: id } = await placed.json();
    const reason = 'Ingredient indisponibil: mozzarella. Te rugăm să alegi alt preparat.';
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(`/app/locations/${account.location}/kitchen`);
    const card = page.locator('article').filter({ hasText: '#' + id.slice(-8) });
    await expect(card).toBeVisible();
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'Română', exact: true }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ro');
    await card.getByRole('textbox', { name: 'Motivul respingerii', exact: true }).fill(reason);
    const rejected = page.waitForResponse(
        (response) =>
            response.url().endsWith(`/kitchen-orders/${id}/status`) && response.request().method() === 'PATCH',
    );
    await card.getByRole('button', { name: 'Respinge comanda', exact: true }).click();
    expect((await rejected).status()).toBe(200);
    await page.goto(`/app/locations/${account.location}/orders`);
    await page.reload();
    const row = page.getByRole('row').filter({ hasText: '#' + id.slice(-8) });
    const info = row.getByRole('button', { name: 'Informații: Motivul respingerii', exact: true });
    await info.hover();
    await expect(page.getByRole('tooltip')).toHaveText(reason);
    await info.focus();
    await expect(page.getByRole('tooltip')).toHaveText(reason);
    await info.press('Escape');
    await expect(page.getByRole('tooltip')).toHaveCount(0);
    await row.getByRole('button', { name: 'Vezi detaliile comenzii', exact: true }).click();
    await expect(page.getByRole('region', { name: 'Motivul respingerii', exact: true })).toContainText(reason);
    await expect(page.getByRole('region', { name: 'Motivul respingerii', exact: true })).toBeInViewport({ ratio: 1 });
    await page.screenshot({ path: 'test-results/order-rejection-details.png', animations: 'disabled' });
    await page
        .getByRole('button', { name: /Închide|Close/ })
        .first()
        .click();
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await row.getByRole('button', { name: 'Information: Reason for rejection', exact: true }).click();
    await expect(page.getByRole('tooltip')).toHaveText(reason);
    await row.getByRole('button', { name: 'View order details', exact: true }).click();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByRole('region', { name: 'Reason for rejection', exact: true })).toContainText(reason);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await expect(page.getByRole('region', { name: 'Reason for rejection', exact: true })).toBeInViewport({ ratio: 1 });
    await page.screenshot({ path: 'test-results/order-rejection-mobile.png', animations: 'disabled' });
    expect(errors).toEqual([]);
});
