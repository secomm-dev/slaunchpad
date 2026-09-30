/**
 * BUG-C97F09 (SLP-207) — trace the CURRENT header popup login flow (override
 * disabled, vendor template) with customer/startup/redirect_dashboard = 1.
 * Usage: node trace-current.js
 *
 * A: success mocked exactly as core Ajax\Login returns it when the flag is 1
 *    ({errors:false,message} — no redirectUrl).
 * B: success mocked WITH redirectUrl=/customer/account/ — shows whether the
 *    vendor script would follow a server-provided URL at all.
 * C: real server, wrong password — real endpoint + response shape.
 */
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';
const LOGIN_ENDPOINT = /\/(customer\/ajax\/login|sociallogin\/popup\/login)\/?(\?|$)/;

async function run(browser, name, path, mockBody) {
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    const overrideLoaded = await page.evaluate(() => typeof window.launchpadLoginRedirect !== 'undefined');
    let posted = '';
    let response = '';
    if (mockBody) {
        await page.route(LOGIN_ENDPOINT, route => {
            posted = route.request().url();
            route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(mockBody) });
        });
    }
    page.on('response', async r => {
        if (LOGIN_ENDPOINT.test(r.url())) {
            posted = r.url();
            response = await r.text().catch(() => '');
        }
    });
    await page.click('#customer-menu');
    await page.click('[id="customer.header.sign.in.link"]');
    await page.locator('#social_login_email').waitFor({ state: 'visible', timeout: 10000 });
    const from = page.url().split('#')[0];
    await page.fill('#social_login_email', mockBody ? 'slp207.qa@example.com' : 'slp207.nobody@example.com');
    await page.fill('#social_login_pass', 'Dummy-Passw0rd');
    const nav = page.waitForRequest(r => r.isNavigationRequest() && r.frame() === page.mainFrame(), { timeout: 8000 })
        .then(r => r.url()).catch(() => 'none');
    await page.click('#bnt-social-login-authentication');
    const target = await nav;
    await page.waitForTimeout(1500);
    console.log([
        `[${name}] page=${path}`,
        `  override loaded      : ${overrideLoaded}`,
        `  popup posts to       : ${posted.replace(BASE, '')}`,
        `  response             : ${mockBody ? JSON.stringify(mockBody) + ' (mocked)' : response}`,
        `  navigation after     : ${target === 'none' ? 'none' : target.replace(BASE, '')}${target === from ? '  (= reload same page)' : ''}`
    ].join('\n'));
    await page.context().close();
}

(async () => {
    const browser = await chromium.launch({
        executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome'
    });
    const ok = { errors: false, message: 'Login successful.' };
    await run(browser, 'A success as server returns (flag=1)', '/', ok);
    await run(browser, 'A success as server returns (flag=1)', '/atlas-pouf.html', ok);
    await run(browser, 'B success WITH redirectUrl', '/', { ...ok, redirectUrl: `${BASE}/customer/account/` });
    await run(browser, 'C real server, wrong password', '/', null);
    await browser.close();
})();
