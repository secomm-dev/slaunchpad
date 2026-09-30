/**
 * BUG-C97F09 (SLP-207) — Hyvä popup login: post-success redirect vs
 * customer/startup/redirect_dashboard.
 *
 * Usage: node verify.js <yes|no> [en-expect: yes|no]
 *   arg1 = expected flag for the vi store (default), arg2 = for launchpad_en
 *   (defaults to arg1; differs only in the store-scope override run).
 * Needs Playwright at /tmp/pw-cal + Chrome 150 (see memory: local-playwright).
 *
 * Success path: the login endpoint is mocked ({errors:false}) so no real
 * customer account is needed or touched — the fix is client-side only. The
 * first main-frame navigation after submit is the redirect under test (the
 * mocked login creates no session, so the dashboard itself would bounce to
 * the login page — only the navigation target matters).
 * Failure path: real server, non-existent dummy account → error in popup.
 */
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';
const DASHBOARD = `${BASE}/customer/account/`;
const LOGIN_ENDPOINT = /\/(customer\/ajax\/login|sociallogin\/popup\/login)\/?(\?|$)/;
const expectVi = process.argv[2];
const expectEn = process.argv[3] || expectVi;
const STORES = [['vi', 'default', expectVi], ['en', 'launchpad_en', expectEn]];
const PAGES = [['home', '/'], ['pdp', '/atlas-pouf.html'], ['cart', '/checkout/cart/']];
const results = [];
const log = (name, pass, detail = '') => {
    const l = `${pass ? 'PASS' : 'FAIL'} | ${name}${detail ? ' | ' + detail : ''}`;
    results.push(l);
    console.log(l);
};

async function openPopup(browser, storeCode, path) {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    const errors = [];
    page.on('console', m => m.type() === 'error' && errors.push(m.text()));
    page.on('pageerror', e => errors.push(String(e)));
    // a store cookie alone does not switch the store view here; ___store does
    const url = BASE + path + (storeCode === 'default' ? '' : `?___store=${storeCode}`);
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    const flag = await page.evaluate(() => window.launchpadLoginRedirect || null);
    const store = await page.evaluate(() => (typeof CURRENT_STORE_CODE !== 'undefined' ? CURRENT_STORE_CODE : null));
    if (store !== storeCode) {
        throw new Error(`store view is ${store}, expected ${storeCode}`);
    }
    await page.click('#customer-menu');
    await page.click('[id="customer.header.sign.in.link"]');
    await page.locator('#social_login_email').waitFor({ state: 'visible', timeout: 10000 });
    return { ctx, page, errors, flag };
}

async function successCase(browser, [label, storeCode, expected], [pageName, path]) {
    const name = `${label} ${pageName} success`;
    const { ctx, page, errors, flag } = await openPopup(browser, storeCode, path);
    try {
        let endpoint = '';
        await page.route(LOGIN_ENDPOINT, route => {
            endpoint = route.request().url();
            route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ errors: false, message: 'Login successful.' })
            });
        });
        // the sign-in link is href="#": reload() navigates without the fragment
        const before = page.url().split('#')[0];
        await page.fill('#social_login_email', 'slp207.qa@example.com');
        await page.fill('#social_login_pass', 'Dummy-Passw0rd');
        const nav = page.waitForRequest(
            r => r.isNavigationRequest() && r.frame() === page.mainFrame(),
            { timeout: 15000 }
        );
        await page.click('#bnt-social-login-authentication');
        const target = (await nav).url();
        const want = expected === 'yes' ? DASHBOARD : before;
        log(name, target === want && flag && flag.toDashboard === (expected === 'yes'),
            `flag=${JSON.stringify(flag)} endpoint=${endpoint.replace(BASE, '')} from=${before.replace(BASE, '')} nav=${target.replace(BASE, '')} want=${want.replace(BASE, '')} consoleErrors=${JSON.stringify(errors)}`);
    } catch (e) {
        log(name, false, String(e).split('\n')[0]);
    } finally {
        await ctx.close();
    }
}

async function wrongPasswordCase(browser, [label, storeCode]) {
    const name = `${label} home wrong-password (real server)`;
    const { ctx, page, errors } = await openPopup(browser, storeCode, '/');
    try {
        const before = page.url();
        let navigated = '';
        page.on('request', r => {
            if (r.isNavigationRequest() && r.frame() === page.mainFrame()) navigated = r.url();
        });
        await page.fill('#social_login_email', 'slp207.nobody@example.com');
        await page.fill('#social_login_pass', 'Wrong-Passw0rd');
        const resp = page.waitForResponse(r => LOGIN_ENDPOINT.test(r.url()), { timeout: 30000 });
        await page.click('#bnt-social-login-authentication');
        const body = await (await resp).json();
        await page.waitForTimeout(4000);
        const msg = await page.locator('#social-login-authentication .message-error').first().textContent().catch(() => '');
        log(name, body.errors === true && !navigated && page.url() === before && msg.trim() !== '',
            `errors=${body.errors} message="${(msg || '').trim()}" navigated=${navigated || 'none'} consoleErrors=${JSON.stringify(errors)}`);
        await page.screenshot({ path: `${__dirname}/wrong-password-${label}-flag-${expectVi}.png` });
    } catch (e) {
        log(name, false, String(e).split('\n')[0]);
    } finally {
        await ctx.close();
    }
}

(async () => {
    const browser = await chromium.launch({
        executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome'
    });
    const run = async (name, fn) => {
        try {
            await fn();
        } catch (e) {
            log(name, false, String(e).split('\n')[0]);
        }
    };
    for (const store of STORES) {
        for (const p of PAGES) {
            await run(`${store[0]} ${p[0]} success`, () => successCase(browser, store, p));
        }
        await run(`${store[0]} home wrong-password`, () => wrongPasswordCase(browser, store));
    }
    await browser.close();
    const failed = results.filter(r => r.startsWith('FAIL')).length;
    console.log(`\n${results.length - failed}/${results.length} PASS`);
    process.exit(failed ? 1 : 0);
})();
