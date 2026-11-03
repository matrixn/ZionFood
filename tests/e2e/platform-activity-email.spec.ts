import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('platform activity email sources and private details are usable in both languages', async ({ page }) => {
    test.skip(
        process.env.ZF_ACTIVITY_E2E !== '1',
        'Requires isolated activity messages, array mail and administrator fixture.',
    );
    test.setTimeout(90000);
    const fixture = JSON.parse(readFileSync(process.env.ZF_ACTIVITY_ADMIN!, 'utf8'));
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.locator('#email').fill(fixture.email);
    await page.locator('#password').fill(fixture.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
    await page.goto('/admin/emails');
    const labels = {
        ro: ['Admin · Solicitare de contact', 'Admin · Cont nou', 'Admin · Email confirmat', 'Admin · Restaurant nou'],
        en: ['Admin · Contact request', 'Admin · New account', 'Admin · Email verified', 'Admin · New restaurant'],
    };
    for (const locale of ['ro', 'en'] as const) {
        if (locale === 'en') {
            await page.getByTestId('language-selector').click();
            await page.getByRole('option', { name: 'English', exact: true }).click();
        }
        for (const label of labels[locale]) {
            await page.getByRole('combobox', { name: locale === 'ro' ? 'Sursă' : 'Source', exact: true }).click();
            await page.getByRole('option', { name: label, exact: true }).click();
            await page
                .getByRole('button', { name: locale === 'ro' ? 'Aplică filtrele' : 'Apply filters', exact: true })
                .click();
            await expect(page.getByTestId('email-delivery-row')).toHaveCount(1);
            await expect(page.getByTestId('email-delivery-row')).toContainText(label);
            await page
                .getByRole('button', { name: locale === 'ro' ? 'Detalii email' : 'Email details', exact: true })
                .click();
            const dialog = page.getByRole('dialog');
            await expect(dialog).toContainText(fixture.email);
            await expect(dialog).toContainText('ZionFood');
            await dialog
                .getByRole('button', { name: locale === 'ro' ? 'Închide' : 'Close', exact: true })
                .last()
                .click();
        }
    }
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/platform-activity-mail-mobile.png', animations: 'disabled' });
    await page.setViewportSize({ width: 760, height: 1000 });
    await page.setContent(readFileSync(process.env.ZF_ACTIVITY_PREVIEW!, 'utf8'));
    await expect(page.getByRole('heading', { name: 'Solicitare nouă de contact', exact: true })).toBeVisible();
    await expect(page.locator('body')).toContainText('Browser Contact');
    await expect(page.getByRole('link', { name: 'Deschide în administrare', exact: true })).toHaveAttribute(
        'href',
        /\/admin\/leads$/,
    );
    await page.screenshot({ path: 'test-results/platform-activity-email-preview.png', fullPage: true });
    expect(errors).toEqual([]);
});
