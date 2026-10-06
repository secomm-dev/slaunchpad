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
    await page.waitForTimeout(500);
    const sec = page.locator('[data-secomm-ui-component="categories_a"]');
    await sec.screenshot({ path: `/tmp/pw-cal/marquee-${width}-t0.png` });
    await page.waitForTimeout(1200);
    await sec.screenshot({ path: `/tmp/pw-cal/marquee-${width}-t1.png` });
    await ctx.close();
  }
  await browser.close();
  console.log('shots done');
})();
