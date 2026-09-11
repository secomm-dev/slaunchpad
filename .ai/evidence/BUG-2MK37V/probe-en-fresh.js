/**
 * BUG-2MK37V — en store fresh context (no session cookie) + failed-request URLs.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-en-fresh.js
 */
const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    const failed = [];
    const consoleMsgs = [];
    page.on('response', r => { if (r.status() >= 400) failed.push(r.status() + ' ' + r.url().slice(0, 140)); });
    page.on('console', m => { if (['error', 'warning'].includes(m.type())) consoleMsgs.push(m.type() + ': ' + m.text().slice(0, 140)); });
    page.on('pageerror', e => consoleMsgs.push('pageerror: ' + String(e).slice(0, 140)));

    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    // force store switch on checkout URL with fresh session
    await page.goto(BASE + '/onestepcheckout/?___store=launchpad_en', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForSelector('.opc-payment-additional.discount-code', { timeout: 30000 });
    await page.waitForTimeout(4000);

    const en = await page.evaluate(() => {
        const i = document.querySelector('#discount-code');
        const b = document.querySelector('.discount-code .actions-toolbar .action');
        return {
            placeholder: i?.placeholder,
            btnText: b?.textContent.trim(),
            dTop: b && i ? +(b.getBoundingClientRect().top - i.getBoundingClientRect().top).toFixed(1) : null,
            dBottom: b && i ? +(b.getBoundingClientRect().bottom - i.getBoundingClientRect().bottom).toFixed(1) : null,
        };
    });
    console.log('enFresh:', JSON.stringify(en));
    await page.locator('.opc-payment-additional.discount-code').screenshot({ path: 'discount-en-1280-fresh.png' });
    console.log('failedRequests(' + failed.length + '):');
    for (const f of failed.slice(0, 8)) console.log('  ' + f);
    console.log('consoleIssues(' + consoleMsgs.length + '):');
    for (const m of consoleMsgs.slice(0, 10)) console.log('  ' + m);
    await browser.close();
})().catch(e => { console.error('FAIL', e.message); process.exit(1); });
