const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const out = {};
  for (const W of [1440, 1680, 1920]) {
    const page = await (await browser.newContext({ viewport: { width: W, height: 1000 } })).newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(String(e).slice(0, 100)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('.hp-card', { state: 'attached', timeout: 30000 });
    await page.waitForTimeout(1400);
    out[W] = await page.evaluate(() => {
      const $ = (s) => document.querySelector(s);
      const $$ = (s) => Array.from(document.querySelectorAll(s));
      const rb = (el) => { const b = el.getBoundingClientRect(); return { l: Math.round(b.left), r: Math.round(b.right), w: Math.round(b.width) }; };
      const res = { viewport: innerWidth };
      // contained rows: fixed 1440 max (inner 1360)
      const contained = $$('[data-content-type="row"][data-appearance="contained"]');
      res.containedRows = contained.slice(0, 8).map((r) => rb(r).w);
      // rails: no bleed (track right ≈ holder right), 4 cards
      const railTrack = $('.lp-pb-track');
      if (railTrack) {
        const cards = Array.from(railTrack.children).map(rb);
        res.rail = {
          track: rb(railTrack),
          cardW: cards.slice(0, 4).map((c) => c.w),
          cardsInView: cards.filter((c) => c.l < innerWidth - 5).length,
        };
      }
      // flash: bleeds to viewport right
      const flashTrack = $('.lp-flash-track');
      if (flashTrack) {
        res.flash = { track: rb(flashTrack), bleedsToViewport: Math.abs(flashTrack.getBoundingClientRect().right - innerWidth) < 30 };
      }
      // full-bleed sections still full width
      res.hero = rb($('[data-content-type="slider"]')).w;
      // promo clip
      const promoHolder = $('.lp-promo-card')?.parentElement;
      if (promoHolder) res.promo = { ...rb(promoHolder) };
      res.hScroll = document.body.scrollWidth > innerWidth;
      return res;
    });
    out[W].pageerrors = errors.length;
    await page.close();
  }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
