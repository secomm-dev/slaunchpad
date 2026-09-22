// TASK-0NNZCW v4.9.2: verify 3 user fixes
// 1) card gallery dot click must NOT scroll the outer product slider
// 2) promo (Everyday more value): fixed 360px cards, clip at container edge (no viewport bleed)
// 3) split (Living, Reimagined): full width, image flush left
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const results = {};

  for (const W of [1440, 1920]) {
    const ctx = await browser.newContext({ viewport: { width: W, height: 1000 } });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(String(e).slice(0, 120)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('.hp-card', { state: 'attached', timeout: 30000 });
    await page.waitForTimeout(1500);

    results[W] = await page.evaluate(() => {
      const $ = (s) => document.querySelector(s);
      const $$ = (s) => Array.from(document.querySelectorAll(s));
      const rect = (el) => {
        const b = el.getBoundingClientRect();
        return { x: Math.round(b.x), r: Math.round(b.right), w: Math.round(b.width) };
      };
      const out = {};

      // (3) split full width
      const splitRow = $$('[data-content-type="row"]')
        .filter((r) => r.dataset.appearance === 'full-width' || r.dataset.appearance === 'full-bleed')
        .find((r) => r.querySelector('[data-content-type="column-line"], [data-content-type="column-group"]'));
      if (splitRow) {
        const inner = splitRow.querySelector('[data-element="inner"]') || splitRow;
        const media = inner.querySelector('img');
        out.split = {
          innerW: Math.round(inner.getBoundingClientRect().width),
          innerMaxW: getComputedStyle(inner).maxWidth,
          imgX: media ? Math.round(media.getBoundingClientRect().x) : null,
          innerRight: Math.round(inner.getBoundingClientRect().right),
          viewportW: innerWidth,
        };
      }

      // (2) promo: no bleed, clip at container edge
      const promoHolder = $('.lp-promo-card')?.parentElement;
      if (promoHolder) {
        const cards = Array.from(promoHolder.children).filter((c) => c.classList.contains('lp-promo-card'));
        out.promo = {
          holder: rect(promoHolder),
          cardW: cards.map((c) => Math.round(c.getBoundingClientRect().width)),
          card4Clipped: cards.length >= 4
            ? Math.round(cards[3].getBoundingClientRect().right) - Math.round(promoHolder.getBoundingClientRect().right)
            : null,
          overflowX: getComputedStyle(promoHolder).overflowX,
        };
      }

      // (1) gallery sliders present?
      out.galleries = $$('[data-lp-card-slider]').length;
      return out;
    });

    // (1) interaction: find a VISIBLE card gallery with >=2 dots, click dot #2 via JS
    results[W].galleryDot = await page.evaluate(() => {
      const gals = Array.from(document.querySelectorAll('[data-content-type="products"] [data-lp-card-slider]'));
      const vis = (el) => { const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0 && b.right > 0 && b.left < innerWidth; };
      for (const gallery of gals) {
        const pager = gallery.querySelector('[data-pager]');
        if (!pager) continue;
        const dots = Array.from(pager.querySelectorAll('a, button'));
        if (dots.length < 2 || !vis(gallery)) continue;
        const outer = gallery.closest('.snap-slider');
        const outerTrack = outer && outer.querySelector('[data-track]');
        const innerTrack = gallery.querySelector('[data-track]');
        if (!outerTrack || !innerTrack) continue;
        const before = { outer: Math.round(outerTrack.scrollLeft), inner: Math.round(innerTrack.scrollLeft) };
        dots[1].click();
        return new Promise((res) => setTimeout(() => {
          const after = { outer: Math.round(outerTrack.scrollLeft), inner: Math.round(innerTrack.scrollLeft) };
          res({ before, after, dotCount: dots.length, innerMoved: after.inner !== before.inner, outerMoved: after.outer !== before.outer });
        }, 700));
      }
      return { note: 'no visible gallery with >=2 dots' };
    });

    // regression: outer slider nav arrow (data-next) still scrolls the rail
    const navBtn = page.locator('[data-content-type="products"] [data-page-builder-slider-nav] [data-next]').first();
    if (await navBtn.count()) {
      const oBefore = await page.evaluate(() => {
        const t = document.querySelector('[data-content-type="products"] .snap-slider [data-track]');
        return Math.round(t.scrollLeft);
      });
      await navBtn.click();
      await page.waitForTimeout(800);
      const oAfter = await page.evaluate(() => {
        const t = document.querySelector('[data-content-type="products"] .snap-slider [data-track]');
        return Math.round(t.scrollLeft);
      });
      results[W].outerNavWorks = oAfter !== oBefore;
    }

    results[W].hScroll = await page.evaluate(() => document.body.scrollWidth > innerWidth);
    results[W].pageerrors = errors.length;
    await page.screenshot({ path: `/tmp/hp-v492-${W}.png`, fullPage: false });
    await ctx.close();
  }

  console.log(JSON.stringify(results, null, 1));
  await browser.close();
})();
