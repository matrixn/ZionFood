import {test,expect as baseExpect} from '@playwright/test';
const expect=baseExpect.configure({timeout:15000});
import {readFileSync} from 'node:fs';
const fixture=()=>JSON.parse(readFileSync('storage/app/private/browser-overview.json','utf8'));
test('overview shows real aggregated data, accessible explanations and responsive charts',async({page,browser})=>{
 test.setTimeout(90000);page.setDefaultTimeout(15000);const account=fixture();
 await page.goto('/login');await page.locator('#email').fill(account.email);await page.locator('#password').fill(account.password);await page.getByRole('button',{name:'Autentificare',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Restaurantul tău, în cifre',exact:true})).toBeVisible();
 await expect(page.getByRole('heading',{name:'Top 5 preparate comandate',exact:true})).toBeVisible();
 await expect(page.getByRole('img',{name:'Evoluția pe zile',exact:true})).toBeVisible();
 await page.getByRole('button',{name:'Informații: Valoare comenzi finalizate',exact:true}).hover();
 await expect(page.getByRole('tooltip')).toContainText('Nu reprezintă încasări confirmate');
 await page.mouse.move(0,0);
 const context=await browser.newContext();const guest=await context.newPage();
 const visit=guest.waitForResponse(response=>response.url().endsWith('/visit') && response.request().method()==='POST');
 await guest.goto(`/store/${account.location}`);expect((await visit).status()).toBe(204);await context.close();
 await page.reload();

 await page.screenshot({path:'test-results/overview-desktop.png',fullPage:true});
 await page.getByRole('combobox',{name:'Perioada statisticilor',exact:true}).click();await page.getByRole('option',{name:'Ultimele 7 zile',exact:true}).click();await expect(page).toHaveURL(/days=7/);
 await page.setViewportSize({width:390,height:844});expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
 await page.screenshot({path:'test-results/overview-mobile.png',fullPage:true});
});
test('field help opens on hover and keyboard without editing a field and table details stay in tooltips',async({page})=>{
 test.setTimeout(90000);page.setDefaultTimeout(15000);const account=fixture();
 await page.goto('/login');await page.getByRole('button',{name:'Informații: E-mail',exact:false}).hover();await expect(page.getByRole('tooltip')).toContainText('adresă de email validă');
 await page.locator('#email').fill(account.email);await page.locator('#password').fill(account.password);await page.getByRole('button',{name:'Autentificare',exact:true}).click();await expect(page).toHaveURL(/app/);
 await page.goto(`/app/locations/${account.location}/tables`);
 await page.getByRole('button',{name:'Dezactivează toate mesele',exact:true}).click();await page.getByRole('alertdialog').getByRole('button',{name:'Confirmă',exact:true}).click();
 const card=page.getByTestId('table-card').first();await expect(card).toHaveAttribute('data-state','table_closed');
 await expect(card.getByText('Dezactivată de personal',{exact:true})).toBeVisible();
 await expect(card.getByText('Personalul a închis meniul de comandă',{exact:false})).toHaveCount(0);
 await card.getByRole('button',{name:'Informații: Dezactivată de personal',exact:true}).focus();await expect(page.getByRole('tooltip')).toContainText('QR-ul este în continuare valabil');
 await page.keyboard.press('Escape');await expect(page.getByRole('tooltip')).toHaveCount(0);
 expect(await card.evaluate(el=>getComputedStyle(el).borderTopWidth)).toBe('2px');
 await page.setViewportSize({width:390,height:844});expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
 await page.screenshot({path:'test-results/tables-compact-mobile.png',fullPage:true});
});
