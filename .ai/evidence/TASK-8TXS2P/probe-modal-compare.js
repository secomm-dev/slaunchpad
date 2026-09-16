const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    for (const vp of [{ w: 1280, h: 900, tag: '1280' }, { w: 375, h: 812, tag: '375' }]) {
        // --- home page (Hyvä modal) ---
        let ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h } });
        let page = await ctx.newPage();
        await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(4000);
        const homeTrigger = await page.evaluate(() => {
            const cands = ['a[onclick="onClick()"]', '.authorization-link a'];
            for (const s of cands) { const e = document.querySelector(s); if (e && e.offsetWidth) return s; }
            return null;
        });
        console.log(`[${vp.tag}] home trigger:`, homeTrigger);
        if (homeTrigger) {
            await page.click(homeTrigger).catch(e => console.log('click err', e.message.slice(0, 60)));
            await page.waitForTimeout(2500);
            await page.screenshot({ path: `/tmp/slp203/cmp-home-${vp.tag}.png` });
            const dlg = await page.evaluate(() => {
                const d = document.querySelector('dialog[open].wrap-modal-login, dialog.wrap-modal-login[open]');
                return d ? { w: +d.getBoundingClientRect().width.toFixed(0), cls: d.className } : null;
            });
            console.log(`[${vp.tag}] home dialog:`, JSON.stringify(dlg));
        }
        await ctx.close();

        // --- checkout modal ---
        ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h } });
        page = await ctx.newPage();
        await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(5000);
        await page.click('.osc-authentication-wrapper a').catch(() => {});
        await page.waitForTimeout(2500);
        await page.screenshot({ path: `/tmp/slp203/cmp-checkout-${vp.tag}.png` });
        await ctx.close();
    }
    await browser.close();
    console.log('done');
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
