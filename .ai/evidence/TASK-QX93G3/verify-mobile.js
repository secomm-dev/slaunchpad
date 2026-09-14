/** TASK-QX93G3 — Part 3: mobile 375x812 vi. Run: NODE_PATH=... node verify-mobile.js */
const { chromium } = require('playwright');
const { BASE, log, sleep } = require('./helpers');

(async () => {
    const browser = await chromium.launch();
    // GOTCHA (BUG-QKX5BW): viewport on newContext, NOT newPage
    const ctx = await browser.newContext({ viewport: { width: 375, height: 812 } });
    const page = await ctx.newPage();

    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded' });
    await page.click('#product-addtocart-button');
    await page.waitForLoadState('domcontentloaded');
    await sleep(2500);

    await page.click('#menu-cart-icon');
    await page.waitForSelector('#cart-drawer[open]');
    await page.waitForSelector('#cart-drawer li input[name="item_qty"]', { timeout: 8000 });
    await sleep(1200); // let the drawer enter transition finish before measuring
    await page.screenshot({ path: `${__dirname}/mobile-01-drawer.png` });

    // stepper usable on mobile
    const plusBtn = page.locator('#cart-drawer li').first().locator('button[aria-label*="Tăng"]').first();
    const box = await plusBtn.boundingBox();
    log('AC-007 mobile stepper visible + tap target', !!box && box.height >= 16, JSON.stringify(box));
    await plusBtn.click();
    await sleep(1800);
    const qty = await page.locator('#cart-drawer li').first().locator('input[name="item_qty"]').inputValue();
    log('AC-007 mobile qty+ works', qty === '2', `qty=${qty}`);

    // drawer must not overflow viewport horizontally
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    log('AC-007 mobile no horizontal overflow', !overflow, '');

    // subtotal + CTA visible in viewport
    const subtotalVisible = await page.locator('#cart-drawer dd[x-html="cart.subtotal"]').isVisible();
    const checkoutVisible = await page.getByRole('link', { name: 'Thanh toán' }).isVisible();
    log('AC-007 mobile subtotal visible', subtotalVisible);
    log('AC-007 mobile checkout CTA visible', checkoutVisible);

    await ctx.close();
    await browser.close();
})().catch(e => {
    log('SCRIPT ERROR', false, e.message.slice(0, 120));
    process.exit(1);
});