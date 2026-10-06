const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const page = await browser.newPage();
    const reqs = [];
    page.on('request', r => { if (/googleapis|gstatic|\.woff2?/.test(r.url())) reqs.push(r.url()); });
    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);
    console.log('googleapis/gstatic:', reqs.filter(u => /googleapis|gstatic/.test(u)));
    console.log('woff2 Launchpad_Osc:', reqs.filter(u => /Launchpad_Osc.*woff2/.test(u)).length);
    const chk = await page.evaluate(() => ({
        interLoaded: document.fonts.check('16px "Inter"'),
        socialFF: [...document.querySelectorAll('.social-btn .btn-social')].map(e => getComputedStyle(e).fontFamily.split(',')[0]),
    }));
    console.log('interLoaded:', chk.interLoaded, '| btn-social ff:', JSON.stringify(chk.socialFF));
    await browser.close();
})().catch(e => { console.error('FAIL:', e.message); process.exit(1); });
