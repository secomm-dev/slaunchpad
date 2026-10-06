const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.PW_CHROME || undefined, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 375, height: 900 } });
  await ctx.route('**/slaunchpad.localhost/', async (route) => {
    const res = await route.fetch({ url: 'http://127.0.0.1/', headers: { host: 'slaunchpad.localhost' } });
    const body = (await res.text()).replace('class="lp-cat-slider"', 'class="lp-cat-slider lp-cat-slider--marquee" data-lp-marquee="6"');
    await route.fulfill({ status: res.status(), contentType: res.headers()['content-type'] || 'text/html', body });
  });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/', { waitUntil: 'load', timeout: 60000 });
  await page.locator('[data-secomm-ui-component="categories_a"]').scrollIntoViewIfNeeded();
  const info = await page.evaluate(() => {
    const out = {};
    for (const [x, y] of [[308, 392], [187, 392], [187, 300], [187, 500]]) {
      const el = document.elementFromPoint(x, y);
      const sec = el && el.closest('[data-secomm-ui-component="categories_a"]');
      out[`${x},${y}`] = el ? (el.tagName + '.' + String(el.className).slice(0, 40) + ' inCat=' + !!sec) : 'null';
    }
    return out;
  });
  console.log(JSON.stringify(info, null, 1));
  await browser.close();
})();
