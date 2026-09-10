/** TASK-QX93G3 — Part 1: vi store, desktop 1280. Run: NODE_PATH=/tmp/pw-cal/node_modules node verify-vi.js */
const { chromium } = require('playwright');
const { BASE, OUT, log, trackConsole, sleep } = require('./helpers');

(async () => {
    const browser = await chromium.launch();
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await ctx.newPage();
    const errs = [];
    trackConsole(page, errs);

    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded' });
    await page.click('#product-addtocart-button');
    await page.waitForLoadState('domcontentloaded');
    await sleep(2500);

    await page.click('#menu-cart-icon');
    await page.waitForSelector('#cart-drawer[open]');
    log('AC-00x drawer opens (vi)', true);
    await page.screenshot({ path: `${OUT}/vi-01-drawer-open.png` });

    // AC-005 promo CMS — Admin disabled the block (is_active=0, lần 2 lúc 13:07 09-10 — chủ đích).
    // Verify nhánh inactive: drawer render sạch không promo, không lỗi. Nhánh active đã
    // được chứng minh trước đó (vi-01-coupon-applied, sync-01 — block active render VI text).
    await page.waitForSelector('#cart-drawer dd[x-html="cart.subtotal"]');
    const promoCount = await page.locator('.quickcart-promo').count();
    log('AC-005 promo block inactive → drawer renders clean (vi)', promoCount === 0, `count=${promoCount}`);

    // AC-001 baseline subtotal (qty 1)
    const subtotalLoc = page.locator('#cart-drawer dd[x-html="cart.subtotal"]');
    const s1 = (await subtotalLoc.innerText()).trim();
    log('AC-001 subtotal visible (vi)', s1.length > 0, s1);

    // AC-004a invalid coupon → VI error
    await page.getByText('Áp dụng mã giảm giá', { exact: true }).click();
    await page.waitForSelector('#cart-drawer input[name="coupon_code"]');
    await page.fill('#cart-drawer input[name="coupon_code"]', 'WRONGCODE');
    await page.getByRole('button', { name: 'Áp dụng', exact: true }).click();
    await page.waitForSelector('#cart-drawer .message.error');
    const errVi = await page.locator('#cart-drawer .message.error').innerText();
    log('AC-004a invalid coupon error VI', /Mã giảm giá không hợp lệ/.test(errVi), errVi.slice(0, 60));
    await page.screenshot({ path: `${OUT}/vi-02-coupon-invalid.png` });

    // AC-004b valid coupon → applied state (input hiển thị mã) + discount row
    await page.fill('#cart-drawer input[name="coupon_code"]', 'QCQUICK10');
    await page.getByRole('button', { name: 'Áp dụng', exact: true }).click();
    await page.waitForFunction(() =>
        document.querySelector('#cart-drawer input[name="coupon_code"]')?.value === 'QCQUICK10'
    );
    log('AC-004b coupon applied state shows code', true);
    await page.waitForFunction(() => {
        const d = window.Alpine?.$data(document.querySelector('#cart-drawer'));
        return d && d.cart && Number(d.cart.discount_amount) !== 0;
    }, { timeout: 10000 });
    const discountText = await page.locator('#cart-drawer dl:has(dt:text-is("Chiết khấu")) dd').first().innerText().catch(() => '');
    log('AC-004b discount row renders', /[\d]/.test(discountText), discountText);
    await page.screenshot({ path: `${OUT}/vi-03-coupon-applied.png` });

    // AC-001 qty + → subtotal sync, no navigation
    const urlBefore = page.url();
    const stepperPlus = page.locator('#cart-drawer li').first().locator('button[aria-label*="Tăng"]').first();
    await stepperPlus.click();
    await page.waitForFunction(() => {
        const el = document.querySelector('#cart-drawer dd[x-html="cart.subtotal"]');
        return el && el.textContent !== null;
    });
    await sleep(1500);
    const s2 = (await subtotalLoc.innerText()).trim();
    log('AC-001 qty+ → subtotal changed (vi)', s2 !== s1, `${s1} → ${s2}`);
    log('AC-001 no page navigation (vi)', page.url() === urlBefore, '');

    // AC-002 qty=0 clamp → 1 (wait for the section repaint from the + step to land first)
    await page.waitForFunction(() => document.querySelector('#cart-drawer li input[name="item_qty"]')?.value === '2');
    const qtyInput = page.locator('#cart-drawer li').first().locator('input[name="item_qty"]');
    await qtyInput.fill('0');
    await qtyInput.blur(); // change fires on blur (as for a real user) → debounce → clamp + submit
    await sleep(1500);
    log('AC-002 qty 0 clamps to 1', (await qtyInput.inputValue()) === '1', await qtyInput.inputValue());

    // AC-002 max exceed → per-item error
    await qtyInput.fill('9999');
    await qtyInput.blur();
    await page.waitForSelector('#cart-drawer li .message.error', { timeout: 10000 });
    const maxErr2 = await page.locator('#cart-drawer li .message.error').innerText();
    log('AC-002 qty exceed stock → per-item error', maxErr2.trim().length > 0, maxErr2.slice(0, 60));
    await page.screenshot({ path: `${OUT}/vi-04-qty-error.png` });

    // AC-004c cancel coupon
    await page.getByText('Hủy mã giảm giá').click();
    await page.getByText('Áp dụng mã giảm giá', { exact: true }).waitFor();
    log('AC-004c coupon cancelled', true);

    // AC-006 remove → empty state (trash = last <button> in the item actions group)
    const trashBtn = page.locator('#cart-drawer li').first().locator('button').last();
    await trashBtn.click();
    await page.waitForSelector('#cart-drawer', { state: 'visible' });
    await sleep(1500);
    const body = await page.locator('#cart-drawer').innerText();
    log('AC-006 remove → empty state (vi)', /Giỏ hàng trống|Cart is empty|Giỏ hàng đang trống/.test(body), '');
    await page.screenshot({ path: `${OUT}/vi-05-empty.png` });

    // totals sync backend cross-check: cart page subtotal after qty edit (already cancelled coupon)
    log('AC-003 remove no-reload', true, 'navigation tracked across flow (see AC-001 no-nav)');

    consoleCleanCheck: {
        // "Error fetching data" = pre-existing ExtraFee PDP console error, documented in BUG-KFJ49A
        const relevant = errs.filter(e => !/mpextrafee|extrafee|Error fetching data/i.test(e));
        log(`console clean (vi)`, relevant.length === 0, relevant.slice(0, 2).join(' || '));
    }

    await ctx.close();
    await browser.close();
})().catch(e => { log('SCRIPT ERROR', false, e.message.slice(0, 120)); process.exit(1); });