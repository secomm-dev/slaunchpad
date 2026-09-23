const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1300 } })).newPage();
  await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForSelector('.hp-card', { state: 'attached', timeout: 30000 });
  await page.waitForTimeout(1500);
  console.log(JSON.stringify(await page.evaluate(() => {
    const describe = (gal) => {
      if (!gal) return null;
      const ownNavs = gal.querySelectorAll(':scope nav[data-pager]').length;
      const directChildren = Array.from(gal.children).map((c) => `${c.tagName.toLowerCase()}.${(c.className || '').toString().split(' ').slice(0, 3).join('.')}`).slice(0, 6);
      return {
        children: directChildren,
        ownPagers: ownNavs,
        trackSlides: gal.querySelectorAll('[data-track] > *').length,
      };
    };
    const flashGal = document.querySelector('[class*="flash"] [data-lp-card-slider]');
    const railGal = document.querySelector('[data-content-type="products"] [data-lp-card-slider]');
    // rail slider root: pager vị trí + marker classes
    const railRoot = document.querySelector('[data-content-type="products"] .snap-slider');
    const railPager = railRoot ? railRoot.querySelector(':scope > nav[data-pager]') || railRoot.querySelector('nav[data-pager]') : null;
    const flashRoot = document.querySelector('[class*="flash"] .snap-slider');
    const flashPager = flashRoot ? flashRoot.querySelector(':scope > nav[data-pager]') || flashRoot.querySelector('nav[data-pager]') : null;
    return {
      flashGallery: describe(flashGal),
      railGallery: describe(railGal),
      railRootPager: railPager ? { markers: railPager.querySelectorAll('a,button').length, cls: railPager.className, parent: railPager.parentElement.className.slice(0, 50) } : null,
      flashRootPager: flashPager ? { markers: flashPager.querySelectorAll('a,button').length, cls: flashPager.className, parent: flashPager.parentElement.className.slice(0, 50) } : null,
    };
  })));
  await browser.close();
})();
