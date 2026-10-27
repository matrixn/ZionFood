import { test, expect as baseExpect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const expect=baseExpect.configure({timeout:15000});

test('automatic generation stays compact and reviews live results across workspace pages without refresh',async({page})=>{
    test.setTimeout(90000);
    page.setDefaultTimeout(15000);
    const fixture=JSON.parse(readFileSync('storage/app/private/browser-services.json','utf8'));
    const base=`/app/locations/${fixture.location}`;
    let ready=false;
    let reviewed=false;
    let queued=false;
    let read=false;
    const draft='browser-review-fixture';
    const notice='browser-notification-fixture';
    let item='';
    // Only AI/notification delivery is simulated; no configured provider or API key is touched.
    await page.route('**/menu-content',async route=>{
        const response=await route.fetch();
        const body=(await response.text()).replaceAll('"aiReady":false','"aiReady":true').replaceAll('&quot;aiReady&quot;:false','&quot;aiReady&quot;:true');
        await route.fulfill({response,body});
    });
    await page.route('**/app/notifications',route=>route.fulfill({json:{notifications:ready?[{public_id:notice,read_at:read?'2026-10-27T10:00:00Z':null,created_at:'2026-10-27T10:00:00Z',draft,location:fixture.location,item_name:'Preparat de test',status:reviewed?'approved':'ready',kind:'analyze'}]:[]}}));
    await page.route('**/app/notifications/*/read',route=>{read=true;return route.fulfill({json:{read:true}});});
    await page.route('**/menu-content/items/*',async route=>{
        const response=await route.fetch();
        if(route.request().method()==='GET'){
            const data=await response.json(); item=data.public_id;
            if(queued) data.drafts=[{public_id:draft,kind:'analyze',status:ready?'ready':'queued',created_at:new Date().toISOString()}];
            return route.fulfill({response,json:data});
        }
        return route.fulfill({response});
    });
    await page.route('**/menu-content/generate',async route=>{
        const data=route.request().postDataJSON();
        expect(data.kind).toBe('analyze');expect(data.options).toEqual(['nutrition','food']);
        queued=true;
        await route.fulfill({status:202,json:{queued:1}});
    });
    await page.route('**/menu-content/drafts/*',route=>{
        if(route.request().method()==='POST'){
            const data=route.request().postDataJSON(); expect(data.action).toBe('approve');expect(data.sections).toContain('nutrition');reviewed=true;
            return route.fulfill({json:{reviewed:true}});
        }
        return route.fulfill({json:{public_id:draft,item_name:'Preparat de test',item,location:fixture.location,kind:'analyze',status:reviewed?'approved':'ready',source_current:true,can_write:true,error_code:null,report:{notes:'Rezultat simulat pentru verificarea interfeței.'},proposal:{nutrition:{basis:'per_100g',estimated:true,method:'ai_estimate',portion_grams:350,values:{energy_kcal:142.857,protein_g:8.571},per_serving:{energy_kcal:500,protein_g:30},assumptions:['Proporții aproximative.']},allergens:['milk'],may_contain:[],labels:['vegetarian']}}});
    });
    await page.goto('/login');await page.locator('#email').fill(fixture.email);await page.locator('#password').fill(fixture.password);await page.getByRole('button',{name:'Autentificare',exact:true}).click();await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto(base+'/menu-content');
    await expect(page.getByRole('heading',{name:'Din preparat, în meniu. Simplu.'})).toBeVisible();
    await expect(page.getByText('Date alimentare din GloriaFood',{exact:true})).toHaveCount(0);
    await expect(page.getByText('Număr de porții',{exact:true})).toHaveCount(0);
    await page.getByRole('button',{name:'Ce este publicat',exact:true}).click();
    const published=page.getByRole('dialog');
    await published.getByRole('combobox',{name:'Limbă',exact:true}).selectOption('de');
    const addLanguage=published.getByRole('button',{name:'Adaugă traducere manuală',exact:true});
    if(await addLanguage.isEnabled()) await addLanguage.click();
    await published.locator('summary').filter({hasText:'Deutsch'}).click();
    await published.getByLabel('Denumirea preparatului',{exact:true}).fill('Testgericht gespeichert');
    const [saved]=await Promise.all([page.waitForResponse(r=>r.url().includes('/menu-content/items/') && r.request().method()==='PUT'),published.getByRole('button',{name:'Salvează și publică datele',exact:true}).click()]);
    expect(saved.status(),await saved.text()).toBe(200);
    await expect(published).toHaveCount(0);
    const stored=await page.request.get(`${base}/menu-content/items/${item}`);
    expect((await stored.json()).content.translations.de.name).toBe('Testgericht gespeichert');
    await page.getByRole('spinbutton',{name:'Greutatea unei porții',exact:true}).fill('350');
    await page.getByLabel('Ingrediente pentru analiză',{exact:true}).fill('Feta, roșii, ulei de măsline');
    await expect(page.getByRole('button',{name:'Generează pentru preparat',exact:true})).toBeEnabled();
    await page.screenshot({path:'test-results/automation-desktop.png',fullPage:true});
    await page.setViewportSize({width:390,height:844});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await page.getByRole('button',{name:'Generează pentru preparat',exact:true}).click();
    await expect(page.getByRole('status').filter({hasText:'Cererea este în lucru'}).first()).toBeVisible();
    await page.screenshot({path:'test-results/automation-mobile.png',fullPage:true});
    await page.goto(base+'/orders'); ready=true;
    await page.evaluate(()=>window.dispatchEvent(new Event('focus')));
    await page.getByRole('button',{name:/Preparat de test.*Vezi rezultatul/}).click();
    const dialog=page.getByRole('dialog');
    await expect(dialog).toContainText('Valori estimative generate cu AI');
    await expect(dialog).toContainText('500');
    const approve=dialog.getByRole('button',{name:'Aprobă selecția și publică',exact:true});
    await expect(approve).toBeInViewport();
    expect((await dialog.boundingBox())!.width).toBeLessThanOrEqual(390);
    await page.screenshot({path:'test-results/automation-review-mobile.png',fullPage:true});
    await approve.click();await expect(dialog).toHaveCount(0);
    expect(reviewed).toBeTruthy();
});

test('platform menu AI settings keep keys private and fit mobile without changing provider settings', async ({ page }) => {
    page.setDefaultTimeout(15000);
    const fixture = JSON.parse(readFileSync('storage/app/private/browser-email-admin.json', 'utf8'));
    await page.goto('/login');
    await page.locator('#email').fill(fixture.email);
    await page.locator('#password').fill(fixture.password);
    await page.getByRole('button', { name: 'Autentificare', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/locations\//);
    await page.goto('/admin/menu-ai');
    await expect(page.getByRole('heading', { name: 'Configurare AI pentru meniuri', exact: true })).toBeVisible();
    await expect(page.locator('input[type="password"]')).toHaveValue('');
    await expect(page.getByText('Groq este opțiunea inițială cu plan gratuit.', { exact: false })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await page.screenshot({ path: 'test-results/menu-ai-admin-mobile.png', fullPage: true, animations: 'disabled' });
});
