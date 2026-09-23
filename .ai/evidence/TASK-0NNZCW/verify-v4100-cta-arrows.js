const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  const errs = [];
  page.on('pageerror', (e) => errs.push(String(e).slice(0, 100)));
  await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForSelector('.hp-card', { state: 'attached', timeout: 30000 });
  await page.waitForTimeout(1500);
  const res = await page.evaluate(() => {
    const $ = (s) => document.querySelector(s);
    const out = {};
    // hero: slide buttons must NOT contain arrow svg
    out.heroArrows = Array.from(document.querySelectorAll('[data-content-type="slide"] a svg')).length;
    // secondary buttons color
    const sec = $('a.pagebuilder-button-secondary');
    out.secondaryColor = sec ? getComputedStyle(sec).color : null;
    // secondary one-line: Explore (REAL SPACES)
    const explore = Array.from(document.querySelectorAll('a[href="/lookbook"]')).pop();
    if (explore) {
      const r = explore.getBoundingClientRect();
      const textSpan = explore.querySelector('[data-element="link_text"]');
      const tr = textSpan ? textSpan.getBoundingClientRect() : null;
      out.realspaces = { h: Math.round(r.height), oneLine: tr ? Math.abs(tr.bottom - r.bottom) < 30 : null, white: getComputedStyle(explore).whiteSpace };
    }
    // arrows on the two CTAs
    const cta = (txt) => {
      const a = Array.from(document.querySelectorAll('a')).find((x) => (x.textContent || '').trim() === txt && x.href.includes('/collection'));
      return a ? { arrow: !!a.querySelector('svg'), color: getComputedStyle(a).color } : null;
    };
    out.exploreAll = cta('Explore all');
    out.goToCollection = cta('Go to Collection');
    out.flashCta = (() => { const a = $('a[href="/flash-sale"]'); return a ? { arrow: !!a.querySelector('svg'), color: getComputedStyle(a).color } : null; })();
    out.hScroll = document.body.scrollWidth > innerWidth;
    return out;
  });
  res.pageerrors = errs.length;
  console.log(JSON.stringify(res, null, 1));
  // screenshots
  const catRow = page.locator('[data-content-type="row"]:has(.lp-cat-slider)');
  await page.locator('a[href="/lookbook"]').last().scrollIntoViewIfNeeded();
  await page.waitForTimeout(400);
  await page.locator('a[href="/lookbook"]').last().screenshot({ path: '/tmp/v410-realspaces-btn.png' });
  await browser.close();
})();
