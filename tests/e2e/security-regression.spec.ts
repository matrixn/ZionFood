import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';

const fixturePath = process.env.ZF_SECURITY_FIXTURE;
test.skip(!fixturePath, 'Set ZF_SECURITY_FIXTURE to an isolated browser-security.json fixture.');

async function login(page: Page, manager: boolean) {
    const account = JSON.parse(readFileSync(fixturePath!, 'utf8'));
    await page.goto('/login');
    await page.locator('#email').fill(manager ? account.manager_email : account.owner_email);
    await page.locator('#password').fill(manager ? account.manager_password ?? account.password : account.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    return `/app/locations/${account.location}`;
}

test('restricted manager keeps settings and embedded editors while operational switches stay locked', async ({ page }) => {
    const base = await login(page, true);
    await page.goto(base + '/settings');
    for (const name of ['Primește comenzi online', 'Ridicare', 'Livrare']) {
        await expect(page.getByRole('switch', { name, exact: true })).toBeDisabled();
    }
    await page.getByRole('textbox', { name: 'Nume', exact: true }).fill('Security manager rename');
    await page.getByRole('button', { name: 'Salvează setările', exact: true }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Setările locației au fost salvate.' })).toBeVisible();
    await page.goto(base + '/setup/hours');
    await page.locator('input[type=time]').first().fill('10:30');
    await page.getByRole('button', { name: 'Salvează setările', exact: true }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Setările locației au fost salvate.' })).toBeVisible();
    await expect(page).toHaveURL(base + '/setup/hours');
    await page.goto(base + '/setup/delivery');
    await page.getByRole('button', { name: 'Salvează setările', exact: true }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Setările locației au fost salvate.' })).toBeVisible();
    await expect(page).toHaveURL(base + '/setup/delivery');
});

test('owner operational switches remain editable', async ({ page }) => {
    const base = await login(page, false);
    await page.goto(base + '/settings');
    await expect(page.getByRole('switch', { name: 'Livrare', exact: true })).toBeEnabled();
});

test('public diagnostic hides administrator address and real HTTP mutations retain CSRF', async ({ page }) => {
    await page.goto('/test-email');
    await expect(page.getByRole('heading', { name: 'Testare email', exact: true })).toBeVisible();
    const body = await page.content();
    expect(body).not.toContain('dummy@dummy.com');
    expect(body).not.toContain('&quot;recipient&quot;');
    const response = await page.request.post('/register', { data: { email: 'csrf-local@example.test' }, headers: { Accept: 'application/json' } });
    expect(response.status()).toBe(419);
});
