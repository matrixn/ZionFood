import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('captures leads in both languages, preserves failed input and manages them only in platform admin', async ({
    page,
}) => {
    test.skip(process.env.ZF_LEADS_E2E !== '1', 'Requires the isolated leads database and administrator fixture.');
    test.setTimeout(120000);
    const admin = JSON.parse(readFileSync(process.env.ZF_LEADS_ADMIN!, 'utf8'));
    const nonce = Date.now();
    const englishName = `English contact ${nonce}`;
    const romanianName = `Restaurant contact ${nonce}`;
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/en#contact');
    const contact = page.locator('#contact');
    await expect(contact.getByRole('button', { name: "Let's talk", exact: true })).toBeEnabled();
    await expect(contact.locator('input:visible')).toHaveCount(3);
    await contact.getByRole('textbox', { name: 'Your name', exact: true }).fill(englishName);
    await contact.getByRole('textbox', { name: 'Your name', exact: true }).press('Tab');
    await expect(contact.getByRole('textbox', { name: 'Email address', exact: true })).toBeFocused();
    await contact.getByRole('textbox', { name: 'Email address', exact: true }).fill(`english-${nonce}@example.test`);
    await contact.getByRole('textbox', { name: 'Phone number', exact: true }).fill('not a number');
    await contact.getByRole('button', { name: "Let's talk", exact: true }).click();
    await expect(contact.getByRole('alert').first()).toContainText('Please check');
    await expect(contact.getByRole('textbox', { name: 'Your name', exact: true })).toHaveValue(englishName);
    await expect(contact.getByRole('textbox', { name: 'Phone number', exact: true })).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    await contact.getByRole('textbox', { name: 'Phone number', exact: true }).fill('+40 770 123 456');
    await page.route('**/interest', (route) => route.fulfill({ status: 503, body: 'unavailable' }), { times: 1 });
    await contact.getByRole('button', { name: "Let's talk", exact: true }).click();
    await expect(contact.getByRole('alert')).toContainText("We couldn't confirm");
    await expect(contact.getByRole('textbox', { name: 'Email address', exact: true })).toHaveValue(
        `english-${nonce}@example.test`,
    );
    await contact.getByRole('button', { name: "Let's talk", exact: true }).click();
    await expect(page.getByTestId('lead-confirmation')).toContainText("We've received your request.");
    await expect(contact.locator('input')).toHaveCount(0);
    await page.goto('/#contact');
    await contact.getByRole('textbox', { name: 'Numele tău', exact: true }).fill(romanianName);
    await contact.getByRole('textbox', { name: 'Adresa de email', exact: true }).fill(`romanian-${nonce}@example.test`);
    await contact.getByRole('textbox', { name: 'Număr de telefon', exact: true }).fill('0770123456');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await contact.scrollIntoViewIfNeeded();
    await contact.screenshot({ path: 'test-results/leads-desktop.png', animations: 'disabled' });
    await page.setViewportSize({ width: 390, height: 844 });
    await contact.scrollIntoViewIfNeeded();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await contact.screenshot({ path: 'test-results/leads-mobile.png', animations: 'disabled' });
    await contact.locator('.lead-card').scrollIntoViewIfNeeded();
    await page.screenshot({ path: 'test-results/leads-mobile-viewport.png', animations: 'disabled' });
    await contact.getByRole('button', { name: 'Vreau să discutăm', exact: true }).click();
    await expect(page.getByTestId('lead-confirmation')).toContainText('Am primit mesajul tău.');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/login');
    await page.locator('#email').fill(admin.email);
    await page.locator('#password').fill(admin.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
    await page.goto('/admin/leads');
    await expect(page.getByRole('heading', { name: 'Solicitări de contact', exact: true })).toBeVisible();
    let card = page.locator('article[data-lead-id]').filter({ hasText: romanianName });
    await expect(card).toContainText(`romanian-${nonce}@example.test`);
    await card.getByRole('combobox', { name: `Starea solicitării pentru ${romanianName}`, exact: true }).click();
    await page.getByRole('option', { name: 'Calificată', exact: true }).click();
    await expect(card.locator('span.rounded-full')).toHaveText('Calificată');
    await page.getByRole('combobox', { name: 'Filtrează după stare', exact: true }).click();
    const filterList = await page
        .getByRole('combobox', { name: 'Filtrează după stare', exact: true })
        .getAttribute('aria-controls');
    await page.locator(`[id="${filterList}"]`).getByRole('option', { name: 'Calificată', exact: true }).click();
    await expect(page.locator('article[data-lead-id]')).toHaveCount(1);
    await expect(card).toBeVisible();
    await page.screenshot({ path: 'test-results/leads-admin.png', animations: 'disabled' });
    await card.getByRole('button', { name: `Șterge solicitarea pentru ${romanianName}`, exact: true }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText(romanianName);
    await expect(card).toBeVisible();
    await dialog.getByRole('button', { name: 'Șterge solicitarea', exact: true }).click();
    await expect(page.locator('article[data-lead-id]')).toHaveCount(0);
    await page.getByRole('combobox', { name: 'Filtrează după stare', exact: true }).click();
    await page.getByRole('option', { name: 'Toate stările', exact: true }).click();
    card = page.locator('article[data-lead-id]').filter({ hasText: englishName });
    await expect(card).toBeVisible();
    await card.getByRole('button', { name: `Șterge solicitarea pentru ${englishName}`, exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Șterge solicitarea', exact: true }).click();
    await expect(page.locator('article[data-lead-id]').filter({ hasText: englishName })).toHaveCount(0);
    expect(errors).toEqual([]);
});
