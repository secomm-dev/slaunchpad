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
        const inner = document.querySelector('.opc-payment-additional.discount-code .payment-option-inner');
        if (!inner) return 'no inner';
        const btn = inner.querySelector('button, .action');
        const res = { btnTag: btn.tagName, btnCls: btn.className, html: inner.outerHTML.slice(0, 600) };
        // which ancestor wraps the section
        let n = inner, chain = [];
        for (let i = 0; i < 4 && n; i++) { chain.push((n.id ? '#' + n.id : '') + '.' + [...(n.classList || [])].join('.')); n = n.parentElement; }
        res.chain = chain;
        return res;
    });
    console.log(JSON.stringify(out, null, 2));
    await browser.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
