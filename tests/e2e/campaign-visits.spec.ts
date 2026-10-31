import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test('deduplicates UTM entries, attributes a contact request and reports it in platform admin', async ({ page }) => {
    test.skip(
        process.env.ZF_CAMPAIGNS_E2E !== '1',
        'Requires the isolated campaign database and administrator fixture.',
    );
    test.setTimeout(120000);
    const admin = JSON.parse(readFileSync(process.env.ZF_LEADS_ADMIN!, 'utf8'));
    const nonce = Date.now();
    const campaign = `ZionFood QA ${nonce}`;
    const name = `Campaign contact ${nonce}`;
    const params = new URLSearchParams({
        utm_source: 'Facebook',
        utm_medium: 'paid_social',
        utm_campaign: campaign,
        utm_content: 'ad-a',
        utm_term: 'migration',
        utm_id: `qa-${nonce}`,
    });
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/?' + params);
    await expect(page.locator('.landing')).toHaveAttribute('data-enhanced', 'true');
    await page.reload();
    await page.goto('/en?' + params);
    const form = page.locator('#contact');
    await form.getByRole('textbox', { name: 'Your name', exact: true }).fill(name);
    await form.getByRole('textbox', { name: 'Email address', exact: true }).fill(`campaign-${nonce}@example.test`);
    await form.getByRole('textbox', { name: 'Phone number', exact: true }).fill('+40 770 123 456');
    await form.getByRole('button', { name: "Let's talk", exact: true }).click();
    await expect(page.getByTestId('lead-confirmation')).toContainText("We've received your request.");
    await page.goto('/');
    await page.goto('/login');
    await page.locator('#email').fill(admin.email);
    await page.locator('#password').fill(admin.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
    const report = '/admin/campaigns?utm_campaign=' + encodeURIComponent(campaign);
    await page.goto(report);
    await expect(page.getByRole('heading', { name: 'Campanii și vizite', exact: true })).toBeVisible();
    await expect(page.getByTestId('campaign-metric-visits').locator('strong')).toHaveText('1');
    await expect(page.getByTestId('campaign-metric-leads').locator('strong')).toHaveText('1');
    const visit = page.locator('article[data-visit-id]');
    await expect(visit).toHaveCount(1);
    await expect(visit).toContainText('facebook');
    await expect(visit).toContainText('paid_social');
    await expect(visit).toContainText('ad-a');
    await expect(visit).toContainText(`qa-${nonce}`);
    await page.getByRole('link', { name: 'Vezi solicitările atribuite (1)', exact: true }).click();
    await expect(page).toHaveURL(/campaign_visit=/);
    const lead = page.locator('article[data-lead-id]').filter({ hasText: name });
    await expect(lead).toContainText(campaign);
    await expect(lead).toContainText('Campanie atribuită:');
    await lead.getByRole('combobox', { name: `Starea solicitării pentru ${name}`, exact: true }).click();
    await page.getByRole('option', { name: 'Calificată', exact: true }).click();
    await expect(lead.locator('span.rounded-full')).toHaveText('Calificată');
    await page.goto(report);
    await expect(page.getByTestId('campaign-metric-qualified').locator('strong')).toHaveText('1');
    await page.setViewportSize({ width: 1440, height: 1000 });
    const campaignControl = await page.getByRole('combobox', { name: 'Campanie UTM', exact: true }).boundingBox();
    const applyControl = await page.getByRole('button', { name: 'Aplică filtrele', exact: true }).boundingBox();
    expect(campaignControl!.y + campaignControl!.height).toBeLessThanOrEqual(applyControl!.y);
    await page.screenshot({ path: 'test-results/campaigns-desktop.png', animations: 'disabled' });
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await page.screenshot({ path: 'test-results/campaigns-mobile.png', fullPage: true, animations: 'disabled' });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.getByRole('link', { name: 'Vezi solicitările atribuite (1)', exact: true }).click();
    await page.getByRole('button', { name: `Șterge solicitarea pentru ${name}`, exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Șterge solicitarea', exact: true }).click();
    await expect(page.locator('article[data-lead-id]')).toHaveCount(0);
    await page.goto(report);
    await expect(page.getByTestId('campaign-metric-leads').locator('strong')).toHaveText('0');
    await expect(page.getByTestId('campaign-metric-qualified').locator('strong')).toHaveText('0');
    await expect(page.getByTestId('campaign-metric-visits').locator('strong')).toHaveText('1');
    expect(errors).toEqual([]);
});
