const { chromium } = require('playwright');

// BUG-8G0WCT / SLP-244 — cart extra fee required validation must follow admin config.
// usage: node verify-slp244.js <no|required> [shot]
const BASE = 'http://slaunchpad.localhost';
const PHASE = process.argv[2] || 'no';
const SHOT = process.argv[3] === 'shot';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const page = await browser.newPage({ viewport: { width: 1504, height: 940 } });
    const consoleErrors = [];
    page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    page.on('pageerror', e => consoleErrors.push('PAGEERROR: ' + e.message));

    // 1. Add test product to cart
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(2500);
    await page.goto(BASE + '/checkout/cart/', { waitUntil: 'domcontentloaded', timeout: 90000 });

    // 2. Wait for extra fee options to render (rule config fetched via REST after private-content-loaded)
    await page.waitForSelector('#mp-extra-fee input[type="checkbox"]', { timeout: 20000 });

    // 3. Inspect required markup
    const requiredEls = await page.locator('.mp-extra-fee-required').count();
    const htmlRequired = await page.locator('#mp-extra-fee input[type="checkbox"]').first().getAttribute('required');
    console.log(`[${PHASE}] .mp-extra-fee-required count: ${requiredEls}`);
    console.log(`[${PHASE}] checkbox HTML5 required attr: ${JSON.stringify(htmlRequired)}`);

    // 3b. required-select phase: tick one option first — must navigate even though rule is required
    if (PHASE === 'required-select') {
        await page.locator('#mp-extra-fee input[type="checkbox"]').first().check();
        await page.waitForTimeout(1500);
    }

    // 4. Click proceed-to-checkout and observe navigation
    const urlBefore = page.url();
    await Promise.all([
        page.waitForTimeout(3500),
        page.click('#checkout-link-button'),
    ]);
    const navigated = !page.url().includes('/checkout/cart/');
    const warning = await page.locator('#block-extrafee-summary .message.warning').count();
    console.log(`[${PHASE}] url before: ${urlBefore}`);
    console.log(`[${PHASE}] navigated away from cart: ${navigated} (now ${page.url()})`);
    console.log(`[${PHASE}] .message.warning rendered: ${warning}`);
    if (warning) {
        console.log(`[${PHASE}] warning text: ${(await page.locator('#block-extrafee-summary .message.warning span').first().innerText()).trim()}`);
    }

    const expectedRequired = PHASE === 'required' || PHASE === 'required-select';
    const pass = !expectedRequired
        ? requiredEls === 0 && navigated
        : PHASE === 'required-select'
            ? navigated
            : requiredEls > 0 && !navigated && warning > 0;
    console.log(`[${PHASE}] RESULT: ${pass ? 'PASS' : 'FAIL'}`);

    if (SHOT) {
        await page.screenshot({ path: `/tmp/pw-cal/slp244-${PHASE}.png`, fullPage: true });
        console.log(`[${PHASE}] screenshot: /tmp/pw-cal/slp244-${PHASE}.png`);
    }
    console.log(`[${PHASE}] console errors: ${consoleErrors.length}${consoleErrors.length ? ' -> ' + consoleErrors.slice(0, 3).join(' | ') : ''}`);

    await browser.close();
    process.exit(pass ? 0 : 1);
})();
