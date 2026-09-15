/* TASK-WXBQYZ baseline — console errors WITHOUT touching the coupon form. */
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({
    headless: true,
    args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
  });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  const errs = [];
  page.on('console', (m) => m.type() === 'error' && errs.push(m.text()));
  await page.goto('http://slaunchpad.localhost/joust-duffle-bag.html', { waitUntil: 'load' });
  await page.click('#product-addtocart-button');
  await page.waitForLoadState('load');
  await page.waitForTimeout(1500);
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('toggle-cart')));
  await page.waitForSelector('.coupon-form', { timeout: 15000 });
  await page.waitForTimeout(1000);
  console.log('baseline console errors:', errs.length);
  errs.forEach((e) => console.log(' -', e.slice(0, 140)));
  await browser.close();
})();
