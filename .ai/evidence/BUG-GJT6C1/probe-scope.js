const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';

const scopeStats = () => {
    const urls = [...document.querySelectorAll('script[src], link[href]')].map(e => e.src || e.href).filter(u => /\/static\//.test(u));
    const seg = (u) => { const m = u.match(/\/static\/[^/]*\/frontend\/([^/]+)\/([^/]+)\//); return m ? `${m[1]}/${m[2]}` : '(other)'; };
    const counts = {};
    urls.forEach(u => { const k = seg(u); counts[k] = (counts[k] || 0) + 1; });
    return { assetScopes: counts, bodyClass: document.body.className.slice(0, 120), hasHyva: !!document.querySelector('script[src*="hyva"]') || document.body.innerHTML.includes('alpinejs') };
};

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    const page = await ctx.newPage();
    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(2000);
    console.log('HOME:', JSON.stringify(await page.evaluate(scopeStats), null, 1));

    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/checkout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);
    console.log('CHECKOUT:', JSON.stringify(await page.evaluate(scopeStats), null, 1));
    await browser.close();
})();
