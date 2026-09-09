/**
 * BUG-NY0M3S — focused trigger: get the extra-fee validation message to render.
 * Verbose dumps to see what blocks place-order; vi store only.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-extrafee-trigger.js
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const OUT = process.argv[3] || '/tmp/extrafee-trigger.json';
const WHICH = process.argv[2] || 'vi';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    if (WHICH === 'en') await ctx.addCookies([{ name: 'store', value: 'launchpad_en', url: BASE }]);
    const page = await ctx.newPage();

    await page.goto(WHICH === 'en' ? BASE + '/?___store=launchpad_en' : BASE + '/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    const lang = await page.evaluate(() => document.documentElement.lang);
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(5000);

    await page.evaluate(() => {
        const vis = (e) => !!(e.offsetWidth || e.offsetHeight);
        const set = (el, v) => {
            if (!el) return false;
            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            setter.call(el, v);
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        };
        const byName = (frag) => [...document.querySelectorAll('input[name*="' + frag + '"]:not([type=hidden])')].find(vis);
        set(document.querySelector('#customer-email') || [...document.querySelectorAll('input[type=email]')].find(vis), 'qc@example.com');
        set(byName('firstname'), 'QC');
        set(byName('lastname'), 'Tester');
        set(document.querySelector('input[name*="street[0]"], input[name*="street.0"]'), '12 Nguyen Chi Thanh');
        set(byName('postcode'), '100000');
        set(byName('telephone'), '0900000001');
    });
    for (let round = 0; round < 4; round++) {
        const picked = await page.evaluate(() => {
            const out = [];
            const selects = [...document.querySelectorAll('select')]
                .filter(s => (s.offsetWidth || s.offsetHeight) && /region|city|district|ward|province/i.test(s.name + ' ' + s.id));
            for (const s of selects) {
                const opt = [...s.options].find(o => o.value && o.textContent.trim() && !/vui lòng chọn|please select/i.test(o.textContent));
                if (opt && s.value !== opt.value) {
                    const setter = Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, 'value').set;
                    setter.call(s, opt.value);
                    s.dispatchEvent(new Event('change', { bubbles: true }));
                    out.push({ sel: s.id || s.name, val: opt.textContent.trim() });
                }
            }
            return out;
        });
        if (!picked.length) break;
        await page.waitForTimeout(2500);
    }
    await page.waitForTimeout(7000);

    const before = await page.evaluate(() => {
        const vis = (e) => !!(e.offsetWidth || e.offsetHeight);
        return {
            extraFeeVisible: vis(document.querySelector('#mp-extra-fee-billing')),
            paymentRadios: [...document.querySelectorAll('input[name="payment[method]"]')].map(r => ({ value: r.value, vis: vis(r) })),
            checkoutButtons: [...document.querySelectorAll('button')].filter(vis).map(b => (b.textContent || '').trim()).filter(t => t && t.length < 30).slice(0, 12),
            shippingMethodRadios: [...document.querySelectorAll('input[name="shipping_method"]')].map(r => ({ value: r.value, vis: vis(r) })),
        };
    });

    // select a payment method (real click) if any visible
    const radio = page.locator('input[name="payment[method]"]:visible').first();
    if (await radio.count()) {
        await radio.click({ timeout: 8000 }).catch(e => console.log('radio click fail: ' + e.message.split('\n')[0]));
        await page.waitForTimeout(3500);
    }

    // click the place-order / next button (real click)
    const btn = page.locator('button.action.primary.checkout:visible, button.action.primary:visible').first();
    const btnInfo = await btn.count() ? await btn.textContent() : 'NOT FOUND';
    if (await btn.count()) {
        await btn.click({ timeout: 8000 }).catch(e => console.log('btn click fail: ' + e.message.split('\n')[0]));
        await page.waitForTimeout(4000);
    }

    const after = await page.evaluate(() => {
        const form = document.querySelector('#mp-extra-fee-billing');
        const notice = form ? form.querySelector('.message.notice') : null;
        const globals = [...document.querySelectorAll('.message.error > div, .message-error > div')].map(e => e.textContent.trim()).filter(Boolean);
        return {
            noticeMessage: notice ? notice.textContent.trim() : null,
            noticeVisible: notice ? !!(notice.offsetWidth || notice.offsetHeight) : false,
            globalErrors: globals.slice(0, 6),
        };
    });

    const out = { store: WHICH, lang, before, btnText: (btnInfo || '').trim(), after };
    fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
    console.log(JSON.stringify(out, null, 2));
    await browser.close();
})();
