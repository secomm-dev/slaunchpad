const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();
  await page.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  const before = await page.locator('[x-text="summaryCount"]').first().textContent();
  await page.locator('.hp-btn-atc').first().click();
  await page.waitForTimeout(3000);
  const after = await page.locator('[x-text="summaryCount"]').first().textContent();
  console.log(`counter before=${before.trim()} after=${after.trim()} -> ${parseInt(after) > parseInt(before) ? 'PASS' : 'FAIL'}`);
  console.log('url still PLP:', page.url().includes('/gear/bags.html') ? 'PASS' : 'FAIL ' + page.url());
  await browser.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
