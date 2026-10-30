import { test, expect, type Page } from '@playwright/test';

test.skip(process.env.ZF_CAPTCHA_E2E !== '1', 'Requires an isolated local database and array mail transport.');

async function sumOnReceipt(page: Page) {
    const text = await page.getByTestId('captcha-question').innerText();
    return (text.match(/[0-9]+/g) ?? []).reduce((sum, value) => sum + Number(value), 0);
}

test('Romanian receipt has a raster challenge, keyboard help and a real one-use registration confirmation', async ({ page }) => {
    await page.goto('/register');
    await expect(page.getByRole('heading', { name: 'Un pas și ești gata', exact: true })).toBeVisible();
    const image = page.getByRole('img', { name: /Cod ZionFood din patru cifre/ });
    await expect(image).toBeVisible();
    await expect(image).toHaveAttribute('src', /^data:image\/png;base64,/);
    await expect(page.getByRole('button', { name: 'Creează cont', exact: true })).toBeDisabled();
    await page.getByRole('button', { name: 'Prefer o întrebare text', exact: true }).click();
    await expect(page.getByTestId('captcha-question')).toBeVisible();
    for (const digit of String(await sumOnReceipt(page))) {
        await page.getByRole('button', { name: `Adaugă cifra ${digit}`, exact: true }).click();
    }
    await page.getByRole('button', { name: 'Verifică răspunsul', exact: true }).click();
    await expect(page.getByTestId('captcha-verified')).toBeVisible();
    await page.locator('#name').fill('Local Receipt Browser');
    await page.locator('#email').fill(`receipt-${Date.now()}@example.test`);
    await page.locator('#password').fill('LocalReceiptPassword!2026');
    await page.locator('#password_confirmation').fill('LocalReceiptPassword!2026');
    await page.getByRole('button', { name: 'Creează cont', exact: true }).click();
    await expect(page).toHaveURL(/\/email\/verify$/);
});

test('wrong answer recovers, language changes refresh the question and verification expires on mobile', async ({ page }) => {
    await page.clock.install();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/register');
    await expect(page.getByRole('img', { name: /Cod ZionFood din patru cifre/ })).toBeVisible();
    await page.getByRole('button', { name: 'Prefer o întrebare text', exact: true }).click();
    await expect(page.getByTestId('captcha-question')).toBeVisible();
    await page.getByRole('textbox', { name: 'Răspunsul tău', exact: true }).fill(String(1 + await sumOnReceipt(page)));
    await page.getByRole('button', { name: 'Verifică răspunsul', exact: true }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Răspunsul nu s-a potrivit' })).toBeVisible();
    await expect(page.getByTestId('captcha-question')).toBeVisible();
    await page.getByTestId('language-selector').click();
    await page.getByRole('option', { name: 'English', exact: true }).click();
    await expect(page.getByTestId('captcha-question')).toContainText('For this check, add');
    await page.getByRole('textbox', { name: 'Your answer', exact: true }).fill(String(await sumOnReceipt(page)));
    await page.getByRole('button', { name: 'Check my answer', exact: true }).click();
    await expect(page.getByTestId('captcha-verified')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/captcha-verified-mobile.png', fullPage: true });
    await page.clock.fastForward(301000);
    await expect(page.getByRole('alert').filter({ hasText: 'Verification expired.' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Create account', exact: true })).toBeDisabled();
});
