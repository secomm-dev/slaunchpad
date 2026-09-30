/**
 * BUG-C97F09 (SLP-207) — the OSC checkout (luma scope, LL-0011) must not load
 * the Hyvä popup override: no launchpadLoginRedirect, luma popup still there.
 * Usage: node verify-osc.js
 */
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';

(async () => {
    const browser = await chromium.launch({
        executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome'
    });
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    const errors = [];
    page.on('console', m => m.type() === 'error' && errors.push(m.text()));
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForTimeout(6000);
    const state = await page.evaluate(() => ({
        url: location.pathname,
        overrideLoaded: typeof window.launchpadLoginRedirect !== 'undefined',
        lumaPopup: !!document.querySelector('#social-login-popup'),
        hyvaForm: !!document.querySelector('#social-form-login[x-data]')
    }));
    const pass = state.url.startsWith('/onestepcheckout') && !state.overrideLoaded && state.lumaPopup && !state.hyvaForm;
    console.log(`${pass ? 'PASS' : 'FAIL'} | osc luma popup untouched | ${JSON.stringify(state)} consoleErrors=${JSON.stringify(errors)}`);
    await browser.close();
    process.exit(pass ? 0 : 1);
})();
