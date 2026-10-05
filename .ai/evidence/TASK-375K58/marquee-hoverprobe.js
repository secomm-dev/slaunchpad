const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.PW_CHROME || undefined, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  for (const width of [375, 1440]) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 } });
    await ctx.route('**/slaunchpad.localhost/', async (route) => {
      const res = await route.fetch({ url: 'http://127.0.0.1/', headers: { host: 'slaunchpad.localhost' } });
      const body = (await res.text()).replace('class="lp-cat-slider"', 'class="lp-cat-slider lp-cat-slider--marquee" data-lp-marquee="6"');
      await route.fulfill({ status: res.status(), contentType: res.headers()['content-type'] || 'text/html', body });
    });
    const page = await ctx.newPage();
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'load', timeout: 60000 });
    await page.locator('[data-secomm-ui-component="categories_a"]').scrollIntoViewIfNeeded();
    await page.evaluate(() => {
      const sec = document.querySelector('[data-secomm-ui-component="categories_a"]');
      window.__ev = [];
      ['pointerenter', 'pointerleave', 'pointerover', 'pointerout', 'mouseenter', 'mouseleave'].forEach((t) =>
        sec.addEventListener(t, () => window.__ev.push(t + '@' + Math.round(performance.now()))));
    });
    const box = await page.locator('[data-secomm-ui-component="categories_a"] .lp-cat-tile').nth(2).boundingBox();
    console.log(width, 'box:', Math.round(box.x), Math.round(box.y));
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.waitForTimeout(400);
    const ev = await page.evaluate(() => window.__ev);
    const paused = await page.evaluate(() => {
      const t = document.querySelector('[data-secomm-ui-component="categories_a"] [data-track]');
      const s1 = t.scrollLeft;
      return new Promise((r) => setTimeout(() => r({ d: Math.round(t.scrollLeft - s1), ev: window.__ev }), 600));
    });
    console.log(width, 'events:', JSON.stringify(ev), 'delta600ms:', paused.d);
    await ctx.close();
  }
  await browser.close();
})();
