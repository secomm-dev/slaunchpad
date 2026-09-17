/**
 * TASK-8TXS2P (SLP-203) — DOM inventory of OSC checkout UI issues.
 * Measures: P1 payment radio baseline (desktop), P2 qty stepper 3 frames,
 * P3 subtitle clip (mobile), P4 address form field alignment (mobile),
 * P5 payment list padding (mobile), S6 social login modal markup on luma scope.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node inventory.js
 */
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = 'http://slaunchpad.localhost';
const OUT = '/tmp/slp203';

async function atcAndCheckout(page) {
    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(5000);
}

const dumpCommon = () => {
    const vis = (e) => !!(e && (e.offsetWidth || e.offsetHeight));
    const r = (e) => { if (!e) return null; const b = e.getBoundingClientRect(); return { x: +b.x.toFixed(1), y: +b.y.toFixed(1), w: +b.width.toFixed(1), h: +b.height.toFixed(1) }; };
    return { vis, r };
};

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const out = {};

    // ---------- DESKTOP 1280 ----------
    {
        const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        const page = await ctx.newPage();
        await atcAndCheckout(page);
        out.desktop = await page.evaluate(() => {
            const { vis, r } = { vis: (e) => !!(e && (e.offsetWidth || e.offsetHeight)), r: (e) => { if (!e) return null; const b = e.getBoundingClientRect(); return { x: +b.x.toFixed(1), y: +b.y.toFixed(1), w: +b.width.toFixed(1), h: +b.height.toFixed(1) }; } };
            const res = {};

            // stylesheets loaded (theme vs module)
            res.stylesheets = [...document.querySelectorAll('link[rel=stylesheet]')].map(l => l.getAttribute('href')).filter(h => /styles|launchpad|Launchpad|osc/i.test(h));
            res.bodyFont = getComputedStyle(document.body).fontFamily.slice(0, 60);

            // P2 qty stepper in Order Summary
            const qw = document.querySelector('.opc-block-summary .qty-wrapper, #opc-sidebar .qty-wrapper');
            if (qw) {
                const minus = qw.querySelector('.button-action.minus');
                const plus = qw.querySelector('.button-action.plus');
                const input = qw.querySelector('input.item_qty');
                const cs = (e) => { const c = getComputedStyle(e); return { w: c.width, h: c.height, pad: c.padding, mar: c.margin, bor: c.borderWidth, fs: c.fontSize, lh: c.lineHeight, box: c.boxSizing }; };
                res.p2_qtyStepper = { wrapper: r(qw), minus: r(minus), input: r(input), plus: r(plus), cs: { minus: cs(minus), input: cs(input), plus: cs(plus) } };
            } else { res.p2_qtyStepper = 'not found'; }

            // subtitle (config description)
            const all = [...document.querySelectorAll('div,span,p')];
            const sub = all.find(e => vis(e) && /nhập thông tin|details below/i.test(e.textContent || '') && e.children.length <= 2);
            if (sub) {
                const c = getComputedStyle(sub);
                res.p3_subtitle = { text: sub.textContent.trim().slice(0, 80), rect: r(sub), cls: sub.className, clientH: sub.clientHeight, scrollH: sub.scrollHeight, lh: c.lineHeight, fs: c.fontSize, ov: c.overflow, maxH: c.maxHeight };
            } else { res.p3_subtitle = 'not found'; }

            // auth block
            const auth = document.querySelector('.osc-authentication-wrapper');
            res.s6_authLink = auth ? { present: true, visible: vis(auth), text: auth.textContent.trim().slice(0, 60), rect: r(auth) } : { present: false };

            // payment section presence + radios
            const pay = document.querySelector('#payment, .payment-methods, #checkout-payment-method-load');
            res.paymentSection = { present: !!pay, vis: vis(pay) };
            const radios = [...document.querySelectorAll('.payment-methods input[type=radio], #checkout-payment-method-load input[type=radio]')].filter(vis);
            res.p1_radios = radios.map(r0 => {
                const item = r0.closest('.payment-method, .payment-method-title, li, label') || r0.parentElement;
                const label = [...item.querySelectorAll('label, span')].find(e => e.textContent.trim());
                const cs = getComputedStyle(r0);
                return { name: r0.name, rect: r(r0), itemRect: r(item), labelText: (label ? label.textContent.trim().slice(0, 30) : null), labelRect: label ? r(label) : null, pos: cs.position, w: cs.width, h: cs.height, mar: cs.margin };
            });
            return res;
        });
        await page.screenshot({ path: `${OUT}/desktop-osc.png`, fullPage: true });

        // S6: click auth link → dump modal markup
        const authVisible = out.desktop.s6_authLink && out.desktop.s6_authLink.visible;
        if (authVisible) {
            await page.click('.osc-authentication-wrapper a');
            await page.waitForTimeout(2500);
            out.s6_modal = await page.evaluate(() => {
                const cands = ['.mfp-wrap', '#social-login-popup', '.modal-popup', 'dialog[open]', '.block-authentication'];
                const found = {};
                for (const sel of cands) {
                    const el = document.querySelector(sel);
                    if (el && (el.offsetWidth || el.offsetHeight)) {
                        found[sel] = el.outerHTML.slice(0, 4000);
                    }
                }
                const sig = {
                    btnSocial: document.querySelectorAll('.btn-social').length,
                    faIcons: document.querySelectorAll('i.fa, .fa-brands, [class*="fa-"]').length,
                    svgInModal: document.querySelectorAll('.mfp-wrap svg, dialog svg, .modal-popup svg').length,
                };
                return { found, sig };
            });
            await page.screenshot({ path: `${OUT}/desktop-social-modal.png` });
        }
        await ctx.close();
    }

    // ---------- MOBILE 375 ----------
    {
        const ctx = await browser.newContext({ viewport: { width: 375, height: 812 } });
        const page = await ctx.newPage();
        await atcAndCheckout(page);
        out.mobile = await page.evaluate(() => {
            const { vis, r } = { vis: (e) => !!(e && (e.offsetWidth || e.offsetHeight)), r: (e) => { if (!e) return null; const b = e.getBoundingClientRect(); return { x: +b.x.toFixed(1), y: +b.y.toFixed(1), w: +b.width.toFixed(1), h: +b.height.toFixed(1) }; } };
            const res = { vw: document.documentElement.clientWidth };

            // P3 subtitle clip
            const all = [...document.querySelectorAll('div,span,p')];
            const sub = all.find(e => vis(e) && /nhập thông tin|details below/i.test(e.textContent || '') && e.children.length <= 2);
            if (sub) {
                const c = getComputedStyle(sub);
                res.p3_subtitle = { text: sub.textContent.trim().slice(0, 80), rect: r(sub), cls: sub.className, clientH: sub.clientHeight, scrollH: sub.scrollHeight, lh: c.lineHeight, fs: c.fontSize, ov: c.overflow, maxH: c.maxHeight, padBottom: c.paddingBottom };
            } else { res.p3_subtitle = 'not found'; }

            // P4 field alignment: labels + inputs x positions in address form
            const fields = [...document.querySelectorAll('.form-address-shipping .field, #co-shipping-form .field, .shipping-address .field')].filter(vis);
            res.p4_fields = fields.slice(0, 14).map(f => {
                const label = f.querySelector('label');
                const ctrl = f.querySelector('.control input, .control select, input, select');
                const cs = ctrl ? getComputedStyle(ctrl) : null;
                return { labelText: label ? label.textContent.trim().slice(0, 25) : null, labelRect: label ? r(label) : null, inputRect: ctrl ? r(ctrl) : null, ctrlMarL: cs ? cs.marginLeft : null, ctrlPadL: cs ? cs.paddingLeft : null, fieldCls: f.className.slice(0, 40) };
            });

            // P5 payment list padding
            const payList = document.querySelector('.payment-methods, #checkout-payment-method-load .payment-methods, #payment');
            if (payList && vis(payList)) {
                const radios = [...payList.querySelectorAll('input[type=radio]')].filter(vis).slice(0, 5);
                const c = getComputedStyle(payList);
                const items = radios.map(r0 => r0.getBoundingClientRect().x.toFixed(1));
                res.p5_payment = { containerRect: r(payList), containerPadL: c.paddingLeft, containerMarL: c.marginLeft, radioXs: items };
            } else { res.p5_payment = payList ? 'not visible' : 'not found'; }
            return res;
        });
        await page.screenshot({ path: `${OUT}/mobile-osc.png`, fullPage: true });
        await ctx.close();
    }

    await browser.close();
    fs.writeFileSync(`${OUT}/inventory.json`, JSON.stringify(out, null, 2));
    console.log(JSON.stringify(out, null, 2));
})().catch(e => { console.error('SCRIPT ERROR:', e.message); process.exit(1); });
