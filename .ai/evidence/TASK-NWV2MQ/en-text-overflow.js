/**
 * TASK-NWV2MQ — the en store view cannot be switched locally (pre-existing,
 * TASK-K14RVZ), so the English strings are injected into the vi popup to check
 * the layout copes with them (CSS is locale-agnostic).
 */
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome', args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    let fails = 0;
    for (const w of [1280, 745, 375]) {
        const page = await (await browser.newContext({ viewport: { width: w, height: 900 } })).newPage();
        await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(6000);
        await page.click('.osc-authentication-wrapper a');
        await page.waitForTimeout(2000);
        const r = await page.evaluate(() => {
            const set = (sel, t) => document.querySelectorAll(sel).forEach(e => { const n = [...e.childNodes].reverse().find(c => c.nodeType === 3 && c.textContent.trim()); if (n) n.textContent = t; else e.textContent = t; });
            set('#mp-popup-social-content .block-title', 'Or Sign In With');
            set('#mp-popup-social-content a.btn-facebook', 'Sign in with Facebook');
            set('#mp-popup-social-content a.btn-google', 'Sign in with Google');
            set('#social-login-popup .authentication .social-login-title h2', 'Sign In');
            set('#block-customer-login-heading', 'Registered Customers');
            set('#social-login-popup .authentication .action.remind', 'Forgot Your Password?');
            set('#social-login-popup .authentication .action.create', 'Create New Account?');
            set('#bnt-social-login-authentication span', 'Sign In');
            const vis = (e) => e.offsetWidth > 0;
            const over = [...document.querySelectorAll('#social-login-popup *')].filter(vis).filter(e => e.scrollWidth > e.clientWidth + 1 && getComputedStyle(e).overflow !== 'visible').map(e => e.className || e.tagName);
            const socialOver = [...document.querySelectorAll('#mp-popup-social-content a.btn-social')].some(a => a.scrollWidth > a.clientWidth + 1);
            const pop = document.querySelector('#social-login-popup');
            return { over, socialOver, popOver: pop.scrollWidth > pop.clientWidth + 1, docOver: document.documentElement.scrollWidth > innerWidth };
        });
        const ok = !r.socialOver && !r.popOver && !r.docOver && r.over.length === 0;
        if (!ok) fails++;
        console.log(`${ok ? 'PASS' : 'FAIL'} | ${w} en strings fit`, JSON.stringify(r));
        await page.screenshot({ path: `${__dirname}/en-text-${w}.png` });
        await page.context().close();
    }
    await browser.close();
    console.log(`SUMMARY en-text: ${3 - fails}/3 PASS`);
})();
