// SLP-264 — verify riêng re-entrancy guard (lớp 2): mô phỏng double-bind như staging
// (thêm tay 1 listener gọi ajaxSubmitCart song song Alpine onSubmit) → phải vẫn 1 POST
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';
const cartQty = page => page.evaluate(() => (JSON.parse(localStorage.getItem('mage-cache-storage') || '{}')?.cart?.summary_count) ?? 0);

(async () => {
  const browser = await chromium.launch({
    executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome',
    args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1']
  });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  let posts = 0;
  page.on('request', r => { if (r.method() === 'POST' && r.url().includes('checkout/cart/add')) posts++; });

  await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'networkidle' });
  await page.evaluate(() => {
    window.__atcCalls = 0;
    const o = window.ajaxSubmitCart;
    window.ajaxSubmitCart = function (...a) { window.__atcCalls++; return o.apply(this, a); };
    // mô phỏng đúng listener mà setAjaxCart đã bind trên staging (pre-fix)
    document.getElementById('product_addtocart_form')
      .addEventListener('submit', e => { e.preventDefault(); window.ajaxSubmitCart(e.target); });
  });

  const q0 = await cartQty(page);
  await page.click('#product-addtocart-button');
  await page.waitForTimeout(3500);

  console.log(JSON.stringify({
    test: 'guard_vs_simulated_double_bind',
    posts: posts,
    atcCalls: await page.evaluate(() => window.__atcCalls),
    qty: `${q0}->${await cartQty(page)}`
  }, null, 1));
  await browser.close();
})();
