const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    const page = await ctx.newPage();
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/checkout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(8000);

    const st = await page.evaluate(async () => {
        const out = { spans: [...document.querySelectorAll('.delivery-information .delivery-date .title span, .delivery-information .delivery-time .title span')].map(e => e.textContent.trim()) };
        out.ko = typeof window.ko;
        out.require = typeof window.require;
        out.jQueryTranslate = (() => { try { return window.jQuery && jQuery.mage && jQuery.mage.__ ? String(jQuery.mage.__('Delivery Date')) : 'no jQuery.mage.__'; } catch (e) { return 'ERR'; } })();
        out.translationScripts = [...document.querySelectorAll('script[src]')].map(s => s.getAttribute('src')).filter(s => /translation|js-translation/.test(s));
        out.xMagentoInitTranslate = document.body.innerHTML.includes('mage/translate');
        return out;
    });
    console.log(JSON.stringify(st, null, 1));
    await browser.close();
})();
