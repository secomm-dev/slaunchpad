const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const out = {};
  for (const W of [375, 1440]) {
    const page = await (await browser.newContext({ viewport: { width: W, height: 1000 } })).newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(String(e).slice(0, 100)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('.lp-cat-tile', { state: 'attached', timeout: 30000 });
    await page.waitForTimeout(1400);
    out[W] = await page.evaluate(() => {
      const $ = (s) => document.querySelector(s);
      const track = $('.lp-cat-track');
      const tr = track.getBoundingClientRect();
      const tiles = Array.from(track.querySelectorAll('.lp-cat-tile'));
      const pager = $('.lp-cat-slider nav[data-pager]');
      const promoHolder = $('.lp-promo-card')?.parentElement;
      const ph = promoHolder?.getBoundingClientRect();
      const flash = $('.lp-flash-track')?.getBoundingClientRect();
      return {
        cat: {
          tiles: tiles.length,
          tileW: Math.round(tiles[0].getBoundingClientRect().width),
          trackRight: Math.round(tr.right),
          trackLeft: Math.round(tr.left),
          bleedsToViewport: Math.abs(tr.right - innerWidth) < 30,
          pagerMarkers: pager ? pager.querySelectorAll('.snap-marker').length : 0,
          pagerVisible: pager ? getComputedStyle(pager).display !== 'none' : false,
        },
        promo: ph ? { right: Math.round(ph.right), bleeds: Math.abs(ph.right - innerWidth) < 30, cards: Math.round(document.querySelector('.lp-promo-card').getBoundingClientRect().width) } : null,
        flashBleeds: flash ? Math.abs(flash.right - innerWidth) < 30 : null,
        hScroll: document.body.scrollWidth > innerWidth,
      };
    });
    out[W].pageerrors = errs.length;
    if (W === 375) {
      out[375].dotClick = await page.evaluate(() => new Promise((res) => {
        const track = document.querySelector('.lp-cat-track');
        const from = Math.round(track.scrollLeft);
        const marker = document.querySelectorAll('.lp-cat-slider nav[data-pager] .snap-marker')[2];
        if (!marker) { res({ note: 'no marker' }); return; }
        marker.click();
        setTimeout(() => {
          const to = Math.round(track.scrollLeft);
          res({ from, to, moved: to !== from });
        }, 800);
      }));
      await page.locator('.lp-cat-slider').screenshot({ path: '/tmp/cat-slider-375.png' });
    } else {
      await page.locator('.lp-cat-slider').screenshot({ path: '/tmp/cat-slider-1440.png' });
    }
    await page.close();
  }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
