/**
 * BUG-AMRBJR (SLP-187) — baseline/AFTER probe for OSC checkout i18n.
 * For each store (vi default / en), adds a product to the cart, opens
 * /onestepcheckout/, then reports for every candidate phrase:
 *   - dict: runtime `mage/translate` resolution (dictionary-level coverage)
 *   - visibleEn / visibleDict: whether EN source or translated string appears
 *     in the rendered page innerText
 * Also dumps full normalized innerText per store for evidence diffing.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-baseline.js <out.json>
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const OUT = process.argv[2] || '/tmp/osc-i18n-baseline.json';
const TAG = process.argv[3] || 'baseline';

const PHRASES = [
    // ticket SLP-187 — untranslated candidates
    'Already have an account? Click here to login',
    'You already have an account with us.',
    'Shipping Address',
    'Email Address',
    'Shipping Methods',
    'Payment Methods',
    'Order Summary',
    'Item in Cart',
    'Items in Cart',
    'Product Name',
    'Quantity',
    'Subtotal',
    'Action',
    'Cart Subtotal',
    'Shipping',
    'Please specify a payment method.',
    'Selected shipping method is not available. Please select another shipping method for this order.',
    'Register for newsletter',
    'Place Order',
    'Save in address book',
    // regression — expected already translated (prior tickets)
    'Delivery Date',
    'Delivery Comment',
    'House Security Code',
    'Comments',
    'Enter your comment here',
    'Enter discount code',
    'Apply Discount',
    'Please choose at least one option for each require extra fee',
    // store data — expected EN (Admin config, out of scope)
    'Table Rate',
    'Flat Rate',
    'Check / Money order',
    'Purchase Order',
];

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

        await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(6000);

        const data = await page.evaluate((PHRASES) => {
            const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
            const text = norm(document.body.innerText);
            let dict = null;
            try {
                const m = window.require('mage/translate');
                dict = (m && m.mage && typeof m.mage.__ === 'function') ? m.mage.__ : null;
            } catch (e) { /* loader not ready */ }
            const phrases = {};
            for (const p of PHRASES) {
                const res = dict ? String(dict(p)) : null;
                phrases[p] = {
                    dict: res,
                    translated: !!(res && res !== p),
                    visibleEn: text.includes(norm(p)),
                    visibleDict: !!(res && res !== p && text.includes(norm(res))),
                };
            }
            return {
                lang: document.documentElement.lang,
                title: document.title,
                phrases,
                innerText: text,
            };
        }, PHRASES);

        results.push({ store: k, ...data });
        fs.writeFileSync(
            '/var/www/projects/slaunchpad/.ai/evidence/BUG-AMRBJR/innerText-' + TAG + '-' + k + '.txt',
            data.innerText
        );
        await ctx.close();
    }

    await browser.close();
    fs.writeFileSync(OUT, JSON.stringify(results, null, 2));
    // console verdict summary
    for (const r of results) {
        console.log('== store ' + r.store + ' lang=' + r.lang + ' ==');
        for (const [p, v] of Object.entries(r.phrases)) {
            const state = v.translated ? (v.visibleDict ? 'DICT+VIS-VI' : 'DICT-only')
                : (v.visibleEn ? 'EN-visible' : 'absent');
            console.log('  [' + state.padEnd(12) + '] ' + p + (v.translated ? '  -> ' + v.dict : ''));
        }
    }
})();
