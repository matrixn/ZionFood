import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const accounts = JSON.parse(readFileSync('storage/app/private/demo-accounts.json', 'utf8')) as { email: string; password: string; location: string }[];
test('committed checkout reaches the authorized private Reverb channel', async ({ page, request }) => {
    const frames: string[] = [];
    page.on('websocket', (socket) => socket.on('framereceived', (event) => frames.push(String(event.payload))));
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(accounts[0].email);
    await page.getByLabel('Password', { exact: true }).fill(accounts[0].password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto('/app/locations/' + accounts[0].location + '/kitchen');
    await expect.poll(() => frames.some((frame) => frame.includes('subscription_succeeded')), { timeout: 10000 }).toBe(true);
    const menu = await (await request.get('/api/v1/public/locations/' + accounts[0].location + '/menu')).json();
    const response = await request.post('/api/v1/public/locations/' + accounts[0].location + '/checkout', { headers: { 'Idempotency-Key': crypto.randomUUID() }, data: { customer_name: 'Realtime Guest', customer_phone: '0700000000', fulfillment: 'pickup', items: [{ product_id: menu.menus[0].categories[0].items[0].public_id, quantity: 1 }] } });
    expect(response.status()).toBe(201); const order = await response.json();
    await expect.poll(() => frames.some((frame) => frame.includes('order.changed') && frame.includes(order.order_id)), { timeout: 10000 }).toBe(true);
    await expect(page.locator('article').filter({ hasText: '#' + order.order_id.slice(-8) })).toBeVisible();
});
test('landing, registration and responsive storefront render', async ({ page }) => {
    const errors: string[] = []; page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/'); await expect(page.getByRole('heading', { name: /Less friction/ })).toBeVisible();
    await page.getByRole('link', { name: 'Set up your restaurant' }).click(); await expect(page.getByRole('heading', { name: 'Start serving smarter' })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 }); await page.goto('/store/' + accounts[0].location);
    await expect(page.getByRole('heading', { name: 'Atelier Bistro', exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect(errors).toEqual([]);
});
test('customer checks out and kitchen recovers the order after reload', async ({ page, browser }) => {
    await page.goto('/store/' + accounts[0].location);
    await page.getByRole('button', { name: /Bruschetta/ }).click();
    await page.getByRole('button', { name: /Add to cart/ }).click();
    await page.getByRole('button', { name: /Your cart/ }).click(); await page.getByRole('button', { name: 'Continue to checkout' }).click();
    await page.getByRole('textbox', { name: 'Your name', exact: true }).fill('Playwright Guest');
    await page.getByRole('textbox', { name: 'Phone number', exact: true }).fill('0712345678');
    await page.getByRole('button', { name: 'Place order', exact: true }).click();
    await expect(page).toHaveURL(/\/track\//); await expect(page.getByText('pending', { exact: true }).first()).toBeVisible();
    const orderId = page.url().split('/').at(-1)!;
    const context = await browser.newContext(); const kitchen = await context.newPage();
    await kitchen.goto('/login'); await kitchen.getByLabel('Email', { exact: true }).fill(accounts[0].email); await kitchen.getByLabel('Password', { exact: true }).fill(accounts[0].password); await kitchen.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(kitchen).toHaveURL(/\/app\/locations\//);
    await kitchen.goto('/app/locations/' + accounts[0].location + '/kitchen');
    let card = kitchen.locator('article').filter({ hasText: '#' + orderId.slice(-8) }); await expect(card).toBeVisible();
    await kitchen.reload(); card = kitchen.locator('article').filter({ hasText: '#' + orderId.slice(-8) }); await expect(card).toBeVisible();
    for (const label of ['Accept order', 'Start preparing', 'Mark ready', 'Complete order']) {
        await card.getByRole('button', { name: label, exact: true }).click();
        if (label !== 'Complete order') await expect(card.getByRole('button', { name: ({ 'Accept order': 'Start preparing', 'Start preparing': 'Mark ready', 'Mark ready': 'Complete order' } as Record<string, string>)[label], exact: true })).toBeVisible();
    }
    await expect(card).toHaveCount(0);
    await page.reload(); await expect(page.getByText('completed', { exact: true }).first()).toBeVisible();
    await kitchen.goto('/app/locations/' + accounts[0].location + '/menu'); await expect(kitchen.getByRole('heading', { name: 'Made to be ordered.' })).toBeVisible();
    await kitchen.getByRole('button', { name: 'Edit Bruschetta', exact: true }).click(); await kitchen.getByLabel('Name', { exact: true }).fill('Bruschetta'); await kitchen.getByRole('button', { name: 'Save product', exact: true }).click(); await expect(kitchen.getByRole('dialog')).toHaveCount(0);
    await kitchen.screenshot({ path: 'test-results/menu-desktop.png', fullPage: true });
    await context.close();
});
