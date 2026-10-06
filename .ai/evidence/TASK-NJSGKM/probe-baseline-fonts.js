/**
 * TASK-NJSGKM (SLP-324) — baseline font audit on /onestepcheckout/ (Magento/luma scope).
 * Measures computed font-family/weight on representative text elements, Inter availability,
 * external font requests, and the page body class (CSS scoping anchor).
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-baseline-fonts.js
 */
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = 'http://slaunchpad.localhost';
const OUT = '/var/www/projects/slaunchpad/.ai/evidence/TASK-NJSGKM';

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const page = await browser.newPage();
    const fontReqs = [];
    page.on('request', r => { if (/fonts\.googleapis|fonts\.gstatic|\.woff2?(\?|$)/.test(r.url())) fontReqs.push(r.url()); });

    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(5000);

    const data = await page.evaluate(() => {
        const grab = (el) => {
            if (!el) return null;
            const cs = getComputedStyle(el);
            return { ff: cs.fontFamily, fw: cs.fontWeight, fs: cs.fontSize };
        };
        const q = (sel) => grab(document.querySelector(sel));
        const lang = document.documentElement.lang;
        const bodyClass = document.body.className;

        const targets = {
            body: grab(document.body),
            page_title: q('.page-title, .page-title-wrapper h1, h1.page-title'),
            osc_step_title: q('.opc-wrapper .step-title, .checkout-container .step-title'),
            summary_title: q('#opc-sidebar .opc-block-summary .title, .opc-block-summary > span.title'),
            label: q('.form-shipping-address .label, label.label'),
            input_text: q('.form-shipping-address input.input-text, #customer-email-fieldset input.input-text'),
            select: q('.form-shipping-address select, select.select'),
            button_primary: q('.checkout-container .action.primary, .action.primary.checkout, #-place-order-trigger, button.action.primary'),
            button_any: q('.checkout-container button.action, .action.action-show'),
            price: q('.opc-block-summary .price, .grand.totals .price'),
            link: q('.opc-wrapper a, .checkout-container a'),
        };

        const weights = {};
        Object.entries(targets).forEach(([k, v]) => { if (v) weights[k] = v.fw; });

        const interLoaded = document.fonts.check('16px "Inter"');
        const fontFaces = [];
        document.fonts.forEach(f => fontFaces.push(`${f.family} ${f.weight} ${f.style} [${f.status}]`));

        const sheets = [...document.querySelectorAll('link[rel=stylesheet]')].map(l => l.href);
        return { lang, bodyClass, targets, weights, interLoaded, fontFaces, sheets };
    });

    const report = {
        url: `${BASE}/onestepcheckout/`,
        timestamp: new Date().toISOString(),
        lang: data.lang,
        bodyClass: data.bodyClass,
        interFontLoaded: data.interLoaded,
        fontRequests: fontReqs,
        externalGoogleFonts: fontReqs.filter(u => /googleapis|gstatic/.test(u)),
        computed: data.targets,
        fontWeights: data.weights,
        documentFonts: data.fontFaces.slice(0, 40),
        stylesheets: data.sheets,
    };
    fs.writeFileSync(`${OUT}/baseline-fonts.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report, null, 2));
    await browser.close();
})().catch(e => { console.error('PROBE FAIL:', e.message); process.exit(1); });
