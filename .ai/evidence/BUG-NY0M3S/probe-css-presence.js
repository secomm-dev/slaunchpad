/**
 * BUG-NY0M3S — confirm the fix stylesheet is loaded on the OSC checkout page
 * and the empty .mp-description line is display:none. vi store.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-css-presence.js
 */
const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    const page = await ctx.newPage();

    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);

    const res = await page.evaluate(() => {
        const link = [...document.querySelectorAll('link[rel=stylesheet]')]
            .map(l => l.getAttribute('href'))
            .find(h => h && h.includes('extra-fee-checkout.css'));
        const form = document.querySelector('#mp-extra-fee-billing');
        const desc = form ? form.querySelector('.mp-description') : null;
        const dtLabel = form ? form.querySelector('dt > label.label') : null;
        const input = form ? form.querySelector('dd input') : null;
        return {
            stylesheetHref: link || null,
            stylesheetLoaded: (() => {
                if (!link) return false;
                for (const sheet of document.styleSheets) {
                    if ((sheet.href || '').includes('extra-fee-checkout.css')) {
                        try { return sheet.cssRules.length > 0; } catch (e) { return 'CORS'; }
                    }
                }
                return false;
            })(),
            descDisplay: desc ? getComputedStyle(desc).display : null,
            gap: (dtLabel && input) ? +(input.getBoundingClientRect().top - dtLabel.getBoundingClientRect().bottom).toFixed(1) : null,
        };
    });
    console.log(JSON.stringify(res, null, 2));
    await browser.close();
})();
