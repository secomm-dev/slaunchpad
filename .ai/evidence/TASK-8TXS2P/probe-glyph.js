const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(5000);
    await page.click('.osc-authentication-wrapper a').catch(() => {});
    await page.waitForTimeout(2000);
    const out = await page.evaluate(() => {
        const btn = document.querySelector('.modal-popup.osc-social-login-popup .action-close');
        const b = getComputedStyle(btn, '::before');
        const sp = btn.querySelector('span');
        return {
            before: { content: b.content, ff: b.fontFamily.slice(0, 30), fs: b.fontSize, lh: b.lineHeight, color: b.color, mar: b.margin, disp: b.display },
            span: sp ? { text: sp.textContent.trim(), disp: getComputedStyle(sp).display } : null,
            btnDisp: getComputedStyle(btn).display,
        };
    });
    console.log(JSON.stringify(out, null, 1));
    await browser.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
