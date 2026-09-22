const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const out = {};
  for (const W of [375, 768, 1440]) {
    const page = await (await browser.newContext({ viewport: { width: W, height: 900 } })).newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(String(e).slice(0, 100)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('.lp-tile', { state: 'attached', timeout: 30000 });
    await page.waitForTimeout(1200);
    out[W] = await page.evaluate(() => {
      const holder = document.querySelector('.lp-tile').parentElement;
      const nav = document.querySelector('.lp-tiles-dots');
      if (!holder || !nav) return { holder: !!holder, nav: !!nav };
      const hr = holder.getBoundingClientRect();
      const nr = nav.getBoundingClientRect();
      const dots = Array.from(nav.querySelectorAll('.lp-tiles-dot'));
      return {
        tiles: holder.children.length,
        holderW: Math.round(hr.width),
        overflow: holder.scrollWidth > holder.clientWidth,
        dotsCount: dots.length,
        navVisible: nr.width > 0 && getComputedStyle(nav).display !== 'none',
        navBelowTiles: nr.top >= hr.bottom - 2,
        activeIdx: dots.findIndex((d) => d.getAttribute('aria-current') === 'true'),
        dotActiveW: (() => { const a = dots.find((d) => d.getAttribute('aria-current') === 'true'); return a ? Math.round(a.getBoundingClientRect().width) : null; })(),
        dotInactiveW: (() => { const i = dots.find((d) => d.getAttribute('aria-current') !== 'true'); return i ? Math.round(i.getBoundingClientRect().width) : null; })(),
      };
    });
    if (W === 375) {
      out[375].clickTest = await page.evaluate(() => new Promise((res) => {
        const holder = document.querySelector('.lp-tile').parentElement;
        const from = Math.round(holder.scrollLeft);
        document.querySelectorAll('.lp-tiles-dots .lp-tiles-dot')[3].click();
        setTimeout(() => {
          const to = Math.round(holder.scrollLeft);
          const active = Array.from(document.querySelectorAll('.lp-tiles-dots .lp-tiles-dot')).findIndex((d) => d.getAttribute('aria-current') === 'true');
          res({ from, to, moved: to !== from, activeDot: active });
        }, 800);
      }));
    }
    out[W].pageerrors = errs.length;
    await page.close();
  }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
