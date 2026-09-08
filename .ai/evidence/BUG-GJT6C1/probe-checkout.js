const { chromium } = require('playwright');

const BASE = 'http://slaunchpad.localhost';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
    page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });

    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    console.log('product page:', page.url(), '| lang =', await page.evaluate(() => document.documentElement.lang));
    const addBtn = page.locator('#product-addtocart-button');
    console.log('add-to-cart buttons:', await addBtn.count());
    await addBtn.first().click();
    await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
    await page.goto(BASE + '/checkout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);

    const info = await page.evaluate(() => {
        const q = (s) => document.querySelector(s);
        const di = q('.delivery-information');
        const inputs = [...document.querySelectorAll('input[name]')].map(i => i.name).filter(Boolean);
        const selects = [...document.querySelectorAll('select[name]')].map(s => s.name).filter(Boolean);
        return {
            lang: document.documentElement.lang,
            url: location.href,
            title: document.title,
            hasDelivery: !!di,
            deliveryVisible: di ? !!(di.offsetParent || di.getClientRects().length) : false,
            deliveryDateInput: !!q('.delivery-information input[x-ref="deliveryDateInput"], .delivery-information input[wire\\:model="deliveryDate"]'),
            flatpickrLoaded: typeof window.flatpickr === 'function',
            inputNames: [...new Set(inputs)].slice(0, 40),
            selectNames: [...new Set(selects)].slice(0, 25),
        };
    });
    console.log(JSON.stringify(info, null, 2));
    console.log('console errors:', errs.length, JSON.stringify(errs.slice(0, 5)));
    await page.screenshot({ path: __dirname + '/probe-checkout.png' });
    await browser.close();
})();
