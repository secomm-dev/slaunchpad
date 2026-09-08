const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';

const dumpBlocks = () => {
    const vis = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    return {
        blocks: [...document.querySelectorAll('.delivery-information')].map(b => ({
            visible: vis(b),
            kind: b.getAttributeNames().some(a => a.startsWith('wire:')) ? 'MAGEWIRE' : (b.innerHTML.includes('mp-delivery-date') ? 'KNOCKOUT' : 'unknown'),
            title: (b.querySelector('.delivery-date .title') || {}).textContent?.trim(),
            y: Math.round(b.getBoundingClientRect().y),
        })),
        flatpickr: document.querySelectorAll('.flatpickr-calendar').length,
        uiDatepicker: document.querySelectorAll('.ui-datepicker').length,
        windowFlatpickr: typeof window.flatpickr,
    };
};

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    const page = await ctx.newPage();
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/checkout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);

    console.log('=== BEFORE address fill ===');
    console.log(JSON.stringify(await page.evaluate(dumpBlocks)));

    // enumerate candidate fields
    const fields = await page.evaluate(() => ({
        inputs: [...document.querySelectorAll('input')].filter(i => i.name).map(i => ({ name: i.name, id: i.id, type: i.type, visible: !!(i.offsetWidth || i.offsetHeight) })),
        selects: [...document.querySelectorAll('select')].filter(s => s.name).map(s => ({ name: s.name, id: s.id, visible: !!(s.offsetWidth || s.offsetHeight), options: s.options.length })),
    }));
    console.log('=== fields ==='); console.log(JSON.stringify(fields, null, 1));

    // fill guest form (best-effort, log misses)
    const step = async (label, fn) => { try { await fn(); console.log('ok:', label); } catch (e) { console.log('MISS:', label, '-', e.message.split('\n')[0]); } };
    await step('email', () => page.fill('input[name="customer-email"]', 'qc.delivery@example.com'));
    await step('firstname', () => page.fill('input[name="firstname"]', 'QC'));
    await step('lastname', () => page.fill('input[name="lastname"]', 'Delivery'));
    await step('street', () => page.fill('input[name="street[0]"]', '12 Nguyen Hue'));
    await step('telephone', () => page.fill('input[name="telephone"]', '0901234567'));
    await step('region select', () => page.selectOption('select[name="region_id"]', { index: 1 }));
    await page.waitForTimeout(2500);
    await step('city select', () => page.selectOption('select[name="custom_city"]', { index: 1 }));
    await page.waitForTimeout(2500);
    const wardSel = await page.evaluate(() => [...document.querySelectorAll('select')].filter(s => s.name && /ward|sub_?city|district/i.test(s.name)).map(s => s.name));
    console.log('ward-like selects:', wardSel);
    for (const w of wardSel) { await step('select ' + w, () => page.selectOption(`select[name="${w}"]`, { index: 1 })); await page.waitForTimeout(2000); }

    await page.waitForTimeout(4000); // let Magewire sections refresh
    console.log('=== AFTER address fill ===');
    console.log(JSON.stringify(await page.evaluate(dumpBlocks)));

    // click the first VISIBLE delivery-date input and inspect which calendar pops
    const clicked = await page.evaluate(() => {
        const vis = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
        const inputs = [...document.querySelectorAll('.delivery-date input')].filter(vis);
        if (!inputs.length) return { clicked: false };
        inputs[0].scrollIntoView({ block: 'center' });
        inputs[0].click();
        return { clicked: true, id: inputs[0].id, cls: inputs[0].className };
    });
    console.log('clicked input:', JSON.stringify(clicked));
    await page.waitForTimeout(1500);
    const cal = await page.evaluate(() => {
        const vis = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
        const ui = document.querySelector('.ui-datepicker');
        const fp = document.querySelector('.flatpickr-calendar.open');
        const month = (sel) => [...document.querySelectorAll(sel)].map(e => e.textContent.trim()).filter(Boolean).slice(0, 3);
        return {
            uiDatepickerVisible: ui ? vis(ui) : false,
            uiMonthHeader: ui ? month('.ui-datepicker-title') : [],
            uiWeekdays: ui ? month('.ui-datepicker-calendar th span') : [],
            flatpickrVisible: fp ? vis(fp) : false,
            fpMonth: fp ? month('.flatpickr-monthDropdown-months') : [],
            fpWeekdays: fp ? month('.flatpickr-weekday') : [],
        };
    });
    console.log('calendar after click:', JSON.stringify(cal, null, 1));
    await browser.close();
})();
