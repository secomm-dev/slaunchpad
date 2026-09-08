const { chromium } = require('playwright');
const phase = process.argv[2] || 'verify';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const page = await browser.newPage({ viewport: { width: 1200, height: 500 } });
    const errs = [];
    page.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0, 200)); });
    page.on('pageerror', e => errs.push('PAGEERROR: ' + e.message.slice(0, 200)));
    await page.goto('http://slaunchpad.localhost/admin/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.fill('#username', 'qc01calendar');
    await page.fill('#login', 'QcCal2026!x');
    await Promise.all([page.waitForNavigation({timeout:60000}).catch(()=>{}), page.click('.action-login')]);
    const orderHref = await page.evaluate(() => {
        const a = document.querySelector('a[href*="order/index"][href*="/key/"]');
        return a ? a.getAttribute('href') : null;
    });
    if (!orderHref) throw new Error('orders menu link not found');
    await page.goto(orderHref, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForSelector('button[data-action="grid-filter-expand"]', { timeout: 60000 });
    await page.waitForSelector('.admin__data-grid-loading-mask', { state: 'hidden', timeout: 60000 }).catch(() => {});
    await page.click('button[data-action="grid-filter-expand"]');
    await page.waitForTimeout(1200);
    await page.waitForSelector('.admin__data-grid-loading-mask', { state: 'hidden', timeout: 60000 }).catch(() => {});
    await page.waitForSelector('input._has-datepicker', { state: 'visible', timeout: 60000 });
    await page.waitForSelector('.ui-datepicker-trigger', { timeout: 30000 });
    await page.waitForTimeout(1000);

    const input = page.locator('input._has-datepicker').first();
    await input.scrollIntoViewIfNeeded();
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await input.scrollIntoViewIfNeeded();
    await page.waitForTimeout(300);
    await page.evaluate(() => {
        const inp = document.querySelector('input._has-datepicker');
        const trig = inp && inp.nextElementSibling;
        if (trig && trig.classList.contains('ui-datepicker-trigger')) { trig.click(); return 'trigger'; }
        if (inp) { inp.focus(); return 'focus'; }
        return 'none';
    });
    await page.waitForTimeout(600);

    const m = await page.evaluate(() => {
        const inp = document.querySelector('input._has-datepicker');
        const dp = document.getElementById('ui-datepicker-div');
        const j = window.jQuery;
        const io = j(inp).offset();
        const po = j(dp).offset();
        const dRangeRegistered = !!j.mage.dateRange;
        return {
            scrollY: Math.round(window.pageYOffset),
            inputDocTop: Math.round(io.top), inputH: inp.offsetHeight,
            dpDocTop: Math.round(po.top), dpH: Math.round(j(dp).outerHeight()),
            dRangeRegistered,
        };
    });
    const deltaTop = m.dpDocTop - (m.inputDocTop + m.inputH);
    const belowOk = Math.abs(deltaTop) <= 5;
    const aboveOk = Math.abs((m.dpDocTop + m.dpH) - m.inputDocTop) <= 5;
    const pass = belowOk || aboveOk;
    console.log(JSON.stringify({ phase, ...m, deltaTop, belowOk, aboveOk, pass }, null, 2));
    await page.screenshot({ path: '/tmp/pw-cal/daterange-' + phase + '.png' });
    console.log('RESULT(dateRange ' + phase + '): ' + (pass ? 'PASS' : 'FAIL'));
    console.log('errors:', JSON.stringify(errs.slice(0, 4)));
    await browser.close();
    process.exit(pass ? 0 : 1);
})().catch(e => { console.error('FATAL', e.message); process.exit(2); });
