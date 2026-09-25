// FEAT-W0XFV2 (SLP-270) — product card brand line, Chrome 150.
// Local DB has no brand data: the "with brand" case is simulated by writing
// text into the first card's .hp-card-brand (no DB writes).
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost/';
const OUT = __dirname;
const PAGES = [
    { name: 'home', url: BASE, widths: [375, 1440] },
    { name: 'plp', url: BASE + 'collections/yoga-new.html', widths: [375, 1440] },
    { name: 'plp-list', url: BASE + 'collections/yoga-new.html?product_list_mode=list', widths: [1440] },
];

(async () => {
    const browser = await chromium.launch({
        executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome',
    });
    let fail = 0;
    const check = (label, ok, info) => {
        if (!ok) fail++;
        console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${info ? ' — ' + info : ''}`);
    };

    for (const p of PAGES) {
        for (const w of p.widths) {
            const page = await browser.newPage({ viewport: { width: w, height: 900 } });
            const errors = [];
            page.on('pageerror', (e) => errors.push(e.message));
            await page.goto(p.url, { waitUntil: 'networkidle', timeout: 90000 });
            const tag = `${p.name}@${w}`;

            const r = await page.evaluate(() => {
                const cards = [...document.querySelectorAll('.hp-card')].filter((c) => c.offsetParent);
                const out = { count: cards.length, rows: [] };
                if (!cards.length) return out;
                const brands = cards.map((c) => c.querySelector('.hp-card-brand'));
                out.missingBrandEl = brands.filter((b) => !b).length;
                const b0 = brands[0];
                const cs = getComputedStyle(b0);
                out.empty = {
                    h: b0.getBoundingClientRect().height,
                    ariaHidden: b0.getAttribute('aria-hidden'),
                };
                // Simulate a short brand on card 0 and a very long one on card 1.
                b0.textContent = 'Nike';
                if (brands[1]) brands[1].textContent = 'Very Long Brand Name Manufacturer International Co. Ltd '.repeat(4);
                const name0 = cards[0].querySelector('.hp-card-name').parentElement;
                out.brand = {
                    fontSize: cs.fontSize, lineHeight: cs.lineHeight, fontWeight: cs.fontWeight,
                    color: cs.color, textTransform: cs.textTransform,
                    h: b0.getBoundingClientRect().height,
                    gap: name0.getBoundingClientRect().top - b0.getBoundingClientRect().bottom,
                };
                if (brands[1]) {
                    out.long = {
                        h: brands[1].getBoundingClientRect().height,
                        overflow: brands[1].scrollWidth > brands[1].clientWidth,
                        textOverflow: getComputedStyle(brands[1]).textOverflow,
                    };
                }
                // Row alignment: name tops of cards sharing the same card top.
                const byTop = {};
                cards.forEach((c) => {
                    const t = Math.round(c.getBoundingClientRect().top);
                    const nt = c.querySelector('.hp-card-name').getBoundingClientRect().top;
                    (byTop[t] = byTop[t] || []).push(nt);
                });
                out.rowSpread = Math.max(0, ...Object.values(byTop).filter((a) => a.length > 1)
                    .map((a) => Math.max(...a) - Math.min(...a)));
                out.hscroll = document.documentElement.scrollWidth > window.innerWidth;
                return out;
            });

            if (!r.count) { check(`${tag} cards found`, false, '0 cards'); await page.close(); continue; }
            check(`${tag} every card has .hp-card-brand (${r.count} cards)`, r.missingBrandEl === 0);
            check(`${tag} AC2 empty brand line 20px + aria-hidden`, Math.abs(r.empty.h - 20) < 1 && r.empty.ariaHidden === 'true', JSON.stringify(r.empty));
            const b = r.brand;
            check(`${tag} AC1 brand 14/20 500 rgb(153,161,175) no transform`,
                b.fontSize === '14px' && b.lineHeight === '20px' && b.fontWeight === '500'
                && b.color === 'rgb(153, 161, 175)' && b.textTransform === 'none', JSON.stringify(b));
            check(`${tag} AC1 name 8px below brand`, Math.abs(b.gap - 8) <= 1, `gap=${b.gap}`);
            if (r.long) check(`${tag} AC3 long brand 1 line ellipsis`, Math.abs(r.long.h - 20) < 1 && r.long.textOverflow === 'ellipsis' && r.long.overflow, JSON.stringify(r.long));
            check(`${tag} AC2 names aligned per row (brand vs no brand)`, r.rowSpread <= 1, `spread=${r.rowSpread}`);
            check(`${tag} AC4 no h-scroll`, !r.hscroll);
            check(`${tag} AC4 0 pageerror`, errors.length === 0, errors.join(' | '));

            const card = page.locator('.hp-card').filter({ visible: true }).first();
            await card.scrollIntoViewIfNeeded();
            const row = card.locator('xpath=..').locator('xpath=..');
            await row.screenshot({ path: `${OUT}/${tag}-cards.png` }).catch(() => card.screenshot({ path: `${OUT}/${tag}-cards.png` }));
            await page.close();
        }
    }
    await browser.close();
    console.log(fail ? `\n${fail} FAIL` : '\nALL PASS');
    process.exit(fail ? 1 : 0);
})();
