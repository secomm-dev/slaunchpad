/** TASK-QX93G3 — Part 4: E2E light — coupon từ drawer → OSC checkout summary.
 * Full place-order blocked locally by pre-existing shipping errmsg (BUG-AMRBJR F4) — QC demo. */
const { chromium } = require('playwright');
const { BASE, OUT, log, sleep } = require('./helpers');

(async () => {
    const browser = await chromium.launch();
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await ctx.newPage();

    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded' });
    await page.click('#product-addtocart-button');
    await page.waitForLoadState('domcontentloaded');
    await sleep(2500);

    await page.click('#menu-cart-icon');
    await page.waitForSelector('#cart-drawer[open]');
    await page.waitForSelector('#cart-drawer li input[name="item_qty"]');

    // apply coupon in drawer
    await page.getByText('Áp dụng mã giảm giá', { exact: true }).click();
    await page.fill('#cart-drawer input[name="coupon_code"]', 'QCQUICK10');
    await page.getByRole('button', { name: 'Áp dụng', exact: true }).click();
    await page.waitForFunction(() =>
        document.querySelector('#cart-drawer input[name="coupon_code"]')?.value === 'QCQUICK10'
    );
    log('E2E coupon applied in drawer', true);

    // CTA → checkout (OSC)
    await page.getByRole('link', { name: 'Thanh toán' }).click();
    await page.waitForLoadState('domcontentloaded');
    log('E2E landed on checkout', page.url().includes('checkout'), page.url().slice(0, 60));

    // discount visible in OSC summary (summary loads via AJAX — wait generously)
    let summary = '';
    try {
        await page.waitForSelector('#opc-sidebar .totals, .opc-block-summary .totals', { timeout: 20000 });
        summary = await page.locator('#opc-sidebar, .opc-block-summary').first().innerText();
    } catch (e) {
        summary = 'summary not rendered: ' + e.message.slice(0, 60);
    }
    log('E2E discount in OSC summary', /Giảm giá|Chiết khấu|Discount|QCQUICK10/i.test(summary),
        (summary.match(/(Giảm giá|Chiết khấu|Discount)[^\n]*/) || [summary.slice(0, 60)])[0].slice(0, 60));
    await page.screenshot({ path: `${OUT}/e2e-01-osc-summary.png`, fullPage: false });

    await ctx.close();
    await browser.close();
})().catch(e => {
    log('SCRIPT ERROR', false, e.message.slice(0, 120));
    process.exit(1);
});