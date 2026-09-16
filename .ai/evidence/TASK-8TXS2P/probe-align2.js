const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    for (const vp of [{ w: 1280, h: 900, tag: '1280' }, { w: 768, h: 900, tag: '768' }, { w: 600, h: 900, tag: '600' }, { w: 375, h: 812, tag: '375' }]) {
        const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h } });
        const page = await ctx.newPage();
        await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(5000);
        const r = await page.evaluate(() => {
            const fields = [...document.querySelectorAll('#co-shipping-form .field, .form-shipping-address .field')].filter(f => f.offsetWidth > 0);
            const perCol = {}; // group by fieldX — check input x == field x and uniform text start
            const bad = [];
            const textStarts = new Set();
            for (const f of fields) {
                const label = f.querySelector('label');
                const input = f.querySelector('.control input.input-text, .control select, input.input-text, select');
                if (!input) continue;
                const ir = input.getBoundingClientRect(), fr = f.getBoundingClientRect();
                const cs = getComputedStyle(input);
                const ts = +(ir.x + parseFloat(cs.paddingLeft) + parseFloat(cs.borderLeftWidth)).toFixed(1);
                if (Math.abs(ir.x - fr.x) > 0.5) bad.push({ label: label?.textContent.trim().slice(0, 14), boxOffset: +(ir.x - fr.x).toFixed(1) });
                if (cs.cssFloat !== 'none') bad.push({ label: label?.textContent.trim().slice(0, 14), float: cs.cssFloat });
                textStarts.add(ts);
            }
            return { bad, textStarts: [...textStarts].sort((a, b) => a - b) };
        });
        console.log(vp.tag, 'boxOffset>0.5 or floated:', JSON.stringify(r.bad), '| textStarts:', JSON.stringify(r.textStarts));
        await ctx.close();
    }
    await browser.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
