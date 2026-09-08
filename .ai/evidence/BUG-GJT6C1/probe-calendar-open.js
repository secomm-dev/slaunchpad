const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    const page = await ctx.newPage();
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/checkout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);

    // open the live (jQuery UI) datepicker programmatically
    const cal = await page.evaluate(() => {
        const $ = window.jQuery;
        const input = document.querySelector('#mp-delivery-date');
        if (!input) return { error: 'no #mp-delivery-date' };
        input.scrollIntoView({ block: 'center' });
        input.focus();
        if ($) { try { $(input).datepicker('show'); } catch (e) { return { error: 'show failed: ' + e.message }; } }
        const dp = document.querySelector('#ui-datepicker-div');
        const vis = (el) => el && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
        return {
            dpExists: !!dp,
            dpVisible: vis(dp),
            dpClasses: dp ? dp.className : '',
            title: dp ? [...dp.querySelectorAll('.ui-datepicker-title *')].map(e => e.textContent.trim()).join(' ') : '',
            weekdays: dp ? [...dp.querySelectorAll('.ui-datepicker-calendar thead th')].map(e => e.textContent.trim().replace(/<[^>]*>/g, '')) : [],
            weekdayAbbr: dp ? [...dp.querySelectorAll('.ui-datepicker-calendar thead th span')].map(e => e.getAttribute('title') + '=' + e.textContent.trim()) : [],
            regionalVi: $ && $.datepicker && $.datepicker.regional ? Object.keys($.datepicker.regional) : [],
            currentRegional: $ && $.datepicker ? ($._data ? 'n/a' : 'n/a') : 'n/a',
        };
    });
    console.log('calendar:', JSON.stringify(cal, null, 1));
    await page.waitForTimeout(600);
    await page.screenshot({ path: __dirname + '/live-calendar-knockout-vi.png', fullPage: false, animations: 'disabled', timeout: 60000 }).catch(e => console.log('shot miss:', e.message.split('\n')[0]));
    await browser.close();
})();
