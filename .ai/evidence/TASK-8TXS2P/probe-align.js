const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    for (const vp of [{ w: 1280, h: 900 }, { w: 768, h: 900 }, { w: 600, h: 900 }, { w: 375, h: 812 }]) {
        const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h } });
        const page = await ctx.newPage();
        await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(5000);
        const rows = await page.evaluate(() => {
            const fields = [...document.querySelectorAll('#co-shipping-form .field, .form-shipping-address .field')].filter(f => f.offsetWidth > 0);
            return fields.map(f => {
                const label = f.querySelector('label');
                const input = f.querySelector('.control input.input-text, .control select, input.input-text, select');
                if (!input) return null;
                const ir = input.getBoundingClientRect(), fr = f.getBoundingClientRect();
                const cs = getComputedStyle(input);
                return {
                    label: label ? label.textContent.trim().slice(0, 16) : '(no label)',
                    fieldX: +fr.x.toFixed(1), fieldW: +fr.width.toFixed(1),
                    inputX: +ir.x.toFixed(1), inputW: +ir.width.toFixed(1),
                    padL: cs.paddingLeft, float: cs.cssFloat, disp: cs.display,
                    textStart: +(ir.x + parseFloat(cs.paddingLeft) + parseFloat(cs.borderLeftWidth)).toFixed(1),
                    tag: input.tagName.toLowerCase(),
                };
            }).filter(Boolean);
        });
        console.log(`\n===== viewport ${vp.w}px =====`);
        console.log('label'.padEnd(18), 'fldX'.padStart(6), 'inX'.padStart(7), 'inW'.padStart(7), 'padL'.padStart(5), 'float'.padStart(6), 'txtStart'.padStart(9));
        for (const r of rows) console.log(r.label.padEnd(18), String(r.fieldX).padStart(6), String(r.inputX).padStart(7), String(r.inputW).padStart(7), r.padL.padStart(5), r.float.padStart(6), String(r.textStart).padStart(9));
        await ctx.close();
    }
    await browser.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
