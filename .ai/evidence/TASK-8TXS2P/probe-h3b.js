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
        const s = document.querySelector('.opc-payment-additional.discount-code');
        const form = s.querySelector('#discount-form');
        const inner = s.querySelector('.payment-option-inner');
        const input = s.querySelector('input');
        const info = (e) => { const c = getComputedStyle(e); const r = e.getBoundingClientRect(); return { x: +r.x.toFixed(1), pad: c.padding, mar: c.margin }; };
        return { section: info(s), form: info(form), inner: info(inner), input: info(input) };
    });
    console.log(JSON.stringify(out, null, 1));
    await browser.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
