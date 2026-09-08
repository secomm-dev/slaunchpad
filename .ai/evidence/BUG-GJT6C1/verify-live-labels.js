/**
 * BUG-GJT6C1 (SLP-146) — live verification on the REAL render path.
 * Finding 2026-09-07: OSC checkout page runs in Magento/luma scope; the Delivery
 * Time block is the Knockout component (jQuery UI datepicker), NOT the Hyva/Magewire
 * flatpickr block targeted by the original theme override.
 * Phase A fix = additive Mageplaza_DeliveryTime/i18n/vi_VN.csv (labels via luma dictionary).
 *
 * Checks per store:
 *   - html lang
 *   - Knockout block titles (i18n bindings) + time select placeholder ($t caption)
 *   - calendar popup locale state (expected: still English pending Phase B decision)
 * Run: NODE_PATH=<playwright dir> node verify-live-labels.js
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const OUT = __dirname;
const STORES = [
    { key: 'vi', entry: BASE + '/', expectLang: 'vi' },
    { key: 'en', entry: BASE + '/?___store=launchpad_en', expectLang: 'en' },
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
        if (store.key === 'en') {
            // ___store query param is request-scoped; the store COOKIE makes
            // the whole session (cart add + checkout) resolve to launchpad_en.
            await ctx.addCookies([{ name: 'store', value: 'launchpad_en', url: BASE }]);
        }
        const page = await ctx.newPage();
        const errs = [];
        page.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
        page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });

        await page.goto(store.entry, { waitUntil: 'domcontentloaded', timeout: 90000 });
        const lang = await page.evaluate(() => document.documentElement.lang);
        if (store.key === 'en' && !lang.startsWith('en')) throw new Error('en store session failed: html lang=' + lang);

        await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(BASE + '/checkout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('.delivery-information').waitFor({ state: 'visible', timeout: 45000 });
        await page.waitForTimeout(3000); // let Knockout bindings + dictionary settle

        const st = await page.evaluate(() => {
            const txt = (sel) => [...document.querySelectorAll(sel)].map(e => e.textContent.trim()).filter(Boolean);
            const $ = window.jQuery;
            const input = document.querySelector('#mp-delivery-date');
            let cal = null;
            if (input && $) {
                try { $(input).datepicker('show'); } catch (e) { /* noop */ }
            }
            const dp = document.querySelector('#ui-datepicker-div');
            if (dp && (dp.offsetWidth || dp.offsetHeight)) {
                cal = {
                    month: (dp.querySelector('.ui-datepicker-month') || {}).textContent || '',
                    year: (dp.querySelector('.ui-datepicker-year') || {}).textContent || '',
                    weekdayMin: [...dp.querySelectorAll('.ui-datepicker-calendar thead th')].map(e => e.textContent.trim()),
                };
                try { $(input).datepicker('hide'); } catch (e) { /* noop */ }
            }
            return {
                blockKind: (() => { const b = document.querySelector('.delivery-information'); return b && b.innerHTML.includes('mp-delivery-date') ? 'KNOCKOUT(luma-scope)' : 'other'; })(),
                titleDeliveryDate: txt('.delivery-information .delivery-date .title span')[0] || null,
                titleDeliveryTime: txt('.delivery-information .delivery-time .title span')[0] || null,
                titleHouseSecurity: txt('.delivery-information .house-security-code .title span')[0] || null,
                titleDeliveryComment: txt('.delivery-information .delivery-comment .title span')[0] || null,
                timePlaceholder: (document.querySelector('#mp-delivery-time option') || {}).textContent || null,
                calendar: cal,
            };
        });
        await page.waitForTimeout(400);

        const r = {
            store: store.key, lang, ...st,
            consoleErrorsRaw: errs.length,
            consoleErrorsAmbient: errs.filter(e => AMBIENT.some(rx => rx.test(e))).length,
        };
        results.push(r);
        await ctx.close();
    }

    await browser.close();
    fs.writeFileSync(`${OUT}/verify-live-labels-results.json`, JSON.stringify(results, null, 2));
    for (const r of results) console.log(JSON.stringify(r));
    const vi = results.find(r => r.store === 'vi'), en = results.find(r => r.store === 'en');
    const viOk = vi && vi.titleDeliveryDate === 'Ngày giao hàng' && vi.titleDeliveryTime === 'Thời gian giao hàng'
        && (vi.timePlaceholder || '').includes('Vui lòng chọn thời gian giao hàng');
    const enOk = en && en.titleDeliveryDate === 'Delivery Date' && en.titleDeliveryTime === 'Delivery Time';
    console.log(`\nPhase A labels: vi=${viOk ? 'PASS' : 'FAIL'} | en regression=${enOk ? 'PASS' : 'FAIL'} | calendar popup locale: vi=${JSON.stringify(vi && vi.calendar && vi.calendar.weekdayMin)} (Phase B pending)`);
})();
