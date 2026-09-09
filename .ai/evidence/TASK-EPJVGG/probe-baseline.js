/**
 * TASK-EPJVGG (SLP-198) — baseline measurement for #mp-extra-fee in the OSC
 * checkout summary area + the same block on the shopping cart page (Hyvä) as
 * the compact reference the ticket asks to match ("tương tự ở shopping cart").
 *
 * Measures, per store (vi/en):
 *  - OSC /onestepcheckout/: #mp-extra-fee presence, gap title-label -> first
 *    option, computed margins chain (form/dl/dt/label/.mp-description/dd/rule),
 *    ancestor chain (which OSC display area renders the block), and
 *    #mp-extra-fee-billing presence (SLP-139 context).
 *  - /checkout/cart/: same gap measurement on the Hyvä template (target value).
 *
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-baseline.js both <out.json>
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const OUT = process.argv[3] || '/tmp/extrafee-summary-baseline.json';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const storeDefs = {
        vi: { entry: BASE + '/', cookie: null },
        en: { entry: BASE + '/?___store=launchpad_en', cookie: 'launchpad_en' },
    };
    const results = [];

    for (const k of ['vi', 'en']) {
        const def = storeDefs[k];
        const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
        if (def.cookie) await ctx.addCookies([{ name: 'store', value: def.cookie, url: BASE }]);
        const page = await ctx.newPage();

        await page.goto(def.entry, { waitUntil: 'domcontentloaded', timeout: 90000 });
        const lang = await page.evaluate(() => document.documentElement.lang);
        if (k === 'en' && !lang.startsWith('en')) throw new Error('en store session failed: html lang=' + lang);

        // add rule-triggering product, then open OSC checkout
        await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(5000);

        // poll for #mp-extra-fee (cart-area block) visibility
        let visible = false;
        for (let i = 0; i < 20; i++) {
            visible = await page.evaluate(() => {
                const f = document.querySelector('#mp-extra-fee');
                return !!(f && (f.offsetWidth || f.offsetHeight));
            });
            if (visible) break;
            await page.waitForTimeout(1000);
        }

        const osc = await page.evaluate(() => {
            const vis = (e) => !!(e && (e.offsetWidth || e.offsetHeight));
            const form = document.querySelector('#mp-extra-fee');
            const billing = document.querySelector('#mp-extra-fee-billing');
            if (!form) return { present: false, billingPresent: !!billing };
            const cs = (el, props) => { if (!el) return null; const c = getComputedStyle(el); const o = {}; props.forEach(p => o[p] = c[p]); return o; };
            const rect = (el) => { if (!el) return null; const r = el.getBoundingClientRect(); return { top: +r.top.toFixed(1), bottom: +r.bottom.toFixed(1), h: +r.height.toFixed(1) }; };
            const dtLabel = form.querySelector('dt > div > label.label');
            const desc = form.querySelector('.mp-description');
            const descSpan = desc ? desc.querySelector('span') : null;
            const rule = form.querySelector('dd .rule, .mp-extra-fee-required .rule');
            const input = form.querySelector('input[type=checkbox], input[type=radio]');
            const chain = [];
            let n = form;
            for (let i = 0; i < 6 && n && n !== document.body; i++) {
                chain.push((n.id ? '#' + n.id : '') + '.' + [...(n.classList || [])].join('.'));
                n = n.parentElement;
            }
            const M = ['marginTop', 'marginBottom', 'display'];
            const target = input || rule;
            return {
                present: true, visible: vis(form),
                ancestorChain: chain,
                billingPresent: !!billing, billingVisible: vis(billing),
                gapTitleToOption: target && dtLabel ? +(target.getBoundingClientRect().top - dtLabel.getBoundingClientRect().bottom).toFixed(1) : null,
                rects: { dtLabel: rect(dtLabel), desc: rect(desc), rule: rect(rule), input: rect(input), form: rect(form) },
                computed: {
                    form: cs(form, M), dl: cs(form.querySelector('dl'), M),
                    dt: cs(form.querySelector('dt'), M), dtLabel: cs(dtLabel, M.concat(['fontSize', 'lineHeight'])),
                    desc: cs(desc, M), descSpanText: descSpan ? descSpan.textContent : null,
                    rule: cs(rule, M),
                },
                titleText: (form.querySelector('dt label.label span') || {}).textContent || null,
                optionText: rule ? rule.textContent.trim() : null,
            };
        });

        // same block on the shopping cart page (Hyvä template) — compact reference
        await page.goto(BASE + '/checkout/cart/', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(3000);
        const cart = await page.evaluate(() => {
            const vis = (e) => !!(e && (e.offsetWidth || e.offsetHeight));
            const form = document.querySelector('#mp-extra-fee');
            if (!form) return { present: false };
            const dtLabel = form.querySelector('dt > div > label.label');
            const desc = form.querySelector('.mp-description');
            const descSpan = desc ? desc.querySelector('span') : null;
            const input = form.querySelector('input[type=checkbox], input[type=radio]');
            const rule = form.querySelector('.rule');
            const target = input || rule;
            return {
                present: true, visible: vis(form),
                gapTitleToOption: target && dtLabel ? +(target.getBoundingClientRect().top - dtLabel.getBoundingClientRect().bottom).toFixed(1) : null,
                descRectH: desc ? +desc.getBoundingClientRect().height.toFixed(1) : null,
                descDisplay: desc ? getComputedStyle(desc).display : null,
                descSpanText: descSpan ? descSpan.textContent : null,
                titleText: (form.querySelector('dt label.label span') || {}).textContent || null,
            };
        });

        results.push({ store: k, lang, osc, cart });
        await ctx.close();
    }

    await browser.close();
    fs.writeFileSync(OUT, JSON.stringify(results, null, 2));
    console.log(JSON.stringify(results, null, 2));
})();
