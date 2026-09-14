/**
 * BUG-2MK37V — behavior regression: coupon apply/cancel flow + en store + console errors.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules SUFFIX=beh node probe-behavior.js
 */
const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
const SUFFIX = process.env.SUFFIX || 'beh';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    const consoleMsgs = [];
    page.on('console', m => { if (['error', 'warning'].includes(m.type())) consoleMsgs.push(m.type() + ': ' + m.text().slice(0, 160)); });
    page.on('pageerror', e => consoleMsgs.push('pageerror: ' + String(e).slice(0, 160)));

    // cart
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForSelector('.opc-payment-additional.discount-code', { timeout: 30000 });
    await page.waitForTimeout(4000);

    const input = page.locator('#discount-code');
    const applyBtn = () => page.locator('.discount-code .action-apply, .discount-code .action-cancel').first();

    // 1) valid coupon (FREESHIP)
    await input.fill('FREESHIP');
    await applyBtn().click();
    await page.waitForTimeout(4000);
    const afterApply = await page.evaluate(() => {
        const btn = document.querySelector('.discount-code .actions-toolbar .action');
        const msg = document.querySelector('.discount-code .message');
        return {
            btnClass: btn ? btn.className : null,
            btnText: btn ? btn.textContent.trim() : null,
            msgText: msg ? msg.textContent.trim() : null,
            inputDisabled: document.querySelector('#discount-code')?.classList.contains('disabled'),
        };
    });
    console.log('afterApply:', JSON.stringify(afterApply));
    await page.locator('.opc-payment-additional.discount-code').screenshot({ path: `discount-vi-applied-${SUFFIX}.png` });

    // 2) cancel coupon
    if ((afterApply.btnClass || '').includes('cancel')) {
        await applyBtn().click();
        await page.waitForTimeout(4000);
        const afterCancel = await page.evaluate(() => {
            const btn = document.querySelector('.discount-code .actions-toolbar .action');
            const msg = document.querySelector('.discount-code .message');
            return { btnClass: btn ? btn.className : null, msgText: msg ? msg.textContent.trim() : null };
        });
        console.log('afterCancel:', JSON.stringify(afterCancel));
    }

    // 3) bogus coupon -> error message (translation check BUG-5NR0PD batch 2)
    await input.fill('NOPE-BUG2MK37V');
    await applyBtn().click();
    await page.waitForTimeout(4000);
    const afterBogus = await page.evaluate(() => {
        const msg = document.querySelector('.discount-code .message');
        return { msgText: msg ? msg.textContent.trim() : null };
    });
    console.log('afterBogus:', JSON.stringify(afterBogus));

    // 4) layout still aligned after flows
    const align = await page.evaluate(() => {
        const i = document.querySelector('#discount-code').getBoundingClientRect();
        const b = document.querySelector('.discount-code .actions-toolbar .action').getBoundingClientRect();
        return { dTop: +(b.top - i.top).toFixed(1), dBottom: +(b.bottom - i.bottom).toFixed(1) };
    });
    console.log('alignAfterFlows:', JSON.stringify(align));

    // 5) en store layout + labels
    const page2 = await ctx.newPage();
    await page2.goto(BASE + '/onestepcheckout/?___store=launchpad_en', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page2.waitForSelector('.opc-payment-additional.discount-code', { timeout: 30000 });
    await page2.waitForTimeout(4000);
    const en = await page2.evaluate(() => {
        const i = document.querySelector('#discount-code');
        const b = document.querySelector('.discount-code .actions-toolbar .action');
        return {
            placeholder: i?.placeholder,
            btnText: b?.textContent.trim(),
            dTop: b && i ? +(b.getBoundingClientRect().top - i.getBoundingClientRect().top).toFixed(1) : null,
            dBottom: b && i ? +(b.getBoundingClientRect().bottom - i.getBoundingClientRect().bottom).toFixed(1) : null,
        };
    });
    console.log('enStore:', JSON.stringify(en));
    await page2.locator('.opc-payment-additional.discount-code').screenshot({ path: `discount-en-1280-${SUFFIX}.png` });

    // 6) en mobile
    const page3 = await ctx.newPage();
    await page3.setViewportSize({ width: 375, height: 812 });
    await page3.goto(BASE + '/onestepcheckout/?___store=launchpad_en', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page3.waitForSelector('.opc-payment-additional.discount-code', { timeout: 30000 });
    await page3.waitForTimeout(4000);
    const enM = await page3.evaluate(() => {
        const i = document.querySelector('#discount-code');
        const inner = document.querySelector('.discount-code .payment-option-inner');
        return {
            inputW: +i.getBoundingClientRect().width.toFixed(1),
            innerW: +inner.getBoundingClientRect().width.toFixed(1),
        };
    });
    console.log('enMobile:', JSON.stringify(enM));
    await page3.locator('.opc-payment-additional.discount-code').screenshot({ path: `discount-en-375-${SUFFIX}.png` });

    console.log('consoleIssues(' + consoleMsgs.length + '):');
    for (const m of consoleMsgs.slice(0, 12)) console.log('  ' + m);
    await browser.close();
})().catch(e => { console.error('FAIL', e.message); process.exit(1); });
