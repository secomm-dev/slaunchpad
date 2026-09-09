/**
 * TASK-EPJVGG — SLP-139 regression probe: with rule area=1 (Payment) the
 * #mp-extra-fee-billing block must still measure its post-SLP-139 gap (9px)
 * with the extended stylesheet loaded. Mirrors probe-debug.js but measures
 * the billing form.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-billing.js <vi|en> <out.json>
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const WHICH = process.argv[2] || 'vi';
const OUT = process.argv[3] || '/tmp/billing-regress.json';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const def = WHICH === 'en'
        ? { entry: BASE + '/?___store=launchpad_en', cookie: 'launchpad_en' }
        : { entry: BASE + '/', cookie: null };
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    if (def.cookie) await ctx.addCookies([{ name: 'store', value: def.cookie, url: BASE }]);
    const page = await ctx.newPage();

    await page.goto(def.entry, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(5000);

    let visible = false;
    for (let i = 0; i < 20; i++) {
        visible = await page.evaluate(() => {
            const f = document.querySelector('#mp-extra-fee-billing');
            return !!(f && (f.offsetWidth || f.offsetHeight));
        });
        if (visible) break;
        await page.waitForTimeout(1000);
    }

    const res = await page.evaluate(() => {
        const form = document.querySelector('#mp-extra-fee-billing');
        if (!form) return { present: false };
        const dtLabel = form.querySelector('dt > div > label.label');
        const desc = form.querySelector('.mp-description');
        const input = form.querySelector('input[type=checkbox], input[type=radio]');
        return {
            present: true,
            visible: !!(form.offsetWidth || form.offsetHeight),
            gap: input && dtLabel ? +(input.getBoundingClientRect().top - dtLabel.getBoundingClientRect().bottom).toFixed(1) : null,
            descDisplay: desc ? getComputedStyle(desc).display : null,
            sheetHasSummaryRule: [...document.styleSheets]
                .filter(s => /extra-fee-checkout/.test(s.href))
                .some(s => [...s.cssRules].some(r => (r.selectorText || '').startsWith('#mp-extra-fee '))),
            titleText: (form.querySelector('dt label.label span') || {}).textContent || null,
        };
    });

    await browser.close();
    fs.writeFileSync(OUT, JSON.stringify({ store: WHICH, billing: res }, null, 2));
    console.log(JSON.stringify({ store: WHICH, billing: res }, null, 2));
})();
