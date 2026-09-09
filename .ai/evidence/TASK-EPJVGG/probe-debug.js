/**
 * TASK-EPJVGG — debug why the en-store OSC run still measured the 45px gap
 * after the CSS fix (vi measured 5px). Inspects, inside the live page:
 * html lang, the extra-fee-checkout.css <link> href, the sheet's cssRules
 * (matched selectors), the stylesheet text served for THIS url, and the
 * computed display/margins of .mp-description inside #mp-extra-fee.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-debug.js en /tmp/e2e-debug.json
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const WHICH = process.argv[2] || 'en';
const OUT = process.argv[3] || '/tmp/e2e-debug.json';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const storeDefs = {
        vi: { entry: BASE + '/', cookie: null },
        en: { entry: BASE + '/?___store=launchpad_en', cookie: 'launchpad_en' },
    };
    const def = storeDefs[WHICH];
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    if (def.cookie) await ctx.addCookies([{ name: 'store', value: def.cookie, url: BASE }]);
    const page = await ctx.newPage();

    await page.goto(def.entry, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/onestepcheckout/?___store=' + (WHICH === 'en' ? 'launchpad_en' : 'default'), { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(5000);

    for (let i = 0; i < 20; i++) {
        const ok = await page.evaluate(() => {
            const f = document.querySelector('#mp-extra-fee');
            return !!(f && (f.offsetWidth || f.offsetHeight));
        });
        if (ok) break;
        await page.waitForTimeout(1000);
    }

    const dbg = await page.evaluate(async () => {
        const link = [...document.querySelectorAll('link[rel=stylesheet]')]
            .find(l => /extra-fee-checkout/.test(l.href));
        let sheetRules = null;
        let cssText = null;
        try {
            const sheets = [...document.styleSheets].filter(s => /extra-fee-checkout/.test(s.href));
            sheetRules = sheets.map(s => [...s.cssRules].map(r => r.selectorText || r.cssText.slice(0, 60)));
            if (link) cssText = (await (await fetch(link.href)).text());
        } catch (e) { sheetRules = ['ERR ' + e.message]; }
        const form = document.querySelector('#mp-extra-fee');
        const desc = form ? form.querySelector('.mp-description') : null;
        const dtLabel = form ? form.querySelector('dt > div > label.label') : null;
        const input = form ? form.querySelector('input[type=checkbox], input[type=radio]') : null;
        return {
            htmlLang: document.documentElement.lang,
            storeCookieVisible: null,
            linkHref: link ? link.href : null,
            sheetRules,
            cssHasMpExtraFeeRule: cssText ? (cssText.match(/#mp-extra-fee[^-]/g) || []).length : null,
            cssText: cssText ? cssText.slice(0, 800) : null,
            descDisplay: desc ? getComputedStyle(desc).display : null,
            descMargin: desc ? getComputedStyle(desc).margin : null,
            gap: input && dtLabel ? +(input.getBoundingClientRect().top - dtLabel.getBoundingClientRect().bottom).toFixed(1) : null,
        };
    });

    await browser.close();
    fs.writeFileSync(OUT, JSON.stringify(dbg, null, 2));
    console.log(JSON.stringify(dbg, null, 2));
})();
