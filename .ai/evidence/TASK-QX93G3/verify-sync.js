/** TASK-QX93G3 — Part 5: coupon sync cart page <-> drawer (review round 2).
 * Cart page syncs via native round-trip/reload (deterministic — the page's
 * fetch+replace is broken by pre-existing inline-script conflicts).
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node verify-sync.js */
const { chromium } = require('playwright');
const { BASE, OUT, log, sleep } = require('./helpers');

(async () => {
    const browser = await chromium.launch();
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    const errs = [];
    page.on('console', m => m.type() === 'error' && errs.push(m.text()));
    page.on('pageerror', e => errs.push('PAGEERROR ' + e.message));

    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded' });
    await page.click('#product-addtocart-button');
    await page.waitForLoadState('domcontentloaded');
    await sleep(2500);
    await page.goto(`${BASE}/checkout/cart`, { waitUntil: 'domcontentloaded' });
    await sleep(2000);

    // ---- direction 1: drawer apply → cart page synced (reload by design) ----
    await page.click('#menu-cart-icon');
    await page.waitForSelector('#cart-drawer[open]');
    await page.waitForSelector('#cart-drawer li input[name="item_qty"]');
    await page.locator('#cart-drawer .coupon-form summary').click();
    await page.fill('#cart-drawer input[name="coupon_code"]', 'QCQUICK10');
    await page.locator('#cart-drawer .coupon-form button[type="submit"]').click();
    await page.waitForLoadState('load');
    await page.waitForFunction(() =>
        document.querySelector('#maincontent input[name="coupon_code"]')?.value === 'QCQUICK10'
    );
    log('SYNC drawer→cart: cart coupon form shows applied code', true);
    const mcText = await page.locator('#maincontent').innerText();
    log('SYNC drawer→cart: discount rule label in totals', /TASK-QX93G3/.test(mcText));
    await page.screenshot({ path: `${OUT}/sync-01-drawer-apply-cart-updated.png` });

    // ---- direction 2: cart page cancel (native round-trip) → drawer synced ----
    // vendor initCouponForm() closes the <details> from browserStorage even when applied — open it first
    await page.locator('#maincontent .coupon-form summary').click();
    await page.locator('#maincontent .coupon-form button:has-text("Hủy mã giảm giá")').click();
    await page.waitForLoadState('load');
    await page.waitForFunction(() =>
        (window.Alpine.$data(document.querySelector('#cart-drawer')).cart.coupon_code || '') === ''
    );
    log('SYNC cart→drawer: cancel propagates to drawer state', true);

    // ---- direction 3: cart page apply (native round-trip) → drawer synced ----
    await page.locator('#maincontent .coupon-form summary').click();
    await page.waitForSelector('#maincontent input[name="coupon_code"]', { state: 'visible', timeout: 10000 })
        .catch(async () => {
            // details toggle raced hydration — click summary once more
            await page.locator('#maincontent .coupon-form summary').click();
            await page.waitForSelector('#maincontent input[name="coupon_code"]', { state: 'visible', timeout: 10000 });
        });
    await page.fill('#maincontent input[name="coupon_code"]', 'QCQUICK10');
    await page.locator('#maincontent .coupon-form button:has-text("Áp dụng")').click();
    await page.waitForLoadState('load');
    await page.waitForFunction(() =>
        (window.Alpine.$data(document.querySelector('#cart-drawer')).cart.coupon_code || '') === 'QCQUICK10'
    );
    log('SYNC cart→drawer: apply propagates to drawer state', true);

    // cleanup: cancel via drawer AJAX (non-cart-page path) — reload expected here too
    await page.click('#menu-cart-icon');
    await page.waitForSelector('#cart-drawer[open]');
    await page.locator('#cart-drawer .coupon-form button[type="submit"]:has-text("Hủy mã giảm giá")').click();
    await sleep(2500);

    // popup redeclare fixed by theme override (let->var); remaining = documented ExtraFee noise
    const relevant = errs.filter(e =>
        !/mpextrafee|extrafee|Error fetching data|optionIsDisabled|initConfigurableSwatchOptions/i.test(e)
    );
    log('console clean (sync)', relevant.length === 0, relevant.slice(0, 2).join(' || '));

    await ctx.close();
    await browser.close();
})().catch(e => {
    log('SCRIPT ERROR', false, e.message.slice(0, 120));
    process.exit(1);
});
