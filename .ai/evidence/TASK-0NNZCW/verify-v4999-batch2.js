const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const out = {};
  for (const W of [375, 1440]) {
    const page = await (await browser.newContext({ viewport: { width: W, height: 1000 } })).newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(String(e).slice(0, 100)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('.hp-card', { state: 'attached', timeout: 30000 });
    await page.waitForTimeout(1500);
    out[W] = await page.evaluate(() => {
      const $ = (s) => document.querySelector(s);
      const $$ = (s) => Array.from(document.querySelectorAll(s));
      const cs = (el) => (el ? getComputedStyle(el) : null);
      const card = $('.hp-card');
      const rating = $('.hp-card-rating');
      const flashTrack = $('.lp-flash [data-track]');
      const flashPager = $('.lp-flash nav[data-pager]');
      const journalPager = $('.lp-journal-slider nav[data-pager]');
      const railNav = $('[data-content-type="products"] [data-page-builder-slider-nav]');
      const headings = {};
      $$('[data-content-type="heading"]').forEach((h) => {
        const t = (h.textContent || '').trim();
        if (['Flash sale', 'Living Room', 'Why us?', 'Journal', 'Category', 'Everyday more value'].includes(t) && !headings[t]) {
          const c = getComputedStyle(h);
          headings[t] = { color: c.color, size: c.fontSize, align: c.textAlign, weight: c.fontWeight };
        }
      });
      const arrows = $$('[data-content-type="button-item"] a svg').length;
      const blogBtn = $$('a[href="/blog"]')[0];
      return {
        cardBg: cs(card).backgroundColor,
        rating: rating ? rating.textContent.trim().slice(0, 20) : null,
        ratingStar: !!$('.hp-card-rating svg'),
        flashMobileGrid: flashTrack ? { display: cs(flashTrack).display, cols: cs(flashTrack).gridTemplateColumns.slice(0, 20), overflow: cs(flashTrack).overflowX } : null,
        flashPagerHidden: flashPager ? cs(flashPager).display === 'none' : 'no-pager',
        journalPagerHidden: journalPager ? cs(journalPager).display === 'none' : 'no-pager',
        railNavVisible: railNav ? cs(railNav).display !== 'none' : null,
        headings,
        ctaArrows: arrows,
        blogBtnPrimary: blogBtn ? blogBtn.className.includes('button-primary') : null,
        hScroll: document.body.scrollWidth > innerWidth,
      };
    });
    out[W].pageerrors = errs.length;
    await page.close();
  }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
