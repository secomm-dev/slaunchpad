const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const page = await browser.newPage();
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('Network.enable');
    cdp.on('Network.requestWillBeSent', e => {
        if (/googleapis|gstatic/.test(e.request.url)) {
            const init = e.initiator || {};
            let stackLine = '';
            if (init.stack?.callFrames?.length) {
                stackLine = init.stack.callFrames.slice(0, 3).map(f => `${f.url || '(inline)'}:${f.lineNumber}`).join(' <- ');
            }
            console.log(`URL: ${e.request.url.slice(0, 90)}`);
            console.log(`  type=${init.type}${init.url ? ' url=' + init.url.slice(0, 110) : ''}${stackLine ? '\n  stack: ' + stackLine : ''}`);
        }
    });
    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);
    await browser.close();
})().catch(e => { console.error('FAIL:', e.message); process.exit(1); });
