/**
 * TASK-K14RVZ (SLP-217) verify live DOM: browser-native validation bubble text
 * trên cart page qty input, store vi (default).
 * Cases:
 *   A — qty rỗng  → validationMessage = "Vui lòng điền vào trường này."
 *   B — qty 10001 → validationMessage = "Giá trị phải nhỏ hơn hoặc bằng 10000."
 *   C — qty 2     → submit KHÔNG bị kẹt custom validity (cart persist qty=2 sau reload)
 * Chạy (root, shell này): node verify-cart-validation.js
 */
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');

const BASE = 'http://slaunchpad.localhost';

function flag(name, ok, detail = '') {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  — ' + detail : ''}`);
}

(async () => {
  const browser = await chromium.launch({
    args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
  });
  const ctx = await browser.newContext({ locale: 'en-US', viewport: { width: 1280, height: 900 } });
  // locale en-US: browser UI English — native bubbles sẽ EN; site override phải VI
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));

  // ATC — Atlas Pouf (simple product, Luma sample data local)
  await page.goto(BASE + '/atlas-pouf.html', { waitUntil: 'networkidle' });
  await page.click('#product-addtocart-button');
  await page.waitForTimeout(1500);

  await page.goto(BASE + '/checkout/cart/', { waitUntil: 'networkidle' });
  const hasQty = await page.locator('input[data-role="cart-item-qty"]').count();
  flag('cart page có qty input', hasQty >= 1, `count=${hasQty}`);
  if (!hasQty) { await browser.close(); process.exit(1); }

  const listenerAttached = await page.evaluate(() => !!window.secommCartQtyValidationMessages);
  flag('listener TASK-K14RVZ attached', listenerAttached);

  // Case A — empty qty
  await page.locator('input[data-role="cart-item-qty"]').first().fill('');
  await page.locator('button[data-cart-item-update]').first().click();
  await page.waitForTimeout(500);
  const msgA = await page.evaluate(
    () => document.querySelector('input[data-role="cart-item-qty"]').validationMessage
  );
  flag('A: empty → VI valueMissing', msgA === 'Vui lòng điền vào trường này.', `got="${msgA}"`);
  await page.screenshot({ path: __dirname + '/case-a-empty.png' });

  // Case B — qty over max (max_sale_qty default 10000)
  await page.locator('input[data-role="cart-item-qty"]').first().fill('10001');
  await page.locator('button[data-cart-item-update]').first().click();
  await page.waitForTimeout(500);
  const msgB = await page.evaluate(
    () => document.querySelector('input[data-role="cart-item-qty"]').validationMessage
  );
  flag('B: 10001 → VI rangeOverflow + max interpol', msgB === 'Giá trị phải nhỏ hơn hoặc bằng 10000.', `got="${msgB}"`);
  await page.screenshot({ path: __dirname + '/case-b-overflow.png' });

  // Case C — valid qty submits (custom validity cleared on input, không kẹt form)
  await page.locator('input[data-role="cart-item-qty"]').first().fill('2');
  await page.locator('button[data-cart-item-update]').first().click();
  await page.waitForTimeout(2500);
  await page.reload({ waitUntil: 'networkidle' });
  const qtyAfter = await page.evaluate(
    () => document.querySelector('input[data-role="cart-item-qty"]')?.value
  );
  flag('C: valid submit → qty=2 persist', qtyAfter === '2', `got="${qtyAfter}"`);

  flag('không có pageerror mới', errors.length === 0, errors.join(' | ').slice(0, 300));

  await browser.close();
})();
