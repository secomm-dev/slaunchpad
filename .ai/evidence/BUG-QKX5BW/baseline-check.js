const { chromium } = require('playwright');

const BASE = 'http://slaunchpad.localhost';
const OUT = __dirname;

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });

    const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
    const consoleErrors = [];
    page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    page.on('pageerror', e => consoleErrors.push('PAGEERROR: ' + e.message));

    await page.goto(BASE + '/customer/account/login/', { waitUntil: 'networkidle' });
    await page.fill('#email', 'qc-social@example.com');
    await page.fill('#pass', 'QcSocial123!');
    await page.click('button[name="send"]');
    await page.waitForURL(/customer\/account/);

    await page.goto(BASE + '/customer/account/', { waitUntil: 'networkidle' });
    const block = page.locator('.block-dashboard-social-login');
    await block.scrollIntoViewIfNeeded();
    await page.waitForTimeout(500);

    // metrics: every social anchor inside the dashboard block
    const metrics = await page.evaluate(() => {
        const out = [];
        document.querySelectorAll('.block-dashboard-social-login a.btn-social').forEach(a => {
            const r = a.getBoundingClientRect();
            out.push({
                cls: a.className,
                text: a.textContent.trim(),
                w: Math.round(r.width),
                h: Math.round(r.height),
                // truncation = content wider than the box
                truncated: a.scrollWidth > a.clientWidth + 1,
                clipped: a.scrollWidth,
                visible: r.width > 0 && r.height > 0,
            });
        });
        // ghost buttons from the dead second loop
        const ghosts = document.querySelectorAll('.block-dashboard-social-login button[class*="px-4"]').length;
        return { buttons: out, ghostButtons: ghosts };
    });
    console.log(JSON.stringify(metrics, null, 2));

    await block.screenshot({ path: OUT + '/baseline-mobile-375.png' });
    console.log('consoleErrors=' + (consoleErrors.length ? JSON.stringify(consoleErrors) : 'none'));

    await browser.close();
})().catch(e => { console.error('FATAL', e.message); process.exit(1); });
