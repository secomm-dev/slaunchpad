const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const browser = await chromium.launch({ executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome', args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await browser.newContext()).newPage();
  await page.route('**/checkout/cart/add/**', r => r.fulfill({ status: 200, contentType: 'text/html', body: 'not-json' }));
  await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'networkidle' });
  await page.click('#product-addtocart-button'); await page.waitForTimeout(2500);
  console.log('T6_error_path', JSON.stringify(await page.$$eval('#messages .message', els => els.map(e => ({ cls: e.className.replace(/\s+/g,' ').slice(0,40), text: e.textContent.trim().replace(/\s+/g,' ') })))));
  await browser.close();
})();
