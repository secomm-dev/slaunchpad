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
        const cs = (sel) => { const e = document.querySelector(sel); if (!e) return null; const c = getComputedStyle(e); const r = e.getBoundingClientRect(); return { x: +r.x.toFixed(1), w: +r.width.toFixed(1), pad: c.padding, mar: c.margin }; };
        const firstFieldX = (sel) => { const e = document.querySelector(sel); if (!e) return null; const f = [...e.querySelectorAll('.field label, label.label')].find(l => l.offsetWidth > 0); const i = [...e.querySelectorAll('input, select, textarea')].find(x => x.offsetWidth > 0); return { labelX: f ? +f.getBoundingClientRect().x.toFixed(1) : null, inputX: i ? +i.getBoundingClientRect().x.toFixed(1) : null }; };
        const res = {};
        // shipping (target)
        res.shippingForm = cs('.form-shipping-address');
        res.shippingRow = cs('.form-shipping-address .row-mp, #co-shipping-form .row-mp');
        res.shippingField = firstFieldX('.form-shipping-address');
        // billing
        res.billingStep = cs('#checkout-step-billing');
        res.billingForm = cs('.form-billing-address, #co-billing-form, #checkout-step-billing form');
        res.billingField = firstFieldX('#checkout-step-billing');
        res.billingRow = cs('#checkout-step-billing .row-mp');
        // shipping-method-related content (delivery date, comments)
        res.shipMethodField = firstFieldX('#co-shipping-method-form, .opc-shipping-method');
        res.deliveryDate = cs('#delivery-date-form, .delivery-date-wrapper, [id*="delivery-date"]');
        // payment
        res.paymentForm = cs('#co-payment-form');
        res.paymentFieldset = cs('#checkout-payment-method-load');
        const radio = document.querySelector('.payment-methods input[type=radio]');
        res.paymentRadioX = radio ? +radio.getBoundingClientRect().x.toFixed(1) : null;
        const radioLabel = radio ? radio.parentElement.querySelector('label') : null;
        res.paymentLabelX = radioLabel ? +radioLabel.getBoundingClientRect().x.toFixed(1) : null;
        res.discount = cs('.opc-payment-additional.discount-code');
        const di = document.querySelector('.opc-payment-additional.discount-code input');
        res.discountInputX = di ? +di.getBoundingClientRect().x.toFixed(1) : null;
        // section header box + text
        const hdr = [...document.querySelectorAll('strong,span,div')].find(e => /Địa chỉ thanh toán/.test(e.textContent || '') && e.children.length === 0 && e.offsetWidth > 0);
        res.billingHeader = hdr ? { x: +hdr.getBoundingClientRect().x.toFixed(1), pad: getComputedStyle(hdr).padding } : null;
        // opc wrapper container
        res.oscContainer = cs('.checkout-container, .opc-wrapper');
        return res;
    });
    console.log(JSON.stringify(out, null, 1));
    await browser.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
