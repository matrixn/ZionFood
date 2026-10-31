import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('reviews restaurant settings, approves selected fields, appends zones and rolls back safely', async ({ page }) => {
    test.skip(
        process.env.ZF_SETTINGS_E2E !== '1',
        'Requires the isolated settings database and captured public response.',
    );
    test.setTimeout(120000);
    const account = JSON.parse(readFileSync(process.env.ZF_MIGRATION_FIXTURE!, 'utf8'));
    const restaurant = JSON.parse(readFileSync(process.env.ZF_SETTINGS_SOURCE!, 'utf8')).restaurant;
    const source = { ...restaurant.menu, restaurant_id: restaurant.id };
    const base = `/app/locations/${account.location}/migration-center`;
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(base);
    await page.getByRole('combobox', { name: 'Sursa meniului', exact: true }).click();
    await page.getByRole('option', { name: 'Fișier JSON exportat anterior (max. 5 MB)', exact: true }).click();
    await page.locator('input[type=file]').setInputFiles({
        name: 'public-menu.json',
        mimeType: 'application/json',
        buffer: Buffer.from(JSON.stringify(source)),
    });
    await page
        .getByRole('textbox', { name: 'Link meniu public GloriaFood — opțional', exact: true })
        .fill(
            'https://www.foodbooking.com/ordering/restaurant/menu?restaurant_uid=ca5549f4-4c22-4fd1-9c51-3bd227b20297',
        );
    await page
        .getByRole('checkbox', { name: 'Am dreptul de a importa meniul acestui restaurant.', exact: true })
        .check();
    const started = page.waitForResponse((r) => r.request().method() === 'POST' && r.url().endsWith(base));
    await page.getByRole('button', { name: 'Preia și verifică', exact: true }).click();
    const batch = (await (await started).json()).batch_id;
    await expect(page.getByRole('heading', { name: 'Pregătit pentru verificare', exact: true })).toBeVisible();
    const review = page.getByTestId('migration-settings-review');
    await expect(review.getByRole('checkbox')).toHaveCount(6);
    for (const checkbox of await review.getByRole('checkbox').all()) await expect(checkbox).not.toBeChecked();
    const before = await (await page.request.get(`${base}/${batch}`)).json();
    const fields = Object.fromEntries(
        before.settings_preview.map((f: { key: string; current: unknown }) => [f.key, f.current]),
    );
    await expect(review.locator('[data-setting=name]')).toContainText(restaurant.name);
    await expect(review.locator('[data-setting=hours]')).toContainText('11:00–23:00');
    await expect(
        review.locator('[data-setting=zones]').getByRole('img', { name: /Previzualizare zone de livrare/ }),
    ).toHaveCount(2);
    const checkboxLabels = ['Preia numele', 'Preia adresa', 'Preia telefonul', 'Preia programul', 'Adaugă zonele noi'];
    for (const label of checkboxLabels) await review.getByRole('checkbox', { name: label, exact: true }).check();
    await review.getByRole('checkbox', { name: 'Preia numele', exact: true }).uncheck();
    await review.getByRole('checkbox', { name: 'Preia programul', exact: true }).uncheck();
    await review.scrollIntoViewIfNeeded();
    await page.screenshot({ path: 'test-results/migration-settings-desktop.png', fullPage: true });
    const unchanged = await (await page.request.get(`${base}/${batch}`)).json();
    expect(unchanged.settings_preview.map((f: { current: unknown }) => f.current)).toEqual(
        before.settings_preview.map((f: { current: unknown }) => f.current),
    );
    await page
        .getByRole('checkbox', {
            name: 'Am verificat prețurile, dimensiunile, regulile, alergenii, nutriția și avertismentele.',
            exact: true,
        })
        .check();
    await page.getByRole('button', { name: 'Publică meniul și datele selectate', exact: true }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Adresă și poziție pe hartă');
    await expect(dialog).toContainText('Telefon');
    await expect(dialog).toContainText('Zone de livrare');
    await dialog.getByRole('button', { name: 'Publică meniul și datele selectate', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Publicat', exact: true })).toBeVisible();
    const after = await (await page.request.get(`${base}/${batch}`)).json();
    const changes = Object.fromEntries(
        after.settings_preview.map((f: { key: string; current: unknown }) => [f.key, f.current]),
    );
    expect(changes.name).toEqual(fields.name);
    expect(changes.hours).toEqual(fields.hours);
    expect(changes.phone).toBe(restaurant.phone);
    expect((changes.address as { address: string }).address).toBe(restaurant.address);
    expect((changes.zones as unknown[]).length).toBe((fields.zones as unknown[]).length + 1);
    expect(Object.keys(after.report.settings_changes).sort()).toEqual(['address', 'phone', 'zones']);
    await page.goto(`/app/locations/${account.location}/settings`);
    for (let attempt = 0; attempt < 2; attempt++) {
        const saved = page.waitForResponse((r) => r.request().method() === 'PUT' && r.url().endsWith('/settings'));
        await page.getByRole('button', { name: 'Salvează setările', exact: true }).click();
        const response = await saved;
        expect(response.status()).toBeLessThan(400);
        expect(
            response
                .request()
                .postDataJSON()
                .zones.map((z: { id: number }) => z.id),
        ).toEqual((changes.zones as { id: number }[]).map((z) => z.id));
        await expect(page.getByRole('button', { name: 'Salvează setările', exact: true })).toBeEnabled();
    }
    await page.goto(base);
    await page.locator('summary').filter({ hasText: 'Importuri recente' }).click();
    await page.getByRole('button').filter({ hasText: 'Publicat' }).first().click();
    await expect(page.getByRole('heading', { name: 'Publicat', exact: true })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await review.locator('[data-setting=zones]').scrollIntoViewIfNeeded();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await page.screenshot({ path: 'test-results/migration-settings-mobile.png', fullPage: true });
    await page.getByRole('button', { name: 'Revino la meniul anterior', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Revino la meniul anterior', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Revenit la versiunea anterioară', exact: true })).toBeVisible();
    const reverted = await (await page.request.get(`${base}/${batch}`)).json();
    expect(reverted.settings_preview.map((f: { current: unknown }) => f.current)).toEqual(
        before.settings_preview.map((f: { current: unknown }) => f.current),
    );
    expect(errors).toEqual([]);
});
