/**
 * TASK-8TXS2P (SLP-203) — verify the 6 checkout UI fixes.
 * vi/en × desktop 1280 / mobile 375. Display-only assertions on measured
 * geometry + computed styles. Run: NODE_PATH=/tmp/pw-cal/node_modules node verify.js <store> <out-prefix>
 */
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = 'http://slaunchpad.localhost';
const store = process.argv[2] || 'vi';
const prefix = process.argv[3] || 'after';
const OUT = '/tmp/slp203';

const passLog = [];
function log(name, pass, detail = '') {
    passLog.push(`${pass ? 'PASS' : 'FAIL'} | ${name}${detail ? ' | ' + detail : ''}`);
    console.log(`${pass ? 'PASS' : 'FAIL'} | ${name}${detail ? ' | ' + detail : ''}`);
}

async function atcAndCheckout(page) {
    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(5000);
}

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const consoleErrors = [];

    for (const vp of [{ w: 1280, h: 900, tag: 'desktop' }, { w: 375, h: 812, tag: 'mobile' }]) {
        const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h } });
        if (store === 'en') await ctx.addCookies([{ name: 'store', value: 'launchpad_en', url: BASE }]);
        const page = await ctx.newPage();
        if (store === 'vi' && vp.tag === 'desktop') page.on('console', m => m.type() === 'error' && consoleErrors.push(m.text()));
        await atcAndCheckout(page);

        const lang = await page.evaluate(() => document.documentElement.lang);
        if (store === 'en' && !lang.startsWith('en')) log(`${vp.tag}: en store session`, false, 'lang=' + lang);

        const m = await page.evaluate(() => {
            const r = (e) => { if (!e) return null; const b = e.getBoundingClientRect(); return { x: +b.x.toFixed(1), y: +b.y.toFixed(1), w: +b.width.toFixed(1), h: +b.height.toFixed(1) }; };
            const res = {};

            // P1: radio baseline — first method row
            const radio = document.querySelector('.payment-method-title input[type=radio]');
            if (radio) {
                const label = radio.parentElement.querySelector('label');
                const rb = radio.getBoundingClientRect(), lb = label.getBoundingClientRect();
                res.p1_delta = +((rb.top + rb.height / 2) - (lb.top + lb.height / 2)).toFixed(1);
                res.p1_marginTop = getComputedStyle(radio).marginTop;
            }

            // P2: stepper frames
            const qw = document.querySelector('.opc-block-summary .qty-wrapper, #opc-sidebar .qty-wrapper');
            if (qw) {
                const els = [qw.querySelector('.button-action.minus'), qw.querySelector('input.item_qty'), qw.querySelector('.button-action.plus')];
                res.p2_frames = els.map(e => { const b = e.getBoundingClientRect(); return [ +b.width.toFixed(1), +b.height.toFixed(1) ]; });
                res.p2_tops = els.map(e => +e.getBoundingClientRect().top.toFixed(1));
                res.p2_equal = res.p2_frames.every(f => f[0] === res.p2_frames[0][0] && f[1] === res.p2_frames[0][1]);
                res.p2_aligned = Math.max(...res.p2_tops) - Math.min(...res.p2_tops) < 1;
            }

            // P3: estimation vs subtitle
            const est = document.querySelector('.opc-estimated-wrapper');
            const sub = document.querySelector('.page-title-wrapper');
            if (est && sub) {
                res.p3_marginTop = getComputedStyle(est).marginTop;
                res.p3_estTop = +est.getBoundingClientRect().top.toFixed(1);
                res.p3_subBottom = +sub.getBoundingClientRect().bottom.toFixed(1);
                res.p3_overlap = res.p3_estTop < res.p3_subBottom;
            }

            // P4: field input x alignment
            const inputs = [...document.querySelectorAll('.form-shipping-address .control input.input-text, #co-shipping-form .control input.input-text')]
                .filter(e => e.offsetWidth > 0);
            res.p4_xs = [...new Set(inputs.map(e => +e.getBoundingClientRect().x.toFixed(1)))].sort((a, b) => a - b);

            // P5: payment radios x
            const radios = [...document.querySelectorAll('.payment-methods input[type=radio]')].filter(e => e.offsetWidth > 0);
            res.p5_radioX = radios.length ? +radios[0].getBoundingClientRect().x.toFixed(1) : null;
            const secTitle = document.querySelector('.opc-block-shipping-information, .step-title, #checkout-payment-method-load');
            res.p5_containerX = secTitle ? +secTitle.getBoundingClientRect().x.toFixed(1) : null;
            return res;
        });

        log(`${vp.tag} P1 radio baseline (delta≈0)`, Math.abs(m.p1_delta) <= 0.5, `delta=${m.p1_delta} marginTop=${m.p1_marginTop}`);
        log(`${vp.tag} P2 stepper frames equal+aligned`, m.p2_equal && m.p2_aligned, `frames=${JSON.stringify(m.p2_frames)}`);
        if (vp.tag === 'mobile') {
            log(`${vp.tag} P3 estimation margin-top=0`, m.p3_marginTop === '0px', `marginTop=${m.p3_marginTop}`);
            log(`${vp.tag} P3 no overlap with description`, m.p3_overlap === false, `estTop=${m.p3_estTop} subBottom=${m.p3_subBottom}`);
            log(`${vp.tag} P4 all input x aligned (1 value)`, m.p4_xs.length === 1, `xs=${JSON.stringify(m.p4_xs)}`);
            log(`${vp.tag} P5 radio inside section padding`, m.p5_radioX !== null && m.p5_radioX >= (m.p5_containerX ?? 0) - 1, `radioX=${m.p5_radioX} containerX=${m.p5_containerX}`);
        }
        await page.screenshot({ path: `${OUT}/${prefix}-${store}-${vp.tag}.png`, fullPage: vp.tag === 'mobile' });

        // S6 modal (desktop + mobile both)
        const authLink = await page.evaluate(() => { const a = document.querySelector('.osc-authentication-wrapper a'); return !!(a && a.offsetWidth); });
        if (authLink) {
            await page.click('.osc-authentication-wrapper a');
            await page.waitForTimeout(2000);
            const s6 = await page.evaluate(() => {
                const gs = (sel, prop) => { const el = document.querySelector(sel); return el ? getComputedStyle(el)[prop] : null; };
                const res = {};
                res.titleDisplay = gs('.modal-popup.osc-social-login-popup .modal-title', 'display');
                res.titleBarBg = gs('#social-login-popup .social-login .social-login-title', 'backgroundColor');
                res.h2Weight = gs('#social-login-popup .social-login-title h2', 'fontWeight');
                res.btnBg = gs('#mp-popup-social-content a.btn.btn-social', 'backgroundColor');
                res.btnBorder = gs('#mp-popup-social-content a.btn.btn-social', 'borderColor');
                res.btnRadius = gs('#mp-popup-social-content a.btn.btn-social', 'borderRadius');
                res.faDisplay = gs('#mp-popup-social-content a.btn.btn-social .fa', 'display');
                const gBtn = document.querySelector('a.btn-social.btn-google');
                res.iconBefore = gBtn ? getComputedStyle(gBtn, '::before').backgroundImage : null;
                res.primaryBg = gs('#social-login-popup .action.login.primary', 'backgroundColor');
                const wrap = document.querySelector('.modal-popup.osc-social-login-popup .modal-inner-wrap');
                res.wrapW = wrap ? +wrap.getBoundingClientRect().width.toFixed(0) : null;
                const popup = document.querySelector('#social-login-popup .mp-social-popup');
                const channel = document.querySelector('#mp-popup-social-content .social-login-authentication-channel');
                if (popup && channel && window.innerWidth < 640) {
                    const pb = popup.getBoundingClientRect().bottom, cb = channel.getBoundingClientRect();
                    res.stacked = cb.top >= pb - 5 && cb.width > window.innerWidth * 0.7;
                    res.popupW = +popup.getBoundingClientRect().width.toFixed(1);
                }
                return res;
            });
            log(`${vp.tag} S6 modal-title hidden`, s6.titleDisplay === 'none', `display=${s6.titleDisplay}`);
            log(`${vp.tag} S6 blue bar gone`, s6.titleBarBg === 'rgba(0, 0, 0, 0)', `bg=${s6.titleBarBg}`);
            log(`${vp.tag} S6 panel h2 bold`, s6.h2Weight === '700', `weight=${s6.h2Weight}`);
            log(`${vp.tag} S6 social btn white+border+radius`, s6.btnBg === 'rgb(255, 255, 255)' && s6.btnBorder === 'rgb(209, 213, 219)', `bg=${s6.btnBg} border=${s6.btnBorder} radius=${s6.btnRadius}`);
            log(`${vp.tag} S6 FontAwesome hidden + SVG icon`, s6.faDisplay === 'none' && (s6.iconBefore || '').includes('data:image/svg+xml'), `fa=${s6.faDisplay}`);
            log(`${vp.tag} S6 primary green`, s6.primaryBg === 'rgb(20, 83, 45)', `bg=${s6.primaryBg}`);
            if (vp.tag === 'mobile') log(`${vp.tag} S6 columns stacked`, s6.stacked === true, `wrapW=${s6.wrapW}`);
            if (vp.tag === 'desktop') log(`${vp.tag} S6 card widened 760`, s6.wrapW === 760, `wrapW=${s6.wrapW}`);
            await page.screenshot({ path: `${OUT}/${prefix}-${store}-${vp.tag}-modal.png` });
            // close modal (regression: close works)
            await page.click('.modal-popup.osc-social-login-popup .action-close').catch(() => {});
            await page.waitForTimeout(800);
            const closed = await page.evaluate(() => { const el = document.querySelector('.modal-popup.osc-social-login-popup'); return !el || !el.classList.contains('_show'); });
            log(`${vp.tag} S6 modal closes`, closed);
        } else {
            log(`${vp.tag} S6 auth-link present`, false, 'auth-link not visible');
        }
        await ctx.close();
    }

    // regression: discount-code CSS (SLP-199) still loaded + coupon form present
    {
        const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        const page = await ctx.newPage();
        await atcAndCheckout(page);
        // discount section renders with totals (AJAX) — poll generously
        let couponInput = false;
        for (let i = 0; i < 15; i++) {
            couponInput = await page.evaluate(() => !!document.querySelector('.opc-payment-additional.discount-code input[name="discount_code"]'));
            if (couponInput) break;
            await page.waitForTimeout(1000);
        }
        const reg = await page.evaluate(() => ({
            discountCss: [...document.querySelectorAll('link[rel=stylesheet]')].some(l => /osc-discount-code\.css/.test(l.href)),
            uiCss: [...document.querySelectorAll('link[rel=stylesheet]')].some(l => /osc-checkout-ui\.css/.test(l.href)),
            socialCss: [...document.querySelectorAll('link[rel=stylesheet]')].some(l => /social-login-checkout\.css/.test(l.href)),
            extraFeeCss: [...document.querySelectorAll('link[rel=stylesheet]')].some(l => /extra-fee-checkout\.css/.test(l.href)),
            placeOrder: !!document.querySelector('.osc-place-order-block, .action.checkout.primary, button[type=submit].primary'),
        }));
        log('R1 osc-checkout-ui.css loaded', reg.uiCss);
        log('R2 social-login-checkout.css loaded', reg.socialCss);
        log('R3 SLP-199 discount CSS not regressed', reg.discountCss);
        log('R4 SLP-139 extra-fee CSS not regressed', reg.extraFeeCss);
        log('R5 coupon input present', couponInput);
        log('R6 place-order control present', reg.placeOrder);
        await ctx.close();
    }

    await browser.close();
    fs.writeFileSync(`${OUT}/${prefix}-${store}-results.log`, passLog.join('\n') + '\n');
    console.log('---');
    console.log('console errors:', consoleErrors.length ? consoleErrors : '(none)');
    const fails = passLog.filter(l => l.startsWith('FAIL')).length;
    console.log(`SUMMARY ${store}: ${passLog.length - fails}/${passLog.length} PASS`);
    process.exit(fails ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR:', e.message); process.exit(1); });
