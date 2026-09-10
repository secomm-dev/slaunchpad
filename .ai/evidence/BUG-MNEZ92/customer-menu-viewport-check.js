const { chromium } = require('playwright');

// BUG-MNEZ92 (SLP-186): account dropdown clipped at 1440x900 + 1024x768 on demo.
// Root cause (verified 2026-09-09): demo static deploy 09-07 10:42 predates the
// styles.css rebuild in commit 082ba2d6 (09-08) -> demo CSS lacks `.sm\:left-auto`
// and `.sm\:-me-4`, so `left-0` wins the LTR over-constrained resolution at every
// breakpoint and the nav opens to the RIGHT of the icon, past the viewport edge.
// This script proves the LOCAL (correct CSS) build fits at the reported viewports.
const BASE = 'http://slaunchpad.localhost';
const OUT = '/var/www/projects/slaunchpad/.ai/evidence/BUG-MNEZ92';

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
        // stock desktop anchoring: nav right edge == button right edge + 16px (-me-4)
        const rightGapStock = Math.abs((btnBox.x + btnBox.width + 16) - (box.x + box.width));

        await page.screenshot({ path: `${OUT}/dropdown-${label}.png`, clip: { x: 0, y: 0, width: viewport.width, height: 500 } });

        console.log(`[${label}] viewport=${vw} nav.x=${box.x.toFixed(1)} nav.right=${(box.x + box.width).toFixed(1)} width=${box.width.toFixed(1)}`);
        console.log(`[${label}] fitsInViewport=${fits} stockRightAnchored(gapToBtnRight+16)=${rightGapStock.toFixed(1)}`);
        console.log(`[${label}] consoleErrors=${consoleErrors.length ? JSON.stringify(consoleErrors) : 'none'}`);
        await page.close();
        return { fits, rightGapStock, desktop: vw >= 640 };
    };

    // Reported failing viewports first, then regressions (BUG-H929MC set)
    const r1440 = await run('desktop-1440x900', { width: 1440, height: 900 });
    const r1024 = await run('desktop-1024x768', { width: 1024, height: 768 });
    const r1280 = await run('desktop-1280', { width: 1280, height: 800 });
    const r375 = await run('mobile-375', { width: 375, height: 812 });
    await browser.close();

    const desktops = [r1440, r1024, r1280].filter(r => r.desktop);
    const pass = r1440.fits && r1024.fits && r375.fits
        && desktops.every(r => r.rightGapStock < 3);
    console.log(pass ? 'RESULT: PASS' : 'RESULT: FAIL');
    process.exit(pass ? 0 : 1);
})().catch(e => { console.error('FATAL', e); process.exit(2); });
