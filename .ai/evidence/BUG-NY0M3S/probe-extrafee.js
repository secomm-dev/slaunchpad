/**
 * BUG-NY0M3S (SLP-139) — spacing + message probe for #mp-extra-fee-billing.
 * Measures vertical gap dt label -> dd options + computed margins of the chain,
 * so the CSS fix targets the real culprit. Run BEFORE and AFTER the CSS change.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-extrafee.js <out.json>
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const OUT = process.argv[2] || '/tmp/extrafee-spacing.json';
const STORES = [
    { key: 'vi', entry: BASE + '/' },
    { key: 'en', entry: BASE + '/?___store=launchpad_en' },
];

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const results = [];

    for (const store of STORES) {
        const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
        if (store.key === 'en') {
            await ctx.addCookies([{ name: 'store', value: 'launchpad_en', url: BASE }]);
        }
        const page = await ctx.newPage();
        const errs = [];
        page.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
        page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });

        await page.goto(store.entry, { waitUntil: 'domcontentloaded', timeout: 90000 });
        const lang = await page.evaluate(() => document.documentElement.lang);

        await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(6000); // let OSC sections + Knockout settle

        const st = await page.evaluate(() => {
            const form = document.querySelector('#mp-extra-fee-billing');
            if (!form) return { present: false };
            const cs = (el, props) => {
                if (!el) return null;
                const c = getComputedStyle(el);
                const o = {};
                props.forEach(p => o[p] = c[p]);
                return o;
            };
            const rect = (el) => {
                if (!el) return null;
                const r = el.getBoundingClientRect();
                return { top: +r.top.toFixed(1), bottom: +r.bottom.toFixed(1), left: +r.left.toFixed(1), h: +r.height.toFixed(1) };
            };
            const dl = form.querySelector('dl');
            const wrap = form.querySelector('dl > div');
            const dt = form.querySelector('dt');
            const dtLabel = form.querySelector('dt > label.label');
            const desc = form.querySelector('.mp-description');
            const dd = form.querySelector('dd.item-options');
            const rule = form.querySelector('dd .rule');
            const firstInput = form.querySelector('dd input');
            const M = ['marginTop', 'marginBottom', 'paddingTop', 'paddingBottom'];
            const labelSpan = dtLabel ? dtLabel.querySelector('span') : null;
            const gapEl = (firstInput || rule) && dtLabel
                ? +((firstInput || rule).getBoundingClientRect().top - dtLabel.getBoundingClientRect().bottom).toFixed(1)
                : null;
            return {
                present: true,
                visible: !!(form.offsetWidth || form.offsetHeight),
                gapLabelToOption: gapEl,
                rects: { dtLabel: rect(dtLabel), labelSpan: rect(labelSpan), desc: rect(desc), dd: rect(dd), rule: rect(rule), input: rect(firstInput) },
                computed: {
                    form: cs(form, M.concat(['maxWidth'])),
                    dl: cs(dl, M.concat(['paddingTop', 'paddingBottom'])),
                    wrap: cs(wrap, M),
                    dt: cs(dt, M),
                    dtLabel: cs(dtLabel, M.concat(['paddingTop', 'paddingBottom', 'lineHeight', 'fontSize'])),
                    desc: cs(desc, M),
                    dd: cs(dd, M),
                    rule: cs(rule, M),
                },
                labelText: (labelSpan || {}).textContent || null,
                optionText: rule ? rule.textContent.trim() : null,
                noteLabel: (form.querySelector('label[for^="mp-extrafee-note"]') || {}).textContent || null,
                errorMessage: ((form.querySelector('.message.notice') || {}).textContent || '').trim(),
            };
        });
        await page.waitForTimeout(400);

        const r = { store: store.key, lang, ...st, consoleErrorsRaw: errs.length };
        results.push(r);
        await ctx.close();
    }

    await browser.close();
    fs.writeFileSync(OUT, JSON.stringify(results, null, 2));
    for (const r of results) console.log(JSON.stringify(r, null, 2));
})();
