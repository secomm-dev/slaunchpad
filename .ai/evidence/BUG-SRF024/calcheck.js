const { chromium } = require('playwright');

const BASE = 'http://slaunchpad.localhost';
const phase = process.argv[2] || 'repro';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const page = await browser.newPage({ viewport: { width: 1504, height: 940 } });
    const consoleErrors = [];
    page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    page.on('pageerror', e => consoleErrors.push('PAGEERROR: ' + e.message));

    // helper: navigate via menu anchor (secret-keyed URLs) instead of direct URLs
    const gotoMenu = async (matcher) => {
        const href = await page.evaluate((m) => {
            const a = document.querySelector('a[href*="' + m + '"]');
            return a ? a.href : null;
        }, matcher);
        if (!href) throw new Error('menu link not found: ' + matcher);
        await page.goto(href, { waitUntil: 'domcontentloaded', timeout: 90000 });
        return href;
    };

    await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.fill('#username', 'qc01calendar');
    await page.fill('#login', 'QcCal2026!x');
    await Promise.all([
        page.waitForNavigation({ timeout: 60000 }).catch(() => {}),
        page.click('.action-login'),
    ]);
    console.log('logged in:', page.url());

    // --- PRIMARY: Date Off grid on mpdeliverytime config section (SLP-147's exact field) ---
    // Admin secret keys hash route/controller/action, not the section param -> reuse a keyed section URL.
    let target = 'dateoff';
    const cfgHref = await page.evaluate(() => {
        const a = document.querySelector('a[href*="system_config/edit/section/"][href*="/key/"]');
        return a ? a.getAttribute('href') : null;
    });
    if (cfgHref) {
        const keyed = cfgHref.replace(/section\/[a-z_]+\//, 'section/mpdeliverytime/');
        await page.goto(keyed, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(2000);
        const marker = await page.locator('input.mpdeliverytime-dropdown-attribute-required').count();
        const h1 = await page.locator('.page-heading h1, .page-heading').first().textContent().catch(() => '');
        console.log('dateoff page h1:', (h1 || '').trim(), '| marker count:', marker);
        if (marker === 0) {
            console.log('Date Off grid NOT rendered -> fallback to order grid date-range');
            target = 'daterange';
        }
    } else {
        target = 'daterange';
    }

    if (target === 'dateoff') {
        // expand the collapsed config group (fieldset.config.admin__collapsible-block)
        await page.evaluate(() => {
            const marker = document.querySelector('input.mpdeliverytime-dropdown-attribute-required');
            const fs = marker && marker.closest('fieldset.config');
            const a = fs && fs.querySelector('legend a');
            if (a) { a.click(); return; }
            if (fs) { fs.style.display = 'block'; }
        });
        await page.waitForTimeout(800);
        const addBtn = page.locator(
            'xpath=(//input[contains(@class,"mpdeliverytime-dropdown-attribute-required")]' +
            '/ancestor::tfoot[1]//button[contains(@class,"action-add")])[1]'
        );
        await addBtn.waitFor({ state: 'visible', timeout: 30000 });
        if ((await page.locator('input[id*="date_off"]').count()) === 0) {
            await addBtn.click();
        }
        await page.waitForSelector('input[id*="date_off"]', { state: 'attached', timeout: 15000 });
        await page.waitForSelector('.ui-datepicker-trigger', { timeout: 30000 });
    } else {
        // fallback: order grid date-range filter (mage.dateRange) with Filters expanded
        const orderHref = await page.evaluate(() => {
            const a = document.querySelector('a[href*="order/index"][href*="/key/"]');
            return a ? a.getAttribute('href') : null;
        });
        if (!orderHref) throw new Error('orders menu link not found');
        await page.goto(orderHref, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForSelector('button[data-action="grid-filter-expand"]', { timeout: 60000 });
        await page.click('button[data-action="grid-filter-expand"]');
        await page.waitForSelector('input._has-datepicker', { state: 'visible', timeout: 60000 });
        await page.waitForSelector('.ui-datepicker-trigger', { timeout: 30000 });
    }

    const findPosSrc = await page.evaluate(() => window.jQuery.datepicker.constructor.prototype._findPos.toString());
    console.log('findPos: pageXOffset=' + findPosSrc.includes('pageXOffset'),
        'getBoundingClientRect=' + findPosSrc.includes('getBoundingClientRect'));

    const SEL = target === 'dateoff' ? 'input[id*="date_off"]' : 'input._has-datepicker';
    const input = page.locator(SEL).first();
    await input.scrollIntoViewIfNeeded();
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await input.scrollIntoViewIfNeeded();
    await page.waitForTimeout(300);
    // open the picker via its trigger icon (same interaction as SLP-147)
    const trigger = input.locator('xpath=following-sibling::button[contains(@class,"ui-datepicker-trigger")]');
    if (await trigger.count()) {
        await trigger.click();
    } else {
        await input.click({ position: { x: 5, y: 5 } });
    }
    await page.waitForTimeout(600);

    const dpVisible = await page.evaluate(() => {
        const dp = document.getElementById('ui-datepicker-div');
        return !!(dp && (dp.offsetWidth || dp.offsetHeight));
    });
    console.log('datepicker visible:', dpVisible);

    const m = await page.evaluate((sel) => {
        const inp = document.querySelector(sel);
        const dp = document.getElementById('ui-datepicker-div');
        const j = window.jQuery;
        const io = j(inp).offset();
        const po = j(dp).offset();
        return {
            scrollY: Math.round(window.pageYOffset),
            inputViewportTop: Math.round(inp.getBoundingClientRect().top),
            inputDocTop: Math.round(io.top),
            inputH: inp.offsetHeight,
            dpDocTop: Math.round(po.top),
            dpH: Math.round(j(dp).outerHeight()),
        };
    }, SEL);

    const deltaTop = m.dpDocTop - (m.inputDocTop + m.inputH);
    const belowOk = Math.abs(deltaTop) <= 5;
    const aboveOk = Math.abs((m.dpDocTop + m.dpH) - m.inputDocTop) <= 5;
    const pass = dpVisible && (belowOk || aboveOk);
    console.log(JSON.stringify({ phase, ...m, deltaTop, belowOk, aboveOk, pass }, null, 2));
    await page.screenshot({ path: '/tmp/pw-cal/order-' + phase + '.png' });
    console.log('RESULT(' + phase + '): ' + (pass ? 'PASS' : 'FAIL'));
    console.log('consoleErrors(' + consoleErrors.length + '):', JSON.stringify(consoleErrors.slice(0, 5)));
    await browser.close();
    process.exit(pass ? 0 : 1);
})().catch(e => { console.error('FATAL', e.message); process.exit(2); });
