const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    // H1: desktop gutter between column inputs
    {
        const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        const page = await ctx.newPage();
        await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(5000);
        const h1 = await page.evaluate(() => {
            const ten = [...document.querySelectorAll('.form-shipping-address .field')].find(f => /Tên/.test(f.querySelector('label')?.textContent || ''));
            const ho = [...document.querySelectorAll('.form-shipping-address .field')].find(f => /^Họ/.test(f.querySelector('label')?.textContent || ''));
            const ti = ten?.querySelector('.control input, input')?.getBoundingClientRect();
            const hi = ho?.querySelector('.control input, input')?.getBoundingClientRect();
            const tcs = ten ? getComputedStyle(ten) : null;
            return { tenInputRight: ti ? +ti.right.toFixed(1) : null, hoInputLeft: hi ? +hi.x.toFixed(1) : null, gutter: ti && hi ? +(hi.x - ti.right).toFixed(1) : null, tenFieldPad: tcs ? tcs.padding : null, gapH: ten && ho ? +(ho.getBoundingClientRect().x - (ten.getBoundingClientRect().x + ten.getBoundingClientRect().width)).toFixed(1) : null };
        });
        console.log('H1 desktop gutter:', JSON.stringify(h1));
        // H2: mobile labels vs headers
        await page.setViewportSize({ width: 375, height: 812 });
        await page.waitForTimeout(2000);
        const h2 = await page.evaluate(() => {
            const rows = [];
            const header = document.querySelector('.opc-progress-bar-item, .step-title, #shipping .step-title, .opc-wrapper .step-title');
            const hEl = [...document.querySelectorAll('span,div,h1,h2,h3')].find(e => /Địa chỉ giao hàng/.test(e.textContent || '') && e.children.length <= 1 && e.offsetWidth > 0);
            if (hEl) rows.push({ el: 'header', x: +hEl.getBoundingClientRect().x.toFixed(1) });
            for (const f of document.querySelectorAll('.form-shipping-address .field')) {
                if (!(f.offsetWidth > 0)) continue;
                const label = f.querySelector('label');
                const input = f.querySelector('input, select, textarea');
                const lr = label?.getBoundingClientRect();
                rows.push({ el: label?.textContent.trim().slice(0, 12) || '?', labelX: lr ? +lr.x.toFixed(1) : null, labelPadL: label ? getComputedStyle(label).paddingLeft : null, inputX: input ? +input.getBoundingClientRect().x.toFixed(1) : null });
            }
            return rows;
        });
        console.log('H2 mobile:', JSON.stringify(h2, null, 1));
        // H3: discount apply button margin
        const h3 = await page.evaluate(() => {
            const inner = document.querySelector('.opc-payment-additional.discount-code .payment-option-inner');
            if (!inner) return 'discount section not found';
            const btn = inner.querySelector('button, .action');
            const input = inner.querySelector('input');
            const out = { innerDisplay: getComputedStyle(inner).display, innerAlign: getComputedStyle(inner).alignItems, gap: getComputedStyle(inner).gap };
            if (btn) { const c = getComputedStyle(btn); const r = btn.getBoundingClientRect(); const ri = input?.getBoundingClientRect(); out.btn = { x: +r.x.toFixed(1), y: +r.y.toFixed(1), ml: c.marginLeft, mar: c.margin }; out.inputRight = ri ? +ri.right.toFixed(1) : null; out.offsetFromInput = ri ? +(r.x - ri.x).toFixed(1) : null; }
            return out;
        });
        console.log('H3 discount btn:', JSON.stringify(h3));
        // H4: extra fee block alignment (mobile)
        const h4 = await page.evaluate(() => {
            const form = document.querySelector('#mp-extra-fee');
            if (!form) return 'not found';
            const dt = form.querySelector('dt, dt > div > label');
            const label = form.querySelector('dt label, dt > div > label, dt .label');
            const rules = [...form.querySelectorAll('dd .rule, .mp-extra-fee-required .rule')].slice(0, 2);
            const checkboxes = [...form.querySelectorAll('input[type=checkbox]')].slice(0, 2);
            const cs = (e) => { const c = getComputedStyle(e); return { x: +e.getBoundingClientRect().x.toFixed(1), padL: c.paddingLeft, marL: c.marginLeft }; };
            return { dt: dt ? cs(dt) : null, label: label ? cs(label) : null, rules: rules.map(cs), checkboxes: checkboxes.map(cs) };
        });
        console.log('H4 extra fee:', JSON.stringify(h4));
        await ctx.close();
    }
    await browser.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
