// Probe rendered homepage after user's 04:50 admin save — measure sections vs v4.8 expectations
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e).slice(0, 200)));
  await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForSelector('[data-content-type="slider"]', { state: 'attached', timeout: 30000 });
  await page.waitForTimeout(1500);
  await page.evaluate(() => new Promise((res) => {
    let y = 0; const t = setInterval(() => { y += 800; scrollTo(0, y); if (y >= document.body.scrollHeight) { clearInterval(t); scrollTo(0, 0); setTimeout(res, 600); } }, 120);
  }));
  await page.waitForTimeout(800);

  const m = await page.evaluate(() => {
    const $ = (s) => document.querySelector(s);
    const $$ = (s) => Array.from(document.querySelectorAll(s));
    const rect = (el) => {
      if (!el) return null;
      const b = el.getBoundingClientRect();
      return { w: Math.round(b.width), h: Math.round(b.height), x: Math.round(b.x) };
    };
    const disp = (s) => ($(s) ? getComputedStyle($(s)).display : null);
    return {
      hero: rect($('[data-content-type="slider"]')),
      heroH: $('[data-content-type="slider"]') ? Math.round($('[data-content-type="slider"]').getBoundingClientRect().height) : null,
      heroClass: $('[data-content-type="slider"]')?.className || null,
      slides: $$('[data-content-type="slide"]').length,
      slideH: $('[data-content-type="slide"]') ? Math.round($('[data-content-type="slide"]').getBoundingClientRect().height) + 'px (expect 1024)' : null,
      tilesEls: $$('[data-content-type="column-line"], [data-content-type="column-group"]').length,
      col1Children: (() => {
        const el = $$('[data-content-type="column-line"]')[0] || $$('[data-content-type="column-group"]')[0];
        return el ? Array.from(el.children).map((c) => c.tagName).join(',') : 'none';
      })(),
      uspCols: (() => {
        const groups = $$('[data-content-type="column-group"], [data-content-type="column-line"]');
        const g = groups.find((g) => g.children.length === 4);
        return g ? 4 : 0;
      })(),
      promoCard: rect($('.lp-promo-card')),
      video: rect($('[data-secomm-ui-component="embed_a"]')?.closest('[data-content-type="html"]')),
      cards: $$('.hp-card').length,
      cardDisplay: disp('.hp-card'),
      trackW: rect($('[data-page-builder-slider-track]'))?.w ?? null,
      newsletter: rect($('.lp-newsletter-form')),
      newsSymmetry: (() => {
        const f = $('.lp-newsletter-form');
        if (!f) return null;
        const b = f.getBoundingClientRect();
        return Math.round(Math.abs(b.x + b.width / 2 - innerWidth / 2));
      })(),
      flashRowDisplay: ($('.lp-flash-row') ? getComputedStyle($('.lp-flash-row')).display : 'n/a'),
      markers: $$('nav[data-pager] .snap-marker').length,
      markerCurrent: !!$('nav[data-pager] .snap-marker[aria-current="true"]'),
      uspGrid: (() => {
        const row = $$('[data-content-type="row"]').find((r) => r.querySelector('.lp-usp'));
        if (!row) return null;
        const holder = row.querySelector('[data-content-type="column-line"]') || row.querySelector('[data-content-type="column-group"]');
        const cols = holder ? Array.from(holder.children).filter((c) => c.dataset.contentType === 'column') : [];
        const ws = cols.map((c) => Math.round(c.getBoundingClientRect().width));
        const cs = getComputedStyle(holder);
        return { display: cs.display, cols: ws, template: cs.gridTemplateColumns.slice(0, 60) };
      })(),
      videoPoster: (() => {
        const el = $('[data-secomm-ui-component="embed_a"]');
        if (!el) return null;
        const img = el.querySelector('img');
        return {
          hasImg: !!img,
          imgLoaded: img ? img.complete && img.naturalWidth > 0 : false,
          bg: getComputedStyle(el).backgroundImage.slice(0, 80),
        };
      })(),
      bodyScrollW: document.body.scrollWidth,
      pbStyleBlocks: $$('style').filter((s) => s.textContent.includes('data-pb-style')).length,
    };
  });

  console.log(JSON.stringify(m));
  await page.screenshot({ path: '/tmp/hp-after-save-1440.png', fullPage: true });
  console.log('pageerrors:', errors.length ? errors.slice(0, 5) : 'none');
  await browser.close();
})();
