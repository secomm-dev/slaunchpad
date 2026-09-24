// TASK-HARDAR round 4 (SLP-267): Category slider tràn mép phải + nav desktop.
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.PW_CHROME || undefined, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const out = {};
  for (const width of (process.env.WIDTHS || "375,768,1440,1920").split(",").map(Number)) {
    const page = await (await browser.newContext({ viewport: { width, height: 900 } })).newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(String(e).slice(0, 120)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'load', timeout: 60000 });
    const sel = '[data-secomm-ui-component="categories_a"]';
    await page.waitForSelector(sel, { timeout: 30000 });
    await page.locator(sel).scrollIntoViewIfNeeded();
    await page.waitForTimeout(1200);
    const measure = () => page.$eval(sel, (sec) => {
      const track = sec.querySelector('[data-track]');
      const tiles = Array.from(track.querySelectorAll('a.lp-cat-tile'));
      const tr = track.getBoundingClientRect();
      const sr = sec.getBoundingClientRect();
      const vis = (el) => !!el && getComputedStyle(el).display !== 'none' && getComputedStyle(el).visibility !== 'hidden';
      const prev = sec.querySelector('[data-prev]');
      const next = sec.querySelector('[data-next]');
      const pager = sec.querySelector('nav[data-pager]');
      return {
        tiles: tiles.length,
        tileW: Math.round(tiles[0].getBoundingClientRect().width),
        sectionRight: Math.round(sr.right),
        trackRight: Math.round(tr.right),
        viewport: document.documentElement.clientWidth,
        bleedsToEdge: tr.right >= document.documentElement.clientWidth - 1,
        tilesInsideContent: tiles.filter((t) => t.getBoundingClientRect().right <= sr.right + 1).length,
        peekingTile: tiles.some((t) => { const r = t.getBoundingClientRect(); return r.left < document.documentElement.clientWidth && r.right > sr.right + 1; }),
        scrollLeft: Math.round(track.scrollLeft),
        overflowing: track.scrollWidth > track.clientWidth + 1,
        nav: { prevVisible: vis(prev) && vis(prev.parentElement), nextVisible: vis(next) && vis(next.parentElement), prevDisabled: prev?.hasAttribute('disabled'), nextDisabled: next?.hasAttribute('disabled') },
        dots: vis(pager) ? pager.querySelectorAll('.snap-marker').length : 0,
        pageHScroll: document.documentElement.scrollWidth > document.documentElement.clientWidth,
      };
    });
    const res = await measure();
    await page.screenshot({ path: `${__dirname}/cat-bleed-${width}-initial.png`, clip: await page.$eval(sel, (s) => { const r = s.getBoundingClientRect(); return { x: 0, y: r.top + window.scrollY, width: document.documentElement.clientWidth, height: r.height }; }), fullPage: true });
    res.activePills = await page.$$eval(`${sel} nav[data-pager] .snap-marker`, (ms) => ms.filter((m) => m.getBoundingClientRect().width > 12).length);
    if (res.nav.nextVisible && !res.nav.nextDisabled) {
      await page.click(`${sel} [data-next]`);
      await page.waitForTimeout(900);
      const after = await measure();
      res.afterNext = { scrollLeft: after.scrollLeft, prevDisabled: after.nav.prevDisabled, nextDisabled: after.nav.nextDisabled };
    }
    await page.evaluate(() => window.scrollTo(0, window.scrollY)); 
    await page.screenshot({ path: `${__dirname}/cat-bleed-${width}.png`, clip: await page.$eval(sel, (s) => { const r = s.getBoundingClientRect(); return { x: 0, y: r.top + window.scrollY, width: document.documentElement.clientWidth, height: r.height }; }) , fullPage: true });
    res.pageErrors = errs;
    out[width] = res;
    await page.close();
  }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
