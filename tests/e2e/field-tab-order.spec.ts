import { test, expect, type Locator, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';

test.skip(process.env.ZF_FIELD_TAB_E2E !== '1', 'Requires a private local owner/admin fixture and array mail.');
const fixture = () => JSON.parse(readFileSync(process.env.ZF_FIELD_TAB_FIXTURE!, 'utf8'));

async function login(page: Page) {
    const account = fixture();
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    return account;
}

async function checkHelp(page: Page, control: Locator) {
    await expect(control).toHaveAttribute('aria-keyshortcuts', /F1/);
    const text = await control.evaluate((element) =>
        (element.getAttribute('aria-describedby') || '')
            .split(/\s+/)
            .map((id) => document.getElementById(id)?.textContent || '')
            .join(' '),
    );
    expect(text).toContain('F1');
    await control.focus();
    await page.keyboard.press('F1');
    await expect(page.getByRole('tooltip')).toContainText('F1');
    await expect(control).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('tooltip')).toHaveCount(0);
}

test('restaurant text, textarea, select, switch and dynamic dialog fields skip help Tab stops', async ({ page }) => {
    const account = await login(page);
    await page.goto(`/app/locations/${account.location}/branding`);
    const name = page.getByRole('textbox', { name: 'Numele restaurantului', exact: true });
    const slug = page.getByRole('textbox', { name: 'Adresa paginii de comenzi (slug)', exact: true });
    await checkHelp(page, name);
    await page.keyboard.press('Tab');
    await expect(slug).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('textbox', { name: 'Slogan', exact: true })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('textbox', { name: 'Descriere', exact: true })).toBeFocused();
    await expect(page.getByRole('button', { name: 'Informații: Numele restaurantului', exact: true })).toHaveAttribute(
        'tabindex',
        '-1',
    );
    await page.goto(`/app/locations/${account.location}/general-settings`);
    const date = page.getByRole('combobox', { name: 'Format dată', exact: true });
    await checkHelp(page, date);
    await page.keyboard.press('Tab');
    await expect(page.getByRole('combobox', { name: 'Format dată și oră', exact: true })).toBeFocused();
    const phone = page.getByRole('switch', { name: 'Afișează telefonul', exact: true });
    await checkHelp(page, phone);
    await page.goto(`/app/locations/${account.location}/staff`);
    const email = page.getByRole('textbox', { name: 'E-mail', exact: true }).first();
    await checkHelp(page, email);
    await page.keyboard.press('Tab');
    await expect(page.getByRole('combobox', { name: 'Rol', exact: true }).first()).toBeFocused();
    await page.goto(`/app/locations/${account.location}/webhooks`);
    for (let attempt = 0; attempt < 2; attempt++) {
        await page.getByRole('button', { name: 'Adaugă webhook', exact: true }).click();
        const dialog = page.getByRole('dialog');
        const webhookName = dialog.getByRole('textbox', { name: 'Nume', exact: true });
        await checkHelp(page, webhookName);
        await expect(dialog).toBeVisible();
        await page.keyboard.press('Tab');
        await expect(dialog.getByRole('textbox', { name: 'Adresă HTTPS publică', exact: true })).toBeFocused();
        await dialog.getByRole('button', { name: 'Închide', exact: true }).click();
        await expect(dialog).toHaveCount(0);
    }
});

test('customer reservation fields retain help while moving directly between contact inputs', async ({ page }) => {
    const account = fixture();
    await page.goto(`/reserve/${account.location}`);
    const name = page.getByRole('textbox', { name: 'Numele tău', exact: true });
    await checkHelp(page, name);
    await page.keyboard.press('Tab');
    await expect(page.getByRole('textbox', { name: 'Număr de telefon', exact: true })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('textbox', { name: 'E-mail', exact: true })).toBeFocused();
    await expect(page.getByRole('button', { name: 'Informații: Numele tău', exact: true })).toHaveAttribute(
        'tabindex',
        '-1',
    );
});

test('platform administration uses the same field rule and non-field explanations remain keyboard reachable', async ({
    page,
}) => {
    const account = await login(page);
    const information = page.getByRole('button', { name: /Informații: Valoare/ }).first();
    await expect(information).toBeVisible();
    expect(await information.evaluate((element) => (element as HTMLElement).tabIndex)).toBe(0);
    await information.focus();
    await expect(page.getByRole('tooltip')).toBeVisible();
    await page.keyboard.press('Escape');
    await page.goto('/admin/cuisines');
    const ro = page.getByRole('textbox', { name: 'Denumire în română', exact: true });
    await checkHelp(page, ro);
    await page.keyboard.press('Tab');
    await expect(page.getByRole('textbox', { name: 'Denumire în engleză', exact: true })).toBeFocused();
    await page.goto(`/app/locations/${account.location}/migration-center`);
    await page.getByRole('combobox', { name: 'Sursa meniului', exact: true }).click();
    await page.getByRole('option', { name: 'Fișier JSON exportat anterior (max. 5 MB)', exact: true }).click();
    const source = JSON.parse(readFileSync('tests/Fixtures/gloriafood-menu.json', 'utf8'));
    source.categories[0].name = 'x'.repeat(101);
    await page.locator('input[type=file]').setInputFiles({
        name: 'invalid-menu.json',
        mimeType: 'application/json',
        buffer: Buffer.from(JSON.stringify(source)),
    });
    await page
        .getByRole('checkbox', { name: 'Am dreptul de a importa meniul acestui restaurant.', exact: true })
        .check();
    await page.getByRole('button', { name: 'Preia și verifică', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('Meniul a fost preluat');
    await expect(page.getByText('Date din sursă nevalide', { exact: true })).toBeVisible();
    await expect(page.getByText('Câmp afectat:', { exact: false })).toBeVisible();
    await expect(page.getByText('Reguli respinse:', { exact: false })).toBeVisible();
    await expect(page.getByText('mig_issue_source_validation', { exact: true })).toHaveCount(0);
    await expect(page.getByText('categories.0.name', { exact: true })).toBeVisible();
    await expect(page.getByText('max:100', { exact: false })).toBeVisible();
    await page.screenshot({ path: 'test-results/import-validation-details.png', fullPage: true });
});
