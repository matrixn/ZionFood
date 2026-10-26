import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('owner selects photo cards, sees saved activation feedback and switches language', async ({ page }) => {
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
    await expect(page.getByRole('textbox', { name: 'Specific culinar', exact: true })).toHaveCount(0);
    await page.goto(`/app/locations/${account.location}/setup/cuisines`);
    await expect(page.getByRole('heading', { name: 'Specific culinar', exact: true })).toBeVisible();
    await expect(page.locator('[data-testid^="cuisine-choice-"]')).toHaveCount(27);
    await page.getByTestId('cuisine-choice-pizza').click();
    await page.getByTestId('cuisine-choice-burger').click();
    await expect(page.getByTestId('selected-cuisines')).toContainText('Specifice selectate: 2');
    await expect(page.getByTestId('selected-cuisines')).toContainText('nesalvat');
    await page.getByRole('button', { name: 'Salvează pasul', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Configurarea restaurantului a fost salvată.');
    await page.reload();
    await expect(page.getByTestId('cuisine-choice-pizza')).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByTestId('selected-cuisines')).toContainText('Activ din');
    await page.getByRole('textbox', { name: 'Caută un specific culinar', exact: true }).fill('mexican');
    await expect(page.locator('[data-testid^="cuisine-choice-"]')).toHaveCount(1);
    await page.getByTestId('cuisine-choice-mexican').click();
    await expect(page.getByTestId('selected-cuisines')).toContainText('Specifice selectate: 3');
    await page.getByRole('button', { name: 'Salvează pasul', exact: true }).click();
    await expect(page.getByRole('status')).toBeVisible();
    await page.getByRole('textbox', { name: 'Caută un specific culinar', exact: true }).fill('');
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Cuisine', exact: true })).toBeVisible();
    await expect(page.getByTestId('cuisine-choice-burger')).toHaveAccessibleName('Burgers');
    const photos = page.locator('[data-testid^="cuisine-card-"] img');
    for (const image of await photos.all()) {
        await image.scrollIntoViewIfNeeded();
        await expect(image).toHaveJSProperty('complete', true);
        expect(await image.evaluate((element: HTMLImageElement) => element.naturalWidth)).toBeGreaterThan(0);
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.getByTestId('selected-cuisines').scrollIntoViewIfNeeded();
    await page.screenshot({ path: 'test-results/cuisines-desktop.png', fullPage: false });
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/cuisines-mobile.png', fullPage: false });
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'Română', exact: true }).click();
    expect(errors).toEqual([]);
});

test('platform administrator creates a cuisine with a photo and deactivates it', async ({ page }) => {
    test.setTimeout(90000);
    const admin = JSON.parse(readFileSync('storage/app/private/browser-email-admin.json', 'utf8'));
    await page.goto('/login');
    await page.locator('#email').fill(admin.email);
    await page.locator('#password').fill(admin.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto('/admin/cuisines');
    const name = `Românească test ${Date.now()}`;
    await page.getByRole('textbox', { name: 'Denumire în română', exact: true }).fill(name);
    await page.getByRole('textbox', { name: 'Denumire în engleză', exact: true }).fill('Romanian test');
    await page
        .getByRole('textbox', { name: 'Descriere în română', exact: true })
        .fill('Preparate tradiționale românești.');
    await page.getByRole('textbox', { name: 'Descriere în engleză', exact: true }).fill('Traditional Romanian dishes.');
    await page.getByRole('textbox', { name: 'Identificator unic', exact: true }).fill(`romanian-test-${Date.now()}`);
    await page.locator('input[type="file"]').setInputFiles('public/images/cuisines/pizza.jpg');
    await page.getByRole('button', { name: 'Salvează specificul culinar', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Specificul culinar a fost salvat.');
    const card = page.locator('article').filter({ has: page.getByRole('heading', { name, exact: true }) });
    await expect(card.locator('img')).toBeVisible();
    await expect(card.locator('img')).toHaveJSProperty('complete', true);
    expect(await card.locator('img').evaluate((element: HTMLImageElement) => element.naturalWidth)).toBeGreaterThan(0);
    await card.getByRole('button', { name: 'Editează', exact: true }).click();
    await page.getByRole('switch', { name: 'Disponibil în catalog', exact: true }).click();
    await page.getByRole('button', { name: 'Salvează specificul culinar', exact: true }).click();
    await expect(card).toContainText('Indisponibil în catalog');
    await page.screenshot({ path: 'test-results/cuisines-admin.png', fullPage: false });
});
