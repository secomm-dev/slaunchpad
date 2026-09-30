/**
 * TASK-NWV2MQ (SLP-211) — regression for the OSC social-login popup restyle.
 * Usage: node regression.js <outDir> [vi|en]
 * Needs Playwright at /tmp/pw-cal + Chrome 150 (see memory: local-playwright).
 */
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const fs = require('fs');
const BASE = 'http://slaunchpad.localhost';
const OUT = process.argv[2] || __dirname;
const store = process.argv[3] || 'vi';
const results = [];
const log = (name, pass, detail = '') => { const l = `${pass ? 'PASS' : 'FAIL'} | ${name}${detail ? ' | ' + detail : ''}`; results.push(l); console.log(l); };

async function openPopup(browser, w) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 900 } });
    if (store === 'en') await ctx.addCookies([{ name: 'store', value: 'launchpad_en', url: BASE }]);
    const page = await ctx.newPage();
    const errors = [];
    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    // console errors counted on the checkout page only (the product page's
    // Hyvä ExtraFee template logs a pre-existing "Error fetching data")
    page.on('console', m => m.type() === 'error' && errors.push(m.text()));
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);
    await page.click('.osc-authentication-wrapper a');
    await page.waitForTimeout(2000);
    return { ctx, page, errors };
}

const geometry = () => {
    const vis = (e) => e && e.offsetWidth > 0;
    const wrap = document.querySelector('.modal-popup._show .modal-inner-wrap');
    const pop = document.querySelector('#social-login-popup');
    const blk = [...document.querySelectorAll('#social-login-popup .social-login.block-container')].find(vis);
    const btn = blk && [...blk.querySelectorAll('.actions-toolbar .action.primary')].find(vis);
    const link = blk && [...blk.querySelectorAll('.actions-toolbar .secondary a')].find(vis);
    const socials = [...document.querySelectorAll('#mp-popup-social-content a.btn-social')].filter(vis);
    const form = document.querySelector('#social-login-popup > .mp-social-popup');
    const channel = document.querySelector('#mp-popup-social-content');
    const r = (e) => e.getBoundingClientRect();
    const gs = (e, p) => e ? getComputedStyle(e)[p] : null;
    return {
        view: blk ? [...blk.classList].pop() : null,
        docOverflow: document.documentElement.scrollWidth > window.innerWidth,
        wrapW: Math.round(r(wrap).width), wrapInView: r(wrap).left >= 0 && r(wrap).right <= window.innerWidth,
        popOverflow: pop.scrollWidth > pop.clientWidth + 1,
        socialOverflow: socials.some(a => a.scrollWidth > a.clientWidth + 1),
        stacked: channel && form ? r(channel).top >= r(form).bottom - 2 : null,
        titleBg: gs(blk && blk.querySelector('.social-login-title'), 'backgroundColor'),
        h2Color: gs(blk && blk.querySelector('.social-login-title h2'), 'color'),
        btnBg: gs(btn, 'backgroundColor'), btnH: btn ? Math.round(r(btn).height) : null, btnR: gs(btn, 'borderRadius'),
        linkBelowBtn: btn && link ? r(link).top >= r(btn).bottom - 1 : null,
        linkCenterDy: btn && link ? Math.round(Math.abs((r(link).top + r(link).height / 2) - (r(btn).top + r(btn).height / 2))) : null,
        socialBg: gs(socials[0], 'backgroundColor'), faHidden: gs(document.querySelector('#mp-popup-social-content .fa'), 'display') === 'none',
        svgIcon: socials.length ? getComputedStyle(socials[0], '::before').backgroundImage.includes('data:image/svg+xml') : false,
    };
};

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome', args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    for (const w of [1280, 768, 745, 375]) {
        const { ctx, page, errors } = await openPopup(browser, w);
        for (const view of ['login', 'create', 'forgot']) {
            if (view !== 'login') {
                await page.locator('#social-login-popup .social-login.block-container:not(.authentication) .action.back').filter({ visible: true }).first().click({ timeout: 3000 }).catch(() => {});
                await page.waitForTimeout(600);
                await page.locator(`#social-login-popup .social-login.authentication .action.${view === 'create' ? 'create' : 'remind'}`).first().click();
                await page.waitForTimeout(1200);
            }
            const g = await page.evaluate(geometry);
            const t = `${w} ${view}`;
            log(`${t} view switched`, g.view === (view === 'login' ? 'authentication' : view), `view=${g.view}`);
            log(`${t} no horizontal overflow (page/popup/social)`, !g.docOverflow && !g.popOverflow && !g.socialOverflow && g.wrapInView, JSON.stringify({ doc: g.docOverflow, pop: g.popOverflow, soc: g.socialOverflow, wrapW: g.wrapW }));
            log(`${t} title plain ink heading`, g.titleBg === 'rgba(0, 0, 0, 0)' && g.h2Color === 'rgb(16, 24, 40)', `bg=${g.titleBg} h2=${g.h2Color}`);
            log(`${t} primary config color 42px r8`, g.btnBg === 'rgb(51, 153, 204)' && g.btnH === 42 && g.btnR === '8px', `bg=${g.btnBg} h=${g.btnH} r=${g.btnR}`);
            if (w >= 768) log(`${t} link centered on button row`, g.linkCenterDy !== null && g.linkCenterDy <= 3, `dy=${g.linkCenterDy}`);
            else log(`${t} link below full-width button`, g.linkBelowBtn === true, `below=${g.linkBelowBtn}`);
            if (w <= 640) log(`${t} columns stacked`, g.stacked === true);
            log(`${t} social btn surface + SVG, no FontAwesome`, ['rgb(252, 254, 253)', 'oklch(0.995 0.002 155)'].includes(g.socialBg) && g.svgIcon && g.faHidden, `bg=${g.socialBg}`);
            await page.screenshot({ path: `${OUT}/reg-${store}-${w}-${view}.png` });

            // validation messages: submit empty form, errors in-flow (no overlap with next field)
            const submitSel = { login: '#bnt-social-login-authentication', create: '#button-create-social', forgot: '#bnt-social-login-forgot' }[view];
            await page.locator(submitSel).click().catch(() => {});
            await page.waitForTimeout(800);
            const v = await page.evaluate(() => {
                const errs = [...document.querySelectorAll('#social-login-popup div.mage-error')].filter(e => e.offsetWidth && e.textContent.trim());
                const overlap = errs.some(e => { const f = e.closest('.field'); const n = f && f.nextElementSibling; if (!n || !n.offsetWidth) return false; return e.getBoundingClientRect().bottom > n.getBoundingClientRect().top + 1; });
                return { n: errs.length, first: errs[0] ? errs[0].textContent.trim() : '', overlap };
            });
            const langOk = store === 'vi' ? /[àáảãạăâđèéêìíòóôơùúưỳý]/i.test(v.first) : /[a-z]/i.test(v.first);
            log(`${t} validation messages shown (${store}), no overlap`, v.n > 0 && !v.overlap && langOk, `n=${v.n} "${v.first.slice(0, 40)}"`);
            if (w === 1280 || w === 375) await page.screenshot({ path: `${OUT}/reg-${store}-${w}-${view}-validation.png` });
        }
        // close button still closes the modal
        await page.click('.modal-popup.osc-social-login-popup .action-close');
        await page.waitForTimeout(800);
        const closed = await page.evaluate(() => !document.querySelector('.modal-popup.osc-social-login-popup._show'));
        log(`${w} close button closes popup`, closed);
        const cssLoaded = await page.evaluate(() => ['social-login-checkout.css', 'osc-checkout-ui.css', 'osc-discount-code.css'].map(f => [...document.querySelectorAll('link[rel=stylesheet]')].some(l => l.href.includes(f))));
        log(`${w} checkout CSS loaded (social/ui/discount)`, cssLoaded.every(Boolean), JSON.stringify(cssLoaded));
        log(`${w} no console errors`, errors.length === 0, errors.slice(0, 2).join(' || ').slice(0, 160));
        await ctx.close();
    }
    await browser.close();
    fs.writeFileSync(`${OUT}/regression-${store}-results.txt`, results.join('\n') + '\n');
    const fails = results.filter(l => l.startsWith('FAIL')).length;
    console.log(`SUMMARY ${store}: ${results.length - fails}/${results.length} PASS`);
})().catch(e => { console.error('SCRIPT ERROR', e.message); process.exit(1); });
