/**
 * BUG-GJT6C1 (SLP-146) — live browser verification of the Delivery Time calendar
 * localization on the OSC checkout (theme override delivery-information.phtml).
 *
 * Per store (vi = default, en = launchpad_en):
 *   AC-001 vi: calendar month header + weekday row in Vietnamese, week starts Monday
 *   AC-002 en: calendar unchanged English (0 regression)
 *   AC-003: pick a day -> input keeps numeric Y/m/d format, Magewire deliveryDate syncs
 *
 * Run: NODE_PATH=<npx playwright dir> node verify-calendar.js
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const OUT = __dirname;
const STORES = [
    { key: 'vi', entry: BASE + '/', expectLang: 'vi' },
    { key: 'en', entry: BASE + '/?___store=launchpad_en', expectLang: 'en' },
];

// Ambient errors present independent of this change (site-wide CSP config, ambient widgets).
const AMBIENT = [/Content Security Policy directive/, /adobedtm/, /Error fetching data/, /Transition was skipped/];

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const results = [];

    for (const store of STORES) {
        const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
        const page = await ctx.newPage();
        const errs = [];
        page.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
        page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });

        const r = { store: store.key, checks: {}, consoleErrorsRaw: 0, consoleErrorsAmbient: 0, consoleErrorsReal: [] };

        await page.goto(store.entry, { waitUntil: 'domcontentloaded', timeout: 90000 });
        r.lang = await page.evaluate(() => document.documentElement.lang);

        // add first configurable/simple product to cart
        await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});

        await page.goto(BASE + '/checkout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('.delivery-information').waitFor({ state: 'visible', timeout: 45000 });
        await page.waitForTimeout(2500); // let Magewire + flatpickr settle

        // labels (SLP-128 phrases, regression guard)
        r.checks.labelDeliveryDate = (await page.locator('.delivery-date .title').first().innerText()).trim();
        r.checks.placeholderTime = (await page.locator('.delivery-time select option').first().innerText()).trim();

        // open the calendar
        await page.locator('.delivery-date input').first().click();
        await page.locator('.flatpickr-calendar.open').waitFor({ state: 'visible', timeout: 15000 });
        await page.waitForTimeout(500);

        const cal = await page.evaluate(() => {
            const days = [...document.querySelectorAll('.flatpickr-calendar.open .flatpickr-day')];
            let lead = 0;
            for (const d of days) {
                if (d.classList.contains('prevMonthDay')) { lead++; continue; }
                break;
            }
            return {
                month: document.querySelector('.flatpickr-calendar.open .flatpickr-monthDropdown-months')?.value || '',
                year: (document.querySelector('.flatpickr-calendar.open .cur-year') || {}).value || '',
                weekdays: [...document.querySelectorAll('.flatpickr-calendar.open .flatpickr-weekday')].map(e => e.textContent.trim()),
                leadCells: lead, // leading prev-month cells == weekday index of the 1st (0=Sun/…/1=Mon)
                vnL10nRegistered: !!(window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.vn),
            };
        });
        r.calendar = cal;
        r.checks.monthLocalized = store.expectLang === 'vi'
            ? /Th[aá]ng/.test(cal.month)
            : /^[A-Z][a-z]+$/.test(cal.month) && cal.month !== 'Tháng';
        r.checks.weekStart = store.expectLang === 'vi' ? cal.leadCells === 1 : cal.leadCells === 0;

        // pick the first selectable day of the current month
        await page.locator('.flatpickr-calendar.open .flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)').first().click();
        await page.waitForTimeout(1200);

        const after = await page.evaluate(() => {
            const input = document.querySelector('.delivery-date input');
            let magewire = null;
            try { magewire = window.Alpine ? Alpine.$data(document.querySelector('.delivery-information')).deliveryDate : null; } catch (e) { magewire = 'ERR:' + e.message; }
            return { value: input ? input.value : '', magewire };
        });
        r.picked = after;
        r.checks.inputNumericYmd = /^\d{4}\/\d{2}\/\d{2}$/.test(after.value);
        r.checks.magewireSync = after.value !== '' && after.value === after.magewire;

        await page.locator('.flatpickr-calendar.open, .flatpickr-calendar').first().screenshot({ path: `${OUT}/calendar-${store.key}.png`, timeout: 60000, animations: 'disabled' }).catch(e => { r.screenshotError = e.message.split('\n')[0]; });

        r.consoleErrorsRaw = errs.length;
        r.consoleErrorsAmbient = errs.filter(e => AMBIENT.some(rx => rx.test(e))).length;
        r.consoleErrorsReal = errs.filter(e => !AMBIENT.some(rx => rx.test(e))).map(e => e.slice(0, 160));

        results.push(r);
        await ctx.close();
    }

    await browser.close();
    fs.writeFileSync(`${OUT}/verify-calendar-results.json`, JSON.stringify(results, null, 2));
    for (const r of results) {
        console.log(`\n===== store ${r.store} (lang=${r.lang}) =====`);
        console.log('calendar:', JSON.stringify(r.calendar));
        console.log('picked:', JSON.stringify(r.picked));
        console.log('checks:', JSON.stringify(r.checks));
        console.log(`console: raw=${r.consoleErrorsRaw} ambient=${r.consoleErrorsAmbient} real=${r.consoleErrorsReal.length}`, r.consoleErrorsReal);
    }
    const fail = [];
    const vi = results.find(r => r.store === 'vi'), en = results.find(r => r.store === 'en');
    if (!vi || vi.lang !== 'vi' || !vi.checks.monthLocalized || !vi.checks.weekStart || !vi.checks.inputNumericYmd || !vi.checks.magewireSync) fail.push('vi');
    if (!en || en.lang !== 'en' || !en.checks.monthLocalized || !en.checks.weekStart || !en.checks.inputNumericYmd || !en.checks.magewireSync) fail.push('en');
    console.log(fail.length ? `\nRESULT: FAIL (${fail.join(',')})` : '\nRESULT: ALL PASS');
})();
