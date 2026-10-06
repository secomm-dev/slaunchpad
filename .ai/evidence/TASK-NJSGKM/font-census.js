const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const page = await browser.newPage();
    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);

    const census = await page.evaluate(() => {
        const buckets = {};
        const offenders = [];
        const all = document.querySelectorAll('*');
        for (const el of all) {
            const ff = getComputedStyle(el).fontFamily || '';
            const first = ff.split(',')[0].replace(/["']/g, '').trim();
            buckets[first] = (buckets[first] || 0) + 1;
            if (first !== 'Inter') {
                const txt = (el.childNodes.length && [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim()))
                    ? el.textContent.trim().slice(0, 40) : '';
                if (offenders.length < 200) {
                    const path = [];
                    let n = el;
                    while (n && n !== document.body && path.length < 4) {
                        path.unshift(n.tagName.toLowerCase() + (n.className && typeof n.className === 'string' ? '.' + n.className.split(/\s+/).slice(0, 2).join('.') : ''));
                        n = n.parentElement;
                    }
                    offenders.push({ fam: first, tag: el.tagName.toLowerCase(), path: path.join(' > '), txt, visible: el.offsetParent !== null });
                }
            }
        }
        // icon-font sanity: elements computing luma-icons / fontawesome / porto
        const icons = offenders.filter(o => /icons|awesome/i.test(o.fam)).slice(0, 10);
        return { buckets, offenders: offenders.filter(o => !/icons|awesome/i.test(o.fam)), icons };
    });
    console.log('=== BUCKETS (font-family first token x element count) ===');
    console.log(JSON.stringify(census.buckets, null, 1));
    console.log('=== NON-INTER TEXT ELEMENTS (unique fam+path, first 40) ===');
    const seen = new Set(); let shown = 0;
    for (const o of census.offenders) {
        const k = o.fam + '|' + o.path;
        if (seen.has(k)) continue; seen.add(k);
        console.log(`${o.fam.padEnd(12)} ${o.visible ? 'VIS' : 'hid'} ${o.path}${o.txt ? '  "' + o.txt + '"' : ''}`);
        if (++shown >= 40) break;
    }
    console.log('=== ICON FONTS INTACT (AC-3) ===');
    census.icons.forEach(o => console.log(`${o.fam.padEnd(12)} ${o.path}`));
    await browser.close();
})().catch(e => { console.error('FAIL:', e.message); process.exit(1); });
