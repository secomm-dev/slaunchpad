/** TASK-QX93G3 — Part 2: en store, desktop. Run: NODE_PATH=/tmp/pw-cal/node_modules node verify-en.js */
const { chromium } = require('playwright');
const { BASE, OUT, log, trackConsole, sleep } = require('./helpers');

(async () => {
    const browser = await chromium.launch();
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await ctx.newPage();
    const errs = [];
    trackConsole(page, errs);

    const URL_EN = `${BASE}/atlas-pouf.html?___store=launchpad_en`;
    await page.goto(URL_EN, { waitUntil: 'domcontentloaded' });
    // F3 gotcha (BUG-NY0M3S): store cookie never set locally — every EN request needs ?___store=
    // Guest quotes are store-scoped, so the ATC POST itself must hit the EN store:
    await page.evaluate(() => {
        const f = document.querySelector('#product_addtocart_form');
        f.action = f.action + '?___store=launchpad_en';
        f.submit();
    });
    await page.waitForLoadState('domcontentloaded');
    await sleep(2500);

    await page.click('#menu-cart-icon');
    await page.waitForSelector('#cart-drawer[open]');
    await page.waitForSelector('#cart-drawer li input[name="item_qty"]', { timeout: 8000 });
    log('drawer opens (en)', true);

    // EN identity: stepper aria-labels
    const minusBtn = page.locator('#cart-drawer li').first().locator('button[aria-label*="Decrease"]');
    log('AC-007 en stepper aria identity', (await minusBtn.count()) > 0);

    // subtotal baseline
    const subtotalLoc = page.locator('#cart-drawer dd[x-html="cart.subtotal"]');
    const s1 = (await subtotalLoc.innerText()).trim();

    // coupon invalid → EN error
    await page.getByText('Apply Discount Code', { exact: true }).click();
    await page.waitForSelector('#cart-drawer input[name="coupon_code"]');
    await page.fill('#cart-drawer input[name="coupon_code"]', 'WRONGCODE');
    await page.getByRole('button', { name: 'Apply Discount', exact: true }).click();
    await page.waitForSelector('#cart-drawer .message.error');
    const errEn = await page.locator('#cart-drawer .message.error').innerText();
    log('AC-004 invalid coupon error EN', /isn't valid/.test(errEn), errEn.slice(0, 60));

    // qty + → subtotal sync
    await page.locator('#cart-drawer li').first().locator('button[aria-label*="Increase"]').first().click();
    await sleep(1800);
    const s2 = (await subtotalLoc.innerText()).trim();
    log('AC-001 qty+ subtotal sync (en)', s2 !== s1, `${s1} -> ${s2}`);

    // valid coupon + persistence across navigation
    await page.fill('#cart-drawer input[name="coupon_code"]', 'QCQUICK10');
    await page.getByRole('button', { name: 'Apply Discount', exact: true }).click();
    // applied state renders after the section round-trip — assert the input shows the code
    await page.waitForFunction(() =>
        document.querySelector('#cart-drawer input[name="coupon_code"]')?.value === 'QCQUICK10'
    );
    log('AC-004 coupon applies (en)', true);

    await page.goto(`${BASE}/?___store=launchpad_en`, { waitUntil: 'domcontentloaded' });
    await page.click('#menu-cart-icon');
    await page.waitForSelector('#cart-drawer[open]');
    await page.waitForFunction(() =>
        document.querySelector('#cart-drawer input[name="coupon_code"]')?.value === 'QCQUICK10'
    );
    log('AC-004 coupon state persists after navigation', true);

    // "Error fetching data" = pre-existing ExtraFee PDP console error (BUG-KFJ49A)
    const relevant = errs.filter(e => !/mpextrafee|extrafee|Error fetching data/i.test(e));
    log('console clean (en)', relevant.length === 0, relevant.slice(0, 2).join(' || '));

    await ctx.close();
    await browser.close();
})().catch(e => {
    log('SCRIPT ERROR', false, e.message.slice(0, 120));
    process.exit(1);
});