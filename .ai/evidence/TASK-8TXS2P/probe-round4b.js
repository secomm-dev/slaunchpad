const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const ctx = await browser.newContext({ viewport: { width: 375, height: 812 } });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(5000);
    const out = await page.evaluate(() => {
        const fx = (sel) => { const e = document.querySelector(sel); return e ? +e.getBoundingClientRect().x.toFixed(1) : null; };
        return {
            shippingLabel: 30,
            billingLabel: fx('#checkout-step-billing .field label.label'),
            billingInput: fx('#checkout-step-billing input.input-text'),
            billingCreateToggle: fx('#checkout-step-billing .create-account-block'),
            paymentRadio: fx('.payment-methods input[type=radio]'),
            discountInput: fx('.opc-payment-additional.discount-code input.input-text'),
            discountApplyBtn: fx('.opc-payment-additional.discount-code .actions-toolbar .action'),
        };
    });
    console.log(JSON.stringify(out));
    // desktop sanity — must be unchanged
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.waitForTimeout(1500);
    const d = await page.evaluate(() => ({
        billingLabelX: (() => { const e = document.querySelector('#checkout-step-billing .field label.label'); return e ? +e.getBoundingClientRect().x.toFixed(1) : null; })(),
        discountInputX: (() => { const e = document.querySelector('.opc-payment-additional.discount-code input'); return e ? +e.getBoundingClientRect().x.toFixed(1) : null; })(),
        radioX: (() => { const e = document.querySelector('.payment-methods input[type=radio]'); return e ? +e.getBoundingClientRect().x.toFixed(1) : null; })(),
    }));
    console.log('DESKTOP', JSON.stringify(d));
    // screenshots
    await page.setViewportSize({ width: 375, height: 812 });
    await page.waitForTimeout(1500);
    for (const [sel, name] of [['#checkout-step-billing', 'round4-mobile-billing.png'], ['.opc-payment-additional.discount-code', 'round4-mobile-discount.png'], ['table#checkout-review-table, .minicart-items-wrapper', 'round4-mobile-payment.png']]) {
        const el = page.locator(sel).first();
        await el.scrollIntoViewIfNeeded().catch(() => {});
        await el.screenshot({ path: `/tmp/slp203/${name}` }).catch(() => {});
    }
    await browser.close();
    console.log('shots done');
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
