const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const ctx = await browser.newContext({ viewport: { width: 375, height: 812 } });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(5000);
    const out = await page.evaluate(() => {
        const res = {};
        for (const [k, sel] of [['shipping', '.form-shipping-address'], ['billing', '#checkout-step-billing']]) {
            const labels = [...document.querySelectorAll(sel + ' label.label')].filter(l => l.offsetWidth > 0);
            const inputs = [...document.querySelectorAll(sel + ' input.input-text, ' + sel + ' select')].filter(i => i.offsetWidth > 0);
            res[k] = {
                labelXs: [...new Set(labels.map(l => +l.getBoundingClientRect().x.toFixed(1)))],
                labelPadLs: [...new Set(labels.map(l => getComputedStyle(l).paddingLeft))],
                inputXs: [...new Set(inputs.map(i => +i.getBoundingClientRect().x.toFixed(1)))],
            };
        }
        res.paymentRadioX = (() => { const e = document.querySelector('.payment-methods input[type=radio]'); return e ? +e.getBoundingClientRect().x.toFixed(1) : null; })();
        res.discountInputX = (() => { const e = document.querySelector('.opc-payment-additional.discount-code input'); return e ? +e.getBoundingClientRect().x.toFixed(1) : null; })();
        return res;
    });
    console.log(JSON.stringify(out, null, 1));
    const el = page.locator('#checkout-step-billing').first();
    await el.scrollIntoViewIfNeeded().catch(() => {});
    await el.screenshot({ path: '/tmp/slp203/round4-mobile-billing-v2.png' });
    await browser.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
