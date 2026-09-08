const { chromium } = require('playwright');

const BASE = 'http://slaunchpad.localhost';
const OUT = '/var/www/projects/slaunchpad/.ai/evidence/BUG-H929MC';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });

    const run = async (label, viewport) => {
        const page = await browser.newPage({ viewport });
        const consoleErrors = [];
        page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
        page.on('pageerror', e => consoleErrors.push('PAGEERROR: ' + e.message));

        await page.goto(BASE + '/', { waitUntil: 'networkidle' });
        const btn = page.locator('#customer-menu');
        await btn.waitFor({ state: 'visible', timeout: 15000 });
        await btn.click();
        const nav = page.locator('nav[aria-labelledby="customer-menu"]');
        await nav.waitFor({ state: 'visible', timeout: 5000 });
        await page.waitForTimeout(400); // x-transition settle

        const box = await nav.boundingBox();
        const btnBox = await btn.boundingBox();
        const vw = viewport.width;
        const fits = box.x >= 0 && box.x + box.width <= vw;
        const rightGapStock = Math.abs((btnBox.x + btnBox.width + 16) - (box.x + box.width));

        await page.screenshot({ path: `${OUT}/dropdown-${label}.png`, clip: { x: 0, y: 0, width: viewport.width, height: 500 } });

        console.log(`[${label}] viewport=${vw} nav.x=${box.x.toFixed(1)} nav.right=${(box.x + box.width).toFixed(1)} width=${box.width.toFixed(1)}`);
        console.log(`[${label}] fitsInViewport=${fits} stockRightAnchored(gapToBtnRight+16)=${rightGapStock.toFixed(1)}`);
        console.log(`[${label}] consoleErrors=${consoleErrors.length ? JSON.stringify(consoleErrors) : 'none'}`);
        await page.close();
        return { fits, rightGapStock };
    };

    const mobile = await run('mobile-375', { width: 375, height: 812 });
    const desktop = await run('desktop-1280', { width: 1280, height: 800 });
    await browser.close();

    const pass = mobile.fits && desktop.fits && desktop.rightGapStock < 3;
    console.log(pass ? 'RESULT: PASS' : 'RESULT: FAIL');
    process.exit(pass ? 0 : 1);
})().catch(e => { console.error('FATAL', e); process.exit(2); });
