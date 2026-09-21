const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1300 } })).newPage();
  await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForSelector('.hp-card', { state: 'attached', timeout: 30000 });
  await page.waitForTimeout(1500);
  console.log(JSON.stringify(await page.evaluate(() => {
    const info = (gal) => {
      const pager = gal.querySelector('nav[data-pager]');
      const markers = pager ? Array.from(pager.querySelectorAll('a,button')) : [];
      const slides = gal.querySelectorAll('[data-track] > *').length;
      const cs = pager ? getComputedStyle(pager) : null;
      const m0 = markers[0] ? getComputedStyle(markers[0]) : null;
      return {
        slides,
        hasPager: !!pager,
        markerCount: markers.length,
        pagerVisible: cs ? cs.visibility : null,
        pagerDisplay: cs ? cs.display : null,
        markerW: m0 ? Math.round(parseFloat(m0.width)) : null,
        markerBg: m0 ? m0.backgroundColor : null,
        markerClasses: markers[0] ? markers[0].className : null,
        inited: gal.dataset.lpSliderInit || '0',
      };
    };
    const flashGal = document.querySelector('[class*="flash"] [data-lp-card-slider]');
    const railGal = document.querySelector('[data-content-type="products"] [data-lp-card-slider]');
    const railGals = document.querySelectorAll('[data-content-type="products"] [data-lp-card-slider]').length;
    const flashGals = document.querySelectorAll('[class*="flash"] [data-lp-card-slider]').length;
    const allGals = document.querySelectorAll('[data-lp-card-slider]').length;
    return { allGals, flashGals, railGals, flash: flashGal ? info(flashGal) : null, rail: railGal ? info(railGal) : null };
  })));
  await browser.close();
})();
