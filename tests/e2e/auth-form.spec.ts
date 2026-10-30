import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';

test.skip(process.env.ZF_AUTH_FORM_E2E !== '1', 'Requires an isolated local database and array mail.');
const password = 'LocalAuthFormTest!2026';

async function completeReceipt(page: Page) {
    await page.getByRole('button', { name: 'Prefer o întrebare text', exact: true }).click();
    const prompt = await page.getByTestId('captcha-question').innerText();
    const sum = (prompt.match(/[0-9]+/g) ?? []).reduce((total, value) => total + Number(value), 0);
    await page.getByRole('textbox', { name: 'Răspunsul tău', exact: true }).fill(String(sum));
    await page.getByRole('button', { name: 'Confirmă bonul', exact: true }).click();
    await expect(page.getByTestId('captcha-verified')).toBeVisible();
}

test('registration Tab and Shift+Tab move directly between fields while help remains readable', async ({ page }) => {
    await page.goto('/register');
    const fields = ['name', 'email', 'password', 'password_confirmation'];
    await page.locator('#name').focus();
    for (const id of fields.slice(1)) {
        await page.keyboard.press('Tab');
        await expect(page.locator('#' + id)).toBeFocused();
    }
    await expect(page.locator('#captcha-answer')).toBeEnabled();
    await page.keyboard.press('Tab');
    await expect(page.locator('#captcha-answer')).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(page.locator('#password_confirmation')).toBeFocused();
    for (const id of fields.slice(0, -1).reverse()) {
        await page.keyboard.press('Shift+Tab');
        await expect(page.locator('#' + id)).toBeFocused();
    }
    await expect(page.locator('#email')).toHaveAttribute('aria-describedby', 'email-help');
    await expect(page.locator('#email-help')).toContainText('email');
    await page.getByRole('button', { name: 'Informații: E-mail', exact: true }).hover();
    await expect(page.getByRole('tooltip')).toContainText('email');
});

test('external reveal buttons mask on release, leave, cancellation, blur and keyboard release', async ({ page }) => {
    await page.goto('/register');
    for (const id of ['password', 'password_confirmation']) {
        const input = page.locator('#' + id);
        const button = page.locator(`button[aria-controls="${id}"]`);
        await input.fill(password);
        await expect(input).toHaveAttribute('autocomplete', 'new-password');
        const fieldBox = await input.boundingBox();
        const buttonBox = await button.boundingBox();
        expect(buttonBox!.x).toBeGreaterThanOrEqual(fieldBox!.x + fieldBox!.width - 0.5);
        await button.hover();
        await page.mouse.down();
        await expect(input).toHaveAttribute('type', 'text');
        await expect(button).toHaveAttribute('aria-pressed', 'true');
        await page.mouse.up();
        await expect(input).toHaveAttribute('type', 'password');
        await button.hover();
        await page.mouse.down();
        await page.mouse.move(0, 0);
        await expect(input).toHaveAttribute('type', 'password');
        await page.mouse.up();
        await button.hover();
        await page.mouse.down();
        await button.dispatchEvent('pointercancel');
        await expect(input).toHaveAttribute('type', 'password');
        await page.mouse.up();
        await input.focus();
        await page.keyboard.down('F2');
        await expect(input).toHaveAttribute('type', 'text');
        await page.keyboard.up('F2');
        await expect(input).toHaveAttribute('type', 'password');
        await page.keyboard.down('F2');
        await page.locator('#email').focus();
        await expect(input).toHaveAttribute('type', 'password');
        await page.keyboard.up('F2');
        await button.hover();
        await page.mouse.down();
        await page.evaluate(() => window.dispatchEvent(new Event('blur')));
        await expect(input).toHaveAttribute('type', 'password');
        await page.mouse.up();
        await expect(input).toHaveValue(password);
    }
});

test('duplicate email preserves both passwords and the receipt; correcting only email completes signup', async ({
    page,
}) => {
    const fixture = JSON.parse(readFileSync(process.env.ZF_AUTH_FORM_FIXTURE!, 'utf8'));
    await page.goto('/register');
    await completeReceipt(page);
    await page.locator('#name').fill('Local registration correction');
    await page.locator('#email').fill(fixture.email);
    await page.locator('#password').fill(password);
    await page.locator('#password_confirmation').fill(password);
    await page.getByRole('button', { name: 'Creează cont', exact: true }).click();
    await expect(page.getByRole('alert').filter({ hasText: /mail/i })).toBeVisible();
    await expect(page.locator('#password')).toHaveValue(password);
    await expect(page.locator('#password_confirmation')).toHaveValue(password);
    await expect(page.locator('#password')).toHaveAttribute('type', 'password');
    await expect(page.getByTestId('captcha-verified')).toBeVisible();
    expect(
        await page.evaluate(
            (value) => JSON.stringify(localStorage).includes(value) || JSON.stringify(sessionStorage).includes(value),
            password,
        ),
    ).toBe(false);
    await page.locator('#email').fill(`corrected-${Date.now()}@example.test`);
    await page.getByRole('button', { name: 'Creează cont', exact: true }).click();
    await expect(page).toHaveURL(/\/email\/verify$/);
});

test('mobile touch reveals only while held and English labels remain available', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });
    const page = await context.newPage();
    try {
        await page.goto('/register');
        await page.locator('#password').fill(password);
        const button = page.locator('button[aria-controls="password"]');
        await button.scrollIntoViewIfNeeded();
        const bounds = await button.boundingBox();
        const cdp = await context.newCDPSession(page);
        await cdp.send('Input.dispatchTouchEvent', {
            type: 'touchStart',
            touchPoints: [{ x: bounds!.x + 22, y: bounds!.y + bounds!.height / 2 }],
        });
        await expect(page.locator('#password')).toHaveAttribute('type', 'text');
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await expect(page.locator('#password')).toHaveAttribute('type', 'password');
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
        await page.screenshot({ path: 'test-results/auth-form-mobile.png', fullPage: true });
        await page.getByTestId('language-selector').click();
        await page.getByRole('option', { name: 'English', exact: true }).click();
        await expect(page.getByRole('button', { name: 'Hold to reveal password', exact: true })).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Hold to reveal password confirmation', exact: true }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Information: Password', exact: true }).hover();
        await expect(page.getByRole('tooltip')).toContainText('F2');
    } finally {
        await context.close();
    }
});
