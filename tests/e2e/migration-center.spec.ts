import { test, expect as baseExpect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const ro = JSON.parse(readFileSync('resources/js/i18n/ro.json', 'utf8'));
const expect = baseExpect.configure({ timeout: 20000 });
const account = JSON.parse(readFileSync('storage/app/private/browser-account.json', 'utf8'));
test('imports official menu, resolves nutrient IDs, publishes, orders size toppings and rolls back', async ({
    page,
    request,
}) => {
    test.setTimeout(180000);

    page.setDefaultTimeout(20000);
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//, { timeout: 30000 });
    const base = `/app/locations/${account.location}/migration-center`;
    await page.goto(base);
    await expect(page.getByRole('heading', { name: 'Migration Center', exact: true })).toBeVisible();
    await page.getByRole('combobox', { name: 'Sursa meniului', exact: true }).click();
    await page.getByRole('option', { name: 'Fișier JSON exportat anterior (max. 5 MB)', exact: true }).click();
    const source = JSON.parse(readFileSync('tests/Fixtures/gloriafood-menu.json', 'utf8'));
    source.currency = 'RON';
    source.restaurant_id = Date.now(); // Isolate first-import mapping from prior local browser runs.
    source.categories[0].items[0].sizes[0].groups[0].allow_quantity = true;
    await page.locator('input[type=file]').setInputFiles({
        name: 'menu.json',
        mimeType: 'application/json',
        buffer: Buffer.from(JSON.stringify(source)),
    });
    await page
        .getByRole('checkbox', { name: 'Am dreptul de a importa meniul acestui restaurant.', exact: true })
        .check();
    await page.getByRole('button', { name: 'Preia și verifică', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Pregătit pentru verificare', exact: true })).toBeVisible({
        timeout: 20000,
    });
    const mappings = Object.fromEntries(
        ['energy_kcal', 'fat_g', 'carbohydrate_g', 'protein_g', 'sugars_g', 'salt_g'].map((n, i) => [
            String(i + 9),
            ro['mc_nutrient_' + n],
        ]),
    ) as Record<string, string>;
    for (const [id, name] of Object.entries(mappings)) {
        await page.getByRole('combobox', { name: `ID ${id}`, exact: true }).click();
        await page.getByRole('option', { name, exact: true }).last().click();
    }
    await page.getByRole('button', { name: 'Salvează asocierile și verifică din nou', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Asociază ID-urile valorilor nutriționale' })).toHaveCount(0);
    await page
        .getByRole('checkbox', {
            name: 'Am verificat prețurile, dimensiunile, regulile, alergenii, nutriția și avertismentele.',
            exact: true,
        })
        .check();
    await page.screenshot({ path: 'test-results/migration-desktop.png', fullPage: true });
    await page.getByRole('button', { name: 'Publică meniul', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Publică meniul', exact: true }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Publicat', exact: true })).toBeVisible();
    const menu = await (await request.get(`/api/v1/public/locations/${account.location}/menu`)).json();
    const product = menu.menus[0].categories[0].items[0];
    expect(product.variants[1].price).toBe(900);
    await page.goto(`/store/${account.location}`);
    await page
        .getByTestId('menu-product-card')
        .filter({ has: page.getByRole('heading', { name: 'Pizza Margherita', exact: true }) })
        .click();
    const dialog = page.getByRole('dialog');
    await expect(dialog.getByText('Extra Toppings (Small)', { exact: false })).toBeVisible();
    await expect(dialog.getByText('Extra Toppings (Large)', { exact: false })).toHaveCount(0);
    await dialog.getByRole('checkbox', { name: 'Corn', exact: true }).check();
    await dialog.getByRole('spinbutton', { name: 'Cantitate · Corn', exact: true }).fill('3');
    await dialog.getByRole('spinbutton', { name: 'Cantitate · Corn', exact: true }).press('Tab');
    await expect(dialog.getByRole('button', { name: /Adaugă în coș.*9,10.*RON/ })).toBeEnabled();
    const crust = product.modifier_groups[0].options[0];
    const corn = product.variants[0].modifier_groups[0].options[0];
    const order = await request.post(`/api/v1/public/locations/${account.location}/checkout`, {
        headers: { 'Idempotency-Key': crypto.randomUUID() },
        data: {
            customer_name: 'Migration Browser Guest',
            customer_phone: '0700000000',
            fulfillment: 'pickup',
            items: [
                {
                    product_id: product.public_id,
                    quantity: 1,
                    variant_id: product.variants[0].id,
                    modifiers: [
                        { id: crust.id, quantity: 1 },
                        { id: corn.id, quantity: 3 },
                    ],
                },
            ],
        },
    });
    expect(order.status(), await order.text()).toBe(201);
    await page.goto(base);
    await page.locator('summary').filter({ hasText: 'Importuri recente' }).click();
    await page.getByRole('button').filter({ hasText: 'Publicat' }).first().click();
    await expect(page.getByRole('button', { name: 'Revino la meniul anterior', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Revino la meniul anterior', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Revino la meniul anterior', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Revenit la versiunea anterioară', exact: true })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByRole('heading', { name: 'Migration Center', exact: true })).toBeVisible();
    await page.screenshot({ path: 'test-results/migration-mobile.png', fullPage: true });
    const overflow = await page.evaluate(() =>
        [...document.querySelectorAll('*')]
            .filter((e) => e.getBoundingClientRect().right > 391)
            .map((e) => ({ tag: e.tagName, cls: e.className }))
            .slice(-10),
    );
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
        JSON.stringify(overflow),
    ).toBeLessThanOrEqual(390);
    await page.screenshot({ path: 'test-results/migration-mobile.png', fullPage: true });
    expect(errors).toEqual([]);
});
