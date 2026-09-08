const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    const page = await ctx.newPage();
    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/checkout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);

    const dump = await page.evaluate(() => {
        const vis = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
        const info = {
            lang: document.documentElement.lang,
            blocks: [...document.querySelectorAll('.delivery-information')].map(b => ({
                visible: vis(b),
                isMagewire: b.getAttributeNames().some(a => a.startsWith('wire:')),
                hasWireDescendant: b.innerHTML.includes('wire:'),
                hasFlatpickrRef: b.innerHTML.includes('flatpickr') || b.innerHTML.includes('deliveryDateInput'),
                title: (b.querySelector('.delivery-date .title') || {}).textContent?.trim(),
                snippet: b.outerHTML.replace(/\s+/g, ' ').slice(0, 260),
                rect: (r => ({ x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) }))(b.getBoundingClientRect()),
            })),
            flatpickrCalendars: document.querySelectorAll('.flatpickr-calendar').length,
            windowFlatpickr: typeof window.flatpickr,
            mpDatepickerInput: (() => {
                const i = document.querySelector('#mp-delivery-date');
                if (!i) return null;
                return { visible: vis(i), classes: i.className };
            })(),
            magewireDateInputs: [...document.querySelectorAll('input')].filter(i => i.getAttributeNames().some(a => a === 'wire:model' && i.getAttribute(a) === 'deliveryDate')).map(i => ({ visible: vis(i), id: i.id, cls: i.className })),
            jQuery: typeof window.jQuery,
            knockout: typeof window.ko,
        };
        return info;
    });
    console.log(JSON.stringify(dump, null, 2));
    await browser.close();
})();
