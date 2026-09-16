const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(4000);
    const st = await page.evaluate(() => {
        const dlg = document.querySelector('dialog.wrap-modal-login');
        return { hasDialog: !!dlg, typeofOnClick: typeof window.onClick, dlgOpen: dlg ? dlg.open : null };
    });
    console.log('state:', JSON.stringify(st));
    const opened = await page.evaluate(() => {
        try { if (typeof window.onClick === 'function') { window.onClick(); return 'onClick called'; } } catch (e) { return 'err ' + e.message; }
        const d = document.querySelector('dialog.wrap-modal-login');
        if (d) { d.showModal(); return 'showModal direct'; }
        return 'no path';
    });
    console.log('open:', opened);
    await page.waitForTimeout(2500);
    await page.screenshot({ path: '/tmp/slp203/cmp-home-1280.png' });
    const dlg = await page.evaluate(() => {
        const d = document.querySelector('dialog.wrap-modal-login');
        if (!d) return null;
        const r = d.getBoundingClientRect();
        return { w: +r.width.toFixed(0), h: +r.height.toFixed(0), open: d.open };
    });
    console.log('dialog:', JSON.stringify(dlg));
    await browser.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
