/**
 * BUG-NY0M3S (SLP-139) — baseline/AFTER measurement for #mp-extra-fee-billing.
 * Fills guest address incl. VN hierarchical dropdown cascade, waits for the extra
 * fee block, measures dt-label -> option gap + margins chain, then triggers the
 * required-fee validation message and captures its text.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-extrafee3.js <vi|en|both> <out.json>
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const WHICH = process.argv[2] || 'both';
const OUT = process.argv[3] || '/tmp/extrafee-measure.json';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const storeDefs = {
        vi: { entry: BASE + '/', cookie: null },
        en: { entry: BASE + '/?___store=launchpad_en', cookie: 'launchpad_en' },
    };
    const keys = WHICH === 'both' ? ['vi', 'en'] : [WHICH];
    const results = [];

    for (const k of keys) {
        const def = storeDefs[k];
        const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
        if (def.cookie) await ctx.addCookies([{ name: 'store', value: def.cookie, url: BASE }]);
        const page = await ctx.newPage();

        await page.goto(def.entry, { waitUntil: 'domcontentloaded', timeout: 90000 });
        const lang = await page.evaluate(() => document.documentElement.lang);
        if (k === 'en' && !lang.startsWith('en')) throw new Error('en store session failed: html lang=' + lang);
        await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(5000);

        // fill text inputs
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

        // cascade VN dropdowns: pick first real option, wait for reload of the next
        const pickLog = [];
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
            pickLog.push(...picked);
            if (!picked.length) break;
            await page.waitForTimeout(2500);
        }
        // poll for the extra-fee block to become visible (config AJAX + render)
        let blockVisible = false;
        for (let i = 0; i < 15; i++) {
            blockVisible = await page.evaluate(() => {
                const f = document.querySelector('#mp-extra-fee-billing');
                return !!(f && (f.offsetWidth || f.offsetHeight));
            });
            if (blockVisible) break;
            await page.waitForTimeout(1000);
        }

        const measure = await page.evaluate(() => {
            const form = document.querySelector('#mp-extra-fee-billing');
            if (!form) return { present: false };
            const vis = (e) => !!(e && (e.offsetWidth || e.offsetHeight));
            if (!vis(form)) return { present: true, visible: false, display: getComputedStyle(form).display, waitTimeouted: true };
            const cs = (el, props) => { if (!el) return null; const c = getComputedStyle(el); const o = {}; props.forEach(p => o[p] = c[p]); return o; };
            const rect = (el) => { if (!el) return null; const r = el.getBoundingClientRect(); return { top: +r.top.toFixed(1), bottom: +r.bottom.toFixed(1), left: +r.left.toFixed(1), h: +r.height.toFixed(1) }; };
            const dl = form.querySelector('dl');
            const wrap = form.querySelector('dl > div');
            const dt = form.querySelector('dt');
            const dtLabel = form.querySelector('dt > label.label');
            const desc = form.querySelector('.mp-description');
            const dd = form.querySelector('dd.item-options');
            const rule = form.querySelector('dd .rule');
            const input = form.querySelector('dd input');
            const M = ['marginTop', 'marginBottom'];
            const target = input || rule;
            return {
                present: true,
                visible: true,
                gapLabelToOption: target && dtLabel ? +(target.getBoundingClientRect().top - dtLabel.getBoundingClientRect().bottom).toFixed(1) : null,
                rects: { dt: rect(dt), dtLabel: rect(dtLabel), desc: rect(desc), dd: rect(dd), rule: rect(rule), input: rect(input) },
                computed: {
                    form: cs(form, M), dl: cs(dl, M.concat(['paddingTop', 'paddingBottom'])),
                    wrap: cs(wrap, M), dt: cs(dt, M),
                    dtLabel: cs(dtLabel, M.concat(['paddingTop', 'paddingBottom', 'fontSize', 'lineHeight', 'display'])),
                    desc: cs(desc, M), dd: cs(dd, M), rule: cs(rule, M), input: cs(input, M),
                },
                labelText: (form.querySelector('dt label.label span') || {}).textContent || null,
                optionText: rule ? rule.textContent.trim() : null,
                noteLabel: (form.querySelector('label[for^="mp-extrafee-note"]') || {}).textContent || null,
            };
        });

        // trigger required-fee validation: pick a payment method, click place order
        let msg = null;
        try {
            const pay = page.locator('#payment-method-list input[type=radio], .payment-methods input[type=radio], input[name="payment[method]"]').first();
            if (await pay.count()) { await pay.click({ timeout: 5000 }).catch(() => {}); await page.waitForTimeout(2500); }
            const btn = page.locator('button.action.primary.checkout, .action.primary.checkout').first();
            if (await btn.count()) {
                await btn.click({ timeout: 5000 }).catch(() => {});
                await page.waitForTimeout(3500);
            }
        } catch (e) { /* keep going */ }
        msg = await page.evaluate(() => {
            const m = document.querySelector('#mp-extra-fee-billing .message.notice');
            return m ? m.textContent.trim() : null;
        });

        results.push({ store: k, lang, pickLog, block: measure, validationMessage: msg });
        await ctx.close();
    }

    await browser.close();
    fs.writeFileSync(OUT, JSON.stringify(results, null, 2));
    for (const r of results) console.log(JSON.stringify(r, null, 2));
})();
