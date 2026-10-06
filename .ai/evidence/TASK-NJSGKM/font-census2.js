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
    const res = await page.evaluate(() => {
        const rows = [];
        for (const el of document.body.querySelectorAll('*')) {
            const ff = getComputedStyle(el).fontFamily || '';
            const first = ff.split(',')[0].replace(/["']/g, '').trim();
            if (first === 'Inter' || /icons|awesome/i.test(first)) continue;
            const ownText = [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim());
            rows.push({ fam: first, tag: el.tagName.toLowerCase(), cls: (typeof el.className === 'string' ? el.className.split(/\s+/).slice(0, 3).join('.') : ''), id: el.id || '', ownText, visible: el.offsetParent !== null, txt: ownText ? el.textContent.trim().slice(0, 45) : '' });
        }
        return rows;
    });
    const uniq = new Map();
    res.forEach(r => { const k = r.fam + '|' + r.tag + '|' + r.cls + '|' + r.id; if (!uniq.has(k)) uniq.set(k, { ...r, n: 1 }); else uniq.get(k).n++; });
    console.log(`non-Inter trong body: ${res.length} elements / ${uniq.size} unique`);
    [...uniq.values()].forEach(r => console.log(`${r.fam.padEnd(10)} x${String(r.n).padEnd(4)} ${r.tag}${r.id ? '#' + r.id : ''}${r.cls ? '.' + r.cls : ''} ${r.visible ? 'VIS' : 'hid'}${r.txt ? '  "' + r.txt + '"' : ''}`));
    await browser.close();
})().catch(e => { console.error('FAIL:', e.message); process.exit(1); });
