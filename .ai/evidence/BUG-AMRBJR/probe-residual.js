/**
 * BUG-AMRBJR (SLP-187) residual — live probe for the summary item "View Details" toggle
 * (KO i18n in Mageplaza Osc container/summary/item/details.html) after the 8-key dict patch.
 * Store vi only: store en live is blocked by the store-2 locale regression (TASK-K14RVZ);
 * en identity is verified at dictionary level (en_US.csv identity + no en js-translation.json).
 *
 * Flow: PDP configurable (Meridian Modular Sofa — the product in the QC screenshot) →
 * check first radio per super_attribute group (Hyvä custom radio swatches, v2 after first
 * probe attempt showed .swatch-attribute selectors don't match this theme) → ATC →
 * /onestepcheckout/ → summary item:
 *   1. toggle text must be "Xem chi tiết" (was EN "View Details")
 *   2. click toggle → subtitle must be "Chi tiết tùy chọn" (was EN "Options Details")
 *   3. dict-level resolution + page-visibility for the 6 residual keys and regression keys
 * Dumps normalized innerText + screenshot.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-residual.js <out.json>
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const OUT = process.argv[2] || '/tmp/osc-i18n-residual.json';
const EV = '/var/www/projects/slaunchpad/.ai/evidence/BUG-AMRBJR';

// residual keys (this batch) + regression keys (prior tickets, must stay VI)
const CHECKS = {
    residual: [
        'View Details',
        'Options Details',
        'Forgot an item?',
        'No Payment method available.',
        'Sorry, no quotes are available for this order at this time',
        'You can create an account after checkout.',
    ],
    regression: [
        'Order Summary',
        'Discount Code',
        'Product Name',
        'Quantity',
        'Subtotal',
        'Action',
        'Cart Subtotal',
        'Shipping',
        'Place Order',
        'Apply Discount',
        'Items in Cart',
    ],
};

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    const page = await ctx.newPage();
    const log = (...a) => console.log(...a);

    await page.goto(BASE + '/meridian-modular-sofa.html', { waitUntil: 'networkidle', timeout: 90000 });
    await page.waitForTimeout(2500);

    // Hyvä custom swatches = radio inputs name="super_attribute[<attrId>]" — click the label
    // (force-checking the input alone does NOT fire the Alpine @change="reflectOption" handler)
    const groups = await page.evaluate(() =>
        [...new Set([...document.querySelectorAll('input[type="radio"]')]
            .map(r => r.name).filter(n => n.startsWith('super_attribute')))]
    );
    log(`super_attribute radio groups: ${JSON.stringify(groups)}`);
    if (!groups.length) throw new Error('no super_attribute radio groups found on PDP');
    for (const g of groups) {
        await page.locator(`label.swatch-option:has(input[name="${g}"])`).first().click();
        await page.waitForTimeout(600);
    }
    const picked = await page.evaluate(() =>
        [...document.querySelectorAll('input[type="radio"]:checked')].map(r => `${r.name}=${r.value}`)
    );
    log(`options picked: ${JSON.stringify(picked)}`);
    if (picked.length < groups.length) throw new Error('option selection incomplete');

    const atc = page.locator('#product-addtocart-button').first();
    if (!(await atc.isEnabled().catch(() => false))) throw new Error('ATC disabled after option selection');
    // The theme's button handler swallows requestSubmit silently (no cart/add POST observed);
    // POST the add-to-cart form directly with in-page fetch — same session cookies, FormData
    // serializes the checked super_attribute radios + form_key. (pattern per TASK-WXBQYZ)
    const atcRes = await page.evaluate(async () => {
        const f = document.querySelector('#product_addtocart_form');
        const r = await fetch(f.action, { method: 'POST', body: new FormData(f), credentials: 'same-origin' });
        return { status: r.status, url: r.url.slice(0, 120) };
    });
    log(`direct ATC POST: ${JSON.stringify(atcRes)}`);
    if (atcRes.status >= 400) throw new Error('direct ATC POST failed: ' + JSON.stringify(atcRes));
    await page.waitForTimeout(1500);

    // Cart-page item counter is unreliable here (theme cart hydrates items client-side via
    // Alpine/sections and section fetches log pre-existing "Error fetching data" noise) — so
    // no hard gate: the options toggle appearing on /onestepcheckout/ is the real proof.
    const cart = await page.evaluate(async () => {
        const r = await fetch('/checkout/sidebar/cartCount', { credentials: 'same-origin' });
        return { count: await r.text(), status: r.status };
    }).catch((e) => ({ count: 'fetch-failed: ' + e.message }));
    log(`sidebar cartCount: ${JSON.stringify(cart)}`);

    await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    // options toggle in the order summary (item with options only) — generic selectors, OSC runs luma scope
    const toggle = page.locator('.product.options [data-role="title"]').first();
    await toggle.waitFor({ state: 'visible', timeout: 60000 });
    await page.waitForTimeout(1500);

    const toggleBefore = (await toggle.innerText()).trim();
    await toggle.click();
    await page.waitForTimeout(1000);
    const subtitle = (await page.locator('.product.options .subtitle').first().innerText().catch(() => '')).trim();
    const optionLabels = await page.locator('.product.options dl dt').allInnerTexts().catch(() => []);
    const toggleAfter = (await toggle.innerText()).trim();

    await page.screenshot({ path: EV + '/osc-residual-expanded-vi.png', fullPage: false });

    const data = await page.evaluate((CHECKS) => {
        const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
        const text = norm(document.body.innerText);
        let dict = null;
        try {
            const m = window.require('mage/translate');
            dict = (m && m.mage && typeof m.mage.__ === 'function') ? m.mage.__ : null;
        } catch (e) { /* loader not ready */ }
        const out = {};
        for (const [group, phrases] of Object.entries(CHECKS)) {
            out[group] = {};
            for (const p of phrases) {
                const res = dict ? String(dict(p)) : null;
                out[group][p] = {
                    dict: res,
                    translated: !!(res && res !== p),
                    visibleEn: text.includes(norm(p)),
                    visibleDict: !!(res && res !== p && text.includes(norm(res))),
                };
            }
        }
        return { lang: document.documentElement.lang, title: document.title, checks: out, innerText: text };
    }, CHECKS);

    data.probe = { toggleText: toggleBefore, toggleTextAfterExpand: toggleAfter, expandedSubtitle: subtitle, optionLabels, cart };
    fs.writeFileSync(OUT, JSON.stringify({ store: 'vi', ...data }, null, 2));
    fs.writeFileSync(EV + '/innerText-after-residual-vi.txt', data.innerText);

    log(`\n== store vi lang=${data.lang} ==`);
    log(`toggle text        : ${JSON.stringify(toggleBefore)} (expanded: ${JSON.stringify(toggleAfter)})`);
    log(`expanded subtitle  : ${JSON.stringify(subtitle)}`);
    log(`option labels      : ${JSON.stringify(optionLabels)}`);
    for (const [group, phrases] of Object.entries(data.checks)) {
        log(`-- ${group} --`);
        for (const [p, v] of Object.entries(phrases)) {
            const state = v.translated ? (v.visibleDict ? 'DICT+VIS-VI' : 'DICT-only')
                : (v.visibleEn ? 'EN-visible' : 'absent');
            log(`  [${state.padEnd(12)}] ${p}${v.translated ? '  -> ' + v.dict : ''}`);
        }
    }
    await ctx.close();
    await browser.close();
})().catch((e) => { console.error('PROBE FAILED:', e.message); process.exit(1); });
