import { readFileSync } from 'node:fs';
import { expect, test, type Page } from '@playwright/test';
import { explicitRonPricing } from './support/pricingPreference';

const fixturePath = process.env.ZF_REWARDS_E2E_FIXTURE;
const account = () =>
    JSON.parse(readFileSync(fixturePath!, 'utf8')) as { email: string; password: string; location: string };
test.skip(!fixturePath, 'Requires the isolated Rewards fixture.');

async function login(page: Page) {
    const fixture = account();
    await explicitRonPricing(page);
    await page.goto('/login');
    await page.locator('#email').fill(fixture.email);
    await page.locator('#password').fill(fixture.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto('/app/locations/' + fixture.location + '/rewards');
}

async function createCampaign(page: Page, name: string, method: string) {
    await page.getByRole('button', { name: 'Campanie nouă' }).click();
    await page.getByLabel('Denumirea campaniei', { exact: true }).fill(name);
    await page.getByRole('combobox', { name: 'Cum se adună achizițiile' }).click();
    await page.getByRole('option', { name: method, exact: true }).click();
    const eligible = page.getByRole('combobox', { name: 'Produse eligibile' });
    await eligible.press('Space');
    const products = page.getByRole('listbox').getByRole('option', { name: 'Pizza', exact: true });
    await products.click();
    await page.getByRole('heading', { name: 'Campanie nouă', exact: true }).click();
    await expect(eligible).toHaveAttribute('aria-expanded', 'false');
    await expect(products).toBeHidden();
    await page.getByLabel('Prag de cumpărare', { exact: true }).fill('1');
    await page.getByRole('combobox', { name: 'Produs cadou' }).click();
    await page.getByRole('option', { name: 'Pizza', exact: true }).click();
    await page.getByRole('button', { name: 'Salvează campania' }).click();
    await expect(page.getByRole('heading', { name, exact: true })).toBeVisible();
    await expect(page.getByRole('status').filter({ hasText: 'Campania a fost salvată.' })).toBeVisible();
}

async function createWallet(guest: Page) {
    await explicitRonPricing(guest);
    await guest.setViewportSize({ width: 390, height: 844 });
    await guest.goto('/store/' + account().location);
    await guest.getByRole('link', { name: 'ZionFood Rewards' }).click();
    await guest.getByRole('button', { name: 'Creează cardul gratuit' }).click();
    await expect(guest.getByRole('button', { name: 'Arată codul meu QR' })).toBeVisible();
    expect(await guest.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
}

async function showQr(guest: Page) {
    const previous = await guest.locator('code').allTextContents();
    await guest.getByRole('button', { name: 'Arată codul meu QR' }).click();
    await expect(guest.getByRole('img', { name: 'Cod QR temporar Rewards' })).toBeVisible();
    await expect(guest.locator('code')).not.toHaveText(previous[0] ?? '');
    return (await guest.locator('code').textContent())!;
}

test('staff create a campaign, credit a receipt and hand a reward to a mobile customer', async ({ page, browser }) => {
    await login(page);
    const campaign = 'Clubul de test ' + Date.now();
    await createCampaign(page, campaign, 'QR-ul clientului');
    await page.getByRole('button', { name: 'Operare la tejghea' }).click();
    const context = await browser.newContext();
    const guest = await context.newPage();
    await createWallet(guest);
    const code = await showQr(guest);
    const qr = (await guest.getByRole('img', { name: 'Cod QR temporar Rewards' }).getAttribute('src'))!;
    await page.evaluate(async (imageSource) => {
        const image = new Image();
        image.src = imageSource;
        await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 224;
        const context = canvas.getContext('2d')!;
        navigator.mediaDevices.getUserMedia = async () => {
            const stream = canvas.captureStream(10);
            const timer = window.setInterval(() => {
                if (stream.getVideoTracks()[0].readyState === 'ended') window.clearInterval(timer);
                else context.drawImage(image, 0, 0);
            }, 100);
            return stream;
        };
    }, qr);
    await page.bringToFront();
    await page.getByRole('button', { name: 'Scanează cu camera' }).click();
    await expect(page.getByRole('textbox', { name: 'Codul de pe card', exact: true })).toHaveValue(code);
    const confirmation = page.locator('section').filter({
        has: page.getByRole('heading', { name: 'Confirmă achiziția', exact: true }),
    });
    await confirmation.getByRole('combobox', { name: 'Campanie', exact: true }).click();
    await page.getByRole('option', { name: campaign, exact: true }).click();
    await page.getByLabel('Referința bonului', { exact: true }).fill('browser-' + Date.now());
    await page.getByRole('button', { name: 'Adaugă achiziția' }).click();
    await expect(page.getByText('Achiziția a fost adăugată pe cardul clientului.')).toBeVisible();
    await guest.getByRole('button', { name: 'Actualizează' }).click();
    await expect(guest.getByText('Recompense disponibile: 1').first()).toBeVisible();
    await page.getByLabel('Codul de pe card', { exact: true }).fill(await showQr(guest));
    await page.getByRole('button', { name: 'Caută cardul' }).click();
    await page.getByRole('button', { name: 'Marchează ca predată' }).click();
    await expect(page.getByText('Ai înregistrat predarea: 1 × Pizza.')).toBeVisible();
    await guest.reload();
    await expect(guest.getByText('Recompense disponibile: 0')).toBeVisible();
    await context.close();
});

test('venue QR submits a numbered request and waits for staff approval', async ({ page, browser }) => {
    await login(page);
    const campaign = 'QR la locație ' + Date.now();
    await createCampaign(page, campaign, 'QR temporar al locației');
    await page.getByRole('button', { name: 'Operare la tejghea' }).click();
    await page.getByRole('combobox', { name: 'Campanie', exact: true }).click();
    await page.getByRole('option', { name: campaign, exact: true }).click();
    await page.getByRole('button', { name: 'Generează QR' }).click();
    await expect(page.getByRole('img', { name: 'Cod QR temporar Rewards' })).toBeVisible();
    const code = await page.locator('code').textContent();
    const context = await browser.newContext();
    const guest = await context.newPage();
    await createWallet(guest);
    await guest.goto('/rewards/' + account().location + '#join=' + code);
    await guest.reload();
    await guest.getByRole('button', { name: 'Trimite achiziția spre confirmare' }).click();
    const request = guest.getByText(/Arată echipei numărul #\d+/);
    await expect(request).toBeVisible();
    await expect(guest.getByText('Recompense disponibile: 0')).toBeVisible();
    const id = (await request.textContent())!.match(/#\d+/)![0];
    await page.bringToFront();
    await page.getByRole('button', { name: campaign + ' · ' + id }).click({ timeout: 15000 });
    await page.getByLabel('Referința bonului', { exact: true }).fill('venue-browser-' + Date.now());
    await page.getByRole('button', { name: 'Adaugă achiziția' }).click();
    await expect(page.getByText('Achiziția a fost adăugată pe cardul clientului.')).toBeVisible();
    await guest.getByRole('button', { name: 'Actualizează' }).click();
    await expect(guest.getByText('Recompense disponibile: 1').first()).toBeVisible();
    await context.close();
});

test('checkout opt-in credits a digital card when the kitchen completes the order', async ({ page, browser }) => {
    await login(page);
    const campaign = 'Comenzi online ' + Date.now();
    await createCampaign(page, campaign, 'Comandă online finalizată');
    const context = await browser.newContext();
    const guest = await context.newPage();
    await createWallet(guest);
    await guest.getByRole('link', { name: 'Înapoi la meniu' }).click();
    await guest.getByTestId('menu-product-card').filter({ hasText: 'Pizza' }).click();
    await guest.getByRole('button', { name: /Adaugă în coș/ }).click();
    await guest.getByRole('button', { name: /Coșul tău/ }).click();
    await guest.getByRole('button', { name: 'Continuă la finalizare' }).click();
    await guest.getByRole('textbox', { name: 'Numele tău', exact: true }).fill('Rewards Browser Guest');
    await guest.getByRole('textbox', { name: 'Număr de telefon', exact: true }).fill('0712345678');
    const optIn = guest.getByRole('checkbox', { name: 'Atașează cardul ZionFood Rewards la această comandă' });
    await expect(optIn).not.toBeChecked();
    await optIn.check();
    await guest.getByRole('button', { name: 'Plasează comanda', exact: true }).click();
    await expect(guest).toHaveURL(/\/track\//);
    const order = guest.url().split('/').at(-1)!;
    await page.goto('/app/locations/' + account().location + '/kitchen');
    const card = page.locator('article').filter({ hasText: '#' + order.slice(-8) });
    for (const label of ['Acceptă comanda', 'Începe pregătirea', 'Marchează ca pregătită', 'Finalizează comanda']) {
        await card.getByRole('button', { name: label, exact: true }).click();
    }
    await expect(card).toHaveCount(0);
    await guest.goto('/rewards/' + account().location);
    await expect(
        guest.locator('article').filter({ hasText: campaign }).getByText('Recompense disponibile: 1'),
    ).toBeVisible();
    await context.close();
});
