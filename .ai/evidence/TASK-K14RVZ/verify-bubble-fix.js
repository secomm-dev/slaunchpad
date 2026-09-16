/**
 * TASK-K14RVZ verify v2 (sau fix bubble không hiển thị):
 * Root cause regression: handler cũ preventDefault() + reportValidity() →
 * reportValidity fire invalid #2, listener tự cancel → report bị hủy →
 * KHÔNG bubble nào hiển thị (report QC user 09-16).
 * Fix: chỉ setCustomValidity (MDN pattern) — browser render bubble SAU dispatch
 * nên hiển thị message custom.
 *
 * Assertions:
 *   1. invalid event KHÔNG preventDefault (defaultPrevented=false) → per spec
 *      browser report → bubble hiển thị với validationMessage = custom text
 *   2. 1 invalid event/round (không còn double-fire từ reportValidity)
 *   3. validationMessage VI (case A empty, case B >max)
 *   4. Submit vẫn bị chặn khi invalid (0 POST updatePost)
 *   5. Valid submit hoạt động (qty persist)
 * Lưu ý: headless Chrome KHÔNG render native bubble (CDP screenshot không thấy
 * nên không phát hiện được regression lần trước) — bằng chứng hiển thị =
 * defaultPrevented=false (spec) + QC mắt thường trên browser thật.
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
  await ctx.addInitScript(() => {
    window.__k14invalid = [];
    window.addEventListener('invalid', (e) => {
      const rec = { target: e.target.getAttribute('name') || e.target.tagName, dp: null };
      // đọc defaultPrevented sau khi toàn bộ dispatch xong
      setTimeout(() => { rec.dp = e.defaultPrevented; window.__k14invalid.push(rec); }, 0);
    }, true);
  });
  const page = await ctx.newPage();
  const posts = [];
  page.on('request', (r) => { if (r.method() === 'POST' && r.url().includes('updatePost')) posts.push(r.url()); });
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]));

  await page.goto(BASE + '/atlas-pouf.html', { waitUntil: 'networkidle' });
  await page.click('#product-addtocart-button');
  await page.waitForTimeout(1500);
  await page.goto(BASE + '/checkout/cart/', { waitUntil: 'networkidle' });

  // Case A — empty
  await page.evaluate(() => { window.__k14invalid = []; });
  await page.locator('input[data-role="cart-item-qty"]').first().fill('');
  await page.locator('button[data-cart-item-update]').first().click();
  await page.waitForTimeout(600);
  const evA = await page.evaluate(() => window.__k14invalid);
  const msgA = await page.evaluate(
    () => document.querySelector('input[data-role="cart-item-qty"]').validationMessage
  );
  flag('A: đúng 1 invalid event/round', evA.length === 1, `count=${evA.length}`);
  flag('A: defaultPrevented=false → browser sẽ render bubble',
    evA.length === 1 && evA[0].dp === false, JSON.stringify(evA));
  flag('A: validationMessage VI', msgA === 'Vui lòng điền vào trường này.', `got="${msgA}"`);
  flag('A: submit bị chặn (0 POST updatePost)', posts.length === 0, `posts=${posts.length}`);

  // Case B — over max
  await page.evaluate(() => { window.__k14invalid = []; });
  await page.locator('input[data-role="cart-item-qty"]').first().fill('10001');
  await page.locator('button[data-cart-item-update]').first().click();
  await page.waitForTimeout(600);
  const evB = await page.evaluate(() => window.__k14invalid);
  const msgB = await page.evaluate(
    () => document.querySelector('input[data-role="cart-item-qty"]').validationMessage
  );
  flag('B: defaultPrevented=false + 1 event', evB.length === 1 && evB[0].dp === false, JSON.stringify(evB));
  flag('B: validationMessage VI + interpolate', msgB === 'Giá trị phải nhỏ hơn hoặc bằng 10000.', `got="${msgB}"`);
  flag('B: vẫn chưa có POST updatePost', posts.length === 0, `posts=${posts.length}`);

  // Case C — valid submit
  await page.locator('input[data-role="cart-item-qty"]').first().fill('2');
  await page.locator('button[data-cart-item-update]').first().click();
  await page.waitForTimeout(2500);
  await page.reload({ waitUntil: 'networkidle' });
  const qtyAfter = await page.evaluate(
    () => document.querySelector('input[data-role="cart-item-qty"]')?.value
  );
  flag('C: valid submit → qty=2 persist', qtyAfter === '2', `got="${qtyAfter}"`);
  flag('C: POST updatePost đã đi qua', posts.length === 1, `posts=${posts.length}`);

  flag('không có pageerror mới từ change set',
    !errors.some((e) => /secommCartQty|setCustomValidity/.test(e)),
    errors.map((e) => e.slice(0, 80)).join(' | ').slice(0, 300));

  await browser.close();
})();
