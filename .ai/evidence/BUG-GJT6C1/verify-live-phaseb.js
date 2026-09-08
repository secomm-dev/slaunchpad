const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const OUT = __dirname;
const STORES = [
    { key: 'vi', cookie: null },
    { key: 'en', cookie: 'launchpad_en' },
];
const AMBIENT = [/Content Security Policy directive/, /adobedtm/, /Error fetching data/, /Transition was skipped/];

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const results = [];

    for (const store of STORES) {
        const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
        if (store.cookie) await ctx.addCookies([{ name: 'store', value: store.cookie, url: BASE }]);
        const page = await ctx.newPage();
        const errs = [];
        page.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
        page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });

        await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(BASE + '/checkout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('.delivery-information').waitFor({ state: 'visible', timeout: 45000 });
        await page.waitForTimeout(3500);

        const st = await page.evaluate(() => {
            const txt = (sel) => { const e = document.querySelector(sel); return e ? e.textContent.trim() : null; };
            const vis = (el) => el && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
            // open the calendar via the module binding path
            const input = document.querySelector('#mp-delivery-date');
            if (input) { input.scrollIntoView({ block: 'center' }); input.focus(); }
            const dp = document.querySelector('#ui-datepicker-div');
            let cal = { visible: false };
            if (dp && vis(dp)) {
                cal = {
                    visible: true,
                    month: (dp.querySelector('.ui-datepicker-month') || {}).textContent || '',
                    year: (dp.querySelector('.ui-datepicker-year') || {}).textContent || '',
                    weekdayMin: [...dp.querySelectorAll('.ui-datepicker-calendar thead th')].map(e => e.textContent.trim()),
                    prevNext: [...dp.querySelectorAll('.ui-datepicker-prev, .ui-datepicker-next')].map(e => e.textContent.trim()),
                };
            }
            return {
                lang: document.documentElement.lang,
                // calendar locale state
                calendar: cal,
                regionalViRegistered: (window.jQuery && jQuery.datepicker && jQuery.datepicker.regional.vi) ? true : false,
                // OSC order comment block (may be disabled in store config)
                commentBlockPresent: !!document.querySelector('.checkout-comment-block'),
                commentsLabel: txt('.checkout-comment-block label[for="comments"]'),
                commentPlaceholder: (document.querySelector('#comments') || {}).placeholder || null,
                // delivery labels (regression guard)
                titleDeliveryDate: txt('.delivery-information .delivery-date .title span'),
            };
        });
        st.consoleErrorsReal = errs.filter(e => !AMBIENT.some(rx => rx.test(e))).map(e => e.slice(0, 140));
        results.push(st);
        await ctx.close();
    }

    await browser.close();
    fs.writeFileSync(`${OUT}/verify-live-phaseb-results.json`, JSON.stringify(results, null, 2));
    const vi = results[0], en = results[1];
    console.log(JSON.stringify(vi, null, 1));
    console.log(JSON.stringify(en, null, 1));
    const calVi = vi.calendar.visible && /Th[aá]ng/.test(vi.calendar.month) && vi.calendar.weekdayMin.join(' ') === 'T2 T3 T4 T5 T6 T7 CN';
    const calEn = en.calendar.visible && en.calendar.weekdayMin.join(' ').startsWith('Su Mo');
    console.log(`calendar vi: ${calVi ? 'PASS' : 'FAIL'} (month="${vi.calendar.month}" weekdays="${vi.calendar.weekdayMin.join(' ')}") | calendar en regression: ${calEn ? 'PASS' : 'FAIL'}`);
    console.log(`comments vi: label="${vi.commentsLabel}" placeholder="${vi.commentPlaceholder}" (block present=${vi.commentBlockPresent}) | en: label="${en.commentsLabel}"`);
})();
