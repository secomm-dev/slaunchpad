// TASK-HARDAR (SLP-267): verify homepage Category = Secomm UI categories_a slider.
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.PW_CHROME || undefined, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const out = {};
  for (const width of [375, 768, 1440]) {
    const page = await (await browser.newContext({ viewport: { width, height: 900 } })).newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(String(e).slice(0, 120)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'load', timeout: 60000 });
    await page.waitForSelector('[data-secomm-ui-component="categories_a"]', { timeout: 30000 });
    await page.waitForTimeout(1200);
    const res = await page.evaluate(() => {
      const sec = document.querySelector('[data-secomm-ui-component="categories_a"]');
      const track = sec.querySelector('[data-track]');
      const tiles = Array.from(track.querySelectorAll('a.lp-cat-tile'));
      const pager = sec.querySelector('nav[data-pager]');
      const markers = pager ? Array.from(pager.querySelectorAll('.snap-marker, a, button')) : [];
      const cs = getComputedStyle(tiles[0]);
      return {
        heading: sec.querySelector('h2').textContent.trim(),
        headingSize: getComputedStyle(sec.querySelector('h2')).fontSize,
        tiles: tiles.length,
        tileW: tiles.map((t) => Math.round(t.getBoundingClientRect().width)),
        tileBg: cs.backgroundColor,
        labels: tiles.map((t) => t.querySelector('.lp-cat-tile-label').textContent.trim()),
        labelTransform: getComputedStyle(tiles[0].querySelector('.lp-cat-tile-label')).textTransform,
        hrefs: tiles.map((t) => t.getAttribute('href')),
        imgsLoadedOrLazy: tiles.map((t) => t.querySelector('img').getAttribute('src')).every((s) => s.includes('/media/wysiwyg/homepage/cat-')),
        trackDisplay: getComputedStyle(track).display,
        overflowing: track.scrollWidth > track.clientWidth + 1,
        pagerVisible: !!pager && getComputedStyle(pager).display !== 'none',
        markers: markers.length,
        pageHScroll: document.documentElement.scrollWidth > window.innerWidth,
        oldTiles: document.querySelectorAll('.lp-tile, .lp-tiles-dots').length,
      };
    });
    if (res.pagerVisible && res.markers > 2) {
      const before = await page.$eval('[data-secomm-ui-component="categories_a"] [data-track]', (t) => t.scrollLeft);
      await page.locator('[data-secomm-ui-component="categories_a"] nav[data-pager] .snap-marker').nth(2).click();
      await page.waitForTimeout(900);
      res.dotClick = await page.$eval('[data-secomm-ui-component="categories_a"]', (sec, b) => ({
        scrollBefore: b,
        scrollAfter: Math.round(sec.querySelector('[data-track]').scrollLeft),
        active: Array.from(sec.querySelectorAll('nav[data-pager] .snap-marker')).findIndex((m) => m.getAttribute('aria-current') === 'true'),
      }), before);
    }
    // TinyMCE Save bọc directive trong <p> → mô phỏng markup đó, <p> rỗng phải ẩn.
    res.pWrapped = await page.evaluate(() => {
      const sec = document.querySelector('[data-secomm-ui-component="categories_a"]');
      const text = sec.closest('[data-content-type="text"]');
      if (!text) return 'no text element';
      const html = text.innerHTML;
      text.innerHTML = '<p>' + html + '</p>';
      const ps = Array.from(text.querySelectorAll(':scope > p'));
      const out = { inTextElement: true, ps: ps.length, emptyPHeights: ps.filter((p) => !p.textContent.trim()).map((p) => p.getBoundingClientRect().height + parseFloat(getComputedStyle(p).marginTop) + parseFloat(getComputedStyle(p).marginBottom)) };
      text.innerHTML = html;
      return out;
    });
    await page.locator('[data-secomm-ui-component="categories_a"]').screenshot({ path: `${__dirname}/cat-${width}.png` });
    res.pageErrors = errs;
    out[width] = res;
    await page.close();
  }
  console.log(JSON.stringify(out, null, 2));
  await browser.close();
})();
