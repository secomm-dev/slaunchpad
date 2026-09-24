const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForSelector('.hp-card', { state: 'attached', timeout: 30000 });
  await page.waitForTimeout(1200);
  console.log(JSON.stringify(await page.evaluate(() => {
    const arrowState = (a) => {
      if (!a) return null;
      const after = getComputedStyle(a, '::after');
      const on = after.content !== 'none' && after.content !== '' && parseFloat(after.width) > 0;
      return { arrow: on, color: getComputedStyle(a).color };
    };
    const byExactText = (txt, hrefPart) => Array.from(document.querySelectorAll('a[href*="' + hrefPart + '"]'))
      .find((a) => (a.textContent || '').trim() === txt);
    const heroBtns = Array.from(document.querySelectorAll('[data-content-type="slide"] [data-content-type="button-item"] a')).map(arrowState);
    return {
      heroArrows: heroBtns.filter((s) => s.arrow).length,
      heroBtnCount: heroBtns.length,
      flashCta: arrowState(byExactText('Explore all', '/flash-sale')),
      promoExploreAll: arrowState(byExactText('Explore all', '/collection')),
      goToCollection: arrowState(byExactText('Go to Collection', '/collection')),
      realspacesExplore: arrowState(byExactText('Explore', '/lookbook')),
      blogViewAll: arrowState(byExactText('View all post', '/blog')),
      sofaBtn: arrowState(byExactText('Shop in-stock sofa', '/sofas')),
      hScroll: document.body.scrollWidth > innerWidth,
    };
  })));
  await browser.close();
})();
