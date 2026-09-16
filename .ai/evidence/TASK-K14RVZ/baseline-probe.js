/**
 * TASK-K14RVZ baseline probe: disable fix qua guard flag (addInitScript set
 * window.secommCartQtyValidationMessages=true → IIFE skip → không listener).
 * Expect: swatch pageerrors VẪN xuất hiện (pre-existing noise) + validationMessage
 * = English native → chứng minh VI text đến từ change set, noise không phải.
 */
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';

(async () => {
  const browser = await chromium.launch({
    args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
  });
  const ctx = await browser.newContext({ locale: 'en-US', viewport: { width: 1280, height: 900 } });
  await ctx.addInitScript(() => { window.secommCartQtyValidationMessages = true; });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]));

  await page.goto(BASE + '/atlas-pouf.html', { waitUntil: 'networkidle' });
  await page.click('#product-addtocart-button');
  await page.waitForTimeout(1500);
  await page.goto(BASE + '/checkout/cart/', { waitUntil: 'networkidle' });

  await page.locator('input[data-role="cart-item-qty"]').first().fill('');
  await page.locator('button[data-cart-item-update]').first().click();
  await page.waitForTimeout(500);
  const msgA = await page.evaluate(
    () => document.querySelector('input[data-role="cart-item-qty"]').validationMessage
  );
  console.log(`baseline msgA (guard skip → native EN): "${msgA}"`);
  console.log('baseline pageerrors:');
  errors.forEach((e) => console.log('  -', e.slice(0, 120)));
  await browser.close();
})();
