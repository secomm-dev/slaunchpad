/**
 * BUG-2MK37V — baseline probe: computed layout của section discount code trên OSC checkout.
 * Run: cd .ai/evidence/BUG-2MK37V && NODE_PATH=/tmp/pw-cal/node_modules node probe-baseline.js
 */
const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
const SUFFIX = process.env.SUFFIX || 'before';

async function probe(page, label) {
    await page.waitForSelector('.opc-payment-additional.discount-code', { timeout: 30000 });
    await page.waitForTimeout(4000);
    const res = await page.evaluate(() => {
        const pick = (el, props) => {
            if (!el) return null;
            const cs = getComputedStyle(el);
            const r = el.getBoundingClientRect();
            const out = { rect: { top: +r.top.toFixed(1), bottom: +r.bottom.toFixed(1), h: +r.height.toFixed(1), w: +r.width.toFixed(1) } };
            for (const p of props) out[p] = cs[p];
            return out;
        };
        const section = document.querySelector('.opc-payment-additional.discount-code');
        const inner = section && section.querySelector('.payment-option-inner');
        const control = section && section.querySelector('.control.input-field');
        const input = section && section.querySelector('#discount-code');
        const toolbar = section && section.querySelector('.actions-toolbar');
        const btn = section && section.querySelector('.actions-toolbar .action');
        const label = section && section.querySelector('.control .label');
        const stylesheets = [...document.querySelectorAll('link[rel=stylesheet]')].map(l => (l.getAttribute('href') || '').split('?')[0]);
        const myCssIdx = stylesheets.findIndex(h => h.includes('osc-discount-code.css'));
        const vendorCssIdx = stylesheets.findIndex(h => h.includes('Mageplaza_Osc/css/style.css'));
        const chain = [];
        let node = inner ? inner.parentElement : null;
        while (node && node !== document.body && chain.length < 14) {
            const cs = getComputedStyle(node);
            chain.push({
                sel: node.tagName.toLowerCase() + (node.id ? '#' + node.id : '') + (node.className && typeof node.className === 'string' ? '.' + node.className.trim().split(/\s+/).join('.') : ''),
                w: +node.getBoundingClientRect().width.toFixed(1),
                display: cs.display, float: cs.cssFloat, position: cs.position,
                width: cs.width, maxW: cs.maxWidth, minW: cs.minWidth,
                padL: cs.paddingLeft, padR: cs.paddingRight, mL: cs.marginLeft, mR: cs.marginRight,
            });
            node = node.parentElement;
        }
        return {
            sectionFound: !!section,
            cssOrder: { myCssIdx, vendorCssIdx },
            ancestorChain: chain,
            stylesheets,
            inner: pick(inner, ['display', 'alignItems', 'justifyContent', 'flexWrap']),
            control: pick(control, ['display', 'width', 'flex', 'marginBottom']),
            label: pick(label, ['display', 'fontSize', 'height']),
            input: pick(input, ['width', 'height', 'borderRadius', 'padding', 'fontSize', 'backgroundColor']),
            toolbar: pick(toolbar, ['marginTop', 'paddingTop', 'borderTopWidth', 'borderTopColor', 'flexDirection', 'alignItems']),
            btn: pick(btn, ['backgroundColor', 'color', 'height', 'borderRadius', 'padding', 'fontSize', 'width']),
            misalign: (input && btn) ? {
                dTop: +(btn.getBoundingClientRect().top - input.getBoundingClientRect().top).toFixed(1),
                dBottom: +(btn.getBoundingClientRect().bottom - input.getBoundingClientRect().bottom).toFixed(1),
            } : null,
        };
    });
    console.log('=== ' + label + ' ===');
    console.log(JSON.stringify(res, null, 1));
    const sec = page.locator('.opc-payment-additional.discount-code');
    await sec.screenshot({ path: `discount-${label}.png` });
    return res;
}

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    // desktop vi
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await probe(page, 'vi-1280-' + SUFFIX);
    await page.screenshot({ path: `checkout-vi-1280-${SUFFIX}.png`, fullPage: false });

    // mobile vi (same session)
    const page2 = await ctx.newPage();
    await page2.setViewportSize({ width: 375, height: 812 });
    await page2.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await probe(page2, 'vi-375-' + SUFFIX);
    await browser.close();
})().catch(e => { console.error('FAIL', e.message); process.exit(1); });
