const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const out = {};
  for (const w of [1680, 1920]) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 1000 } });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(String(e).slice(0, 120)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('.hp-card', { state: 'attached', timeout: 30000 });
    await page.waitForTimeout(1500);
    out[w] = await page.evaluate(() => {
      const $ = (s) => document.querySelector(s);
      const $$ = (s) => Array.from(document.querySelectorAll(s));
      const wpx = (el) => (el ? Math.round(el.getBoundingClientRect().width) : null);
      const hpx = (el) => (el ? Math.round(el.getBoundingClientRect().height) : null);
      // rails = PB products sliders; flash = FlashSaleList widget rail
      const railCards = $$('.lp-pb-track > [data-content-type] .hp-card, .lp-pb-track .hp-card').slice(0, 4).map(wpx);
      const flashCards = $$('[class*="flash"] .hp-card').slice(0, 4).map(wpx);
      const holder = (sel) => {
        const row = $$('[data-content-type="row"]').find((r) => r.querySelector(sel));
        if (!row) return null;
        const h = row.querySelector('[data-content-type="column-line"]:has(> [data-content-type="column"])')
          || row.querySelector('[data-content-type="column-group"]');
        return h ? { w: Math.round(h.getBoundingClientRect().width), x: Math.round(h.getBoundingClientRect().x) } : null;
      };
      return {
        hero: { w: wpx($('[data-content-type="slider"]')), h: hpx($('[data-content-type="slider"]')) },
        tiles: holder('.lp-tile'),
        tileW: wpx($('.lp-tile')),
        usp: holder('.lp-usp'),
        uspCardW: wpx($('.lp-usp')),
        flashCardW: flashCards,
        railCardW: railCards,
        promo: { w: wpx($('.lp-promo-card')), h: hpx($('.lp-promo-card')) },
        video: { w: wpx($('[data-secomm-ui-component="embed_a"]')?.closest('[data-content-type="html"]')), h: hpx($('[data-secomm-ui-component="embed_a"]')?.closest('[data-content-type="html"]')) },
        newsletter: { w: wpx($('.lp-newsletter-form')), x: Math.round($('.lp-newsletter-form')?.getBoundingClientRect().x || 0) },
        split: $$('[data-appearance="full-width"] [data-content-type="column"]:nth-child(-n+2)').slice(0, 2).map(wpx),
        hScroll: document.body.scrollWidth > innerWidth,
        cards: $$('.hp-card').length,
      };
    });
    out[w].pageerrors = errors.length;
    await page.screenshot({ path: `/tmp/hp-wide-${w}.png`, fullPage: true });
    await ctx.close();
  }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
