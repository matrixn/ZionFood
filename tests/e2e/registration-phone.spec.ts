import { test, expect, type Page } from '@playwright/test';

test.skip(process.env.ZF_PHONE_E2E !== '1', 'Requires isolated registration storage and array mail.');

async function chooseCountry(page: Page, search: string, option: string, english = false) {
    await page.getByRole('combobox', { name: english ? 'Country / code' : 'Țară și prefix', exact: true }).click();
    await page.getByRole('searchbox', { name: english ? 'Search country or calling code' : 'Caută țara sau prefixul', exact: true }).fill(search);
    await page.getByRole('option', { name: option, exact: true }).click();
}

test('country prefix picker uses local flags international search regional validation and responsive bilingual help', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/register');
    const field = page.getByTestId('registration-phone');
    const country = page.getByRole('combobox',{name:'Țară și prefix',exact:true});
    await expect(country.getByText('+40',{exact:true})).toBeVisible();
    await expect(country.locator('.sr-only')).toHaveText('Romania');
    await expect(country.locator('img')).toBeVisible();
    await expect.poll(() => country.locator('img').evaluate(img => (img as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);
    await expect(page.locator('#phone')).toHaveAttribute('required','');
    await page.locator('#phone').fill('123');
    await page.locator('#password').focus();
    await expect(field.getByRole('alert')).toContainText('țara selectată');
    await chooseCountry(page,'Germany','Germany (+49)');
    await expect(country.getByText('+49',{exact:true})).toBeVisible();
    await page.locator('#phone').fill('030 901820');
    await expect(field.getByRole('alert')).toHaveCount(0);
    await chooseCountry(page,'United States','United States (+1)');
    await page.locator('#phone').fill('2025550123');
    await expect(field.getByRole('alert')).toHaveCount(0);
    await chooseCountry(page,'Canada','Canada (+1)');
    await expect(field.getByRole('alert')).toBeVisible();
    await expect(page.locator('#phone')).toHaveValue('2025550123');
    await page.locator('#phone').fill('4165550123');
    await expect(field.getByRole('alert')).toHaveCount(0);
    await country.focus();
    await page.keyboard.press('F1');
    await expect(page.getByRole('tooltip')).toContainText('internațională');
    await page.keyboard.press('Escape');
    await page.setViewportSize({width:390,height:844});
    await country.click();
    await page.getByRole('searchbox',{name:'Caută țara sau prefixul',exact:true}).fill('Romania');
    await expect(page.getByRole('option',{name:'Romania (+40)',exact:true})).toBeVisible();
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await page.screenshot({path:'storage/app/private/registration-phone-0049-20261103/phone-picker-mobile.png',fullPage:false,animations:'disabled'});
    await page.getByRole('option',{name:'Romania (+40)',exact:true}).click();
    await page.getByTestId('language-selector').click();
    await page.getByRole('option',{name:'English',exact:true}).click();
    await expect(page.getByRole('textbox',{name:'Phone number',exact:true})).toBeVisible();
    await chooseCountry(page,'Germany','Germany (+49)',true);
    await page.locator('#phone').fill('030901820');
    await page.screenshot({path:'storage/app/private/registration-phone-0049-20261103/phone-english-mobile.png',fullPage:true,animations:'disabled'});
    expect(errors).toEqual([]);
});

test('prevents invalid phone submission and completes registration with the selected country', async ({ page }) => {
    const requests: { country: string; phone: string }[] = [];
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => {
        if (request.method() === 'POST' && new URL(request.url()).pathname === '/register') {
            const data = request.postDataJSON();
            requests.push({country:data.phone_country,phone:data.phone});
        }
    });
    await page.goto('/register');
    await chooseCountry(page,'Germany','Germany (+49)');
    await page.locator('#name').fill('Local Phone Browser');
    await page.locator('#email').fill(`phone-${Date.now()}@example.test`);
    await page.locator('#password').fill('LocalPhonePassword!2026');
    await page.locator('#password_confirmation').fill('LocalPhonePassword!2026');
    await page.locator('#phone').fill('123');
    await page.getByRole('button',{name:'Prefer o întrebare text',exact:true}).click();
    const prompt = await page.getByTestId('captcha-question').innerText();
    const answer = (prompt.match(/[0-9]+/g) ?? []).reduce((sum,value)=>sum+Number(value),0);
    await page.getByRole('textbox',{name:'Răspunsul tău',exact:true}).fill(String(answer));
    await page.getByRole('button',{name:'Verifică răspunsul',exact:true}).click();
    await expect(page.getByTestId('captcha-verified')).toBeVisible();
    await page.getByRole('button',{name:'Creează cont',exact:true}).click();
    await expect(page.locator('#phone')).toBeFocused();
    expect(requests).toEqual([]);
    await expect(page.locator('#password')).toHaveValue('LocalPhonePassword!2026');
    await page.locator('#phone').fill('030901820');
    await page.getByRole('button',{name:'Creează cont',exact:true}).click();
    await expect(page).toHaveURL(/\/email\/verify$/, {timeout:20000});
    expect(requests).toEqual([{country:'DE',phone:'030901820'}]);
    expect(errors).toEqual([]);
});
