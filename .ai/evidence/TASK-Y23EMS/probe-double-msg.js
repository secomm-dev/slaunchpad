// SLP-264 double success message — ATC liên tiếp không được stack message
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';
const visibleMsgs = page => page.$$eval('#messages .message', els => els.filter(e => e.getBoundingClientRect().height > 0).map(e => e.textContent.trim().replace(/\s+/g, ' ').slice(0, 50)));
const cartQty = page => page.evaluate(() => (JSON.parse(localStorage.getItem('mage-cache-storage') || '{}')?.cart?.summary_count) ?? 0);
const drawerOpen = page => page.evaluate(() => { const d = document.querySelector('#cart-drawer'); try { return !!Alpine.$data(d).open; } catch (e) { return null; } });
const closeDrawer = page => page.evaluate(() => window.dispatchEvent(new CustomEvent('toggle-cart', { detail: { isOpen: false } })));

(async () => {
  const browser = await chromium.launch({ executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome', args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  let posts = 0;
  page.on('request', r => { if (r.method() === 'POST' && r.url().includes('checkout/cart/add')) posts++; });
  const out = {};

  async function repeat(label, n, act) {
    const rows = [];
    for (let i = 1; i <= n; i++) {
      const p0 = posts, q0 = await cartQty(page);
      await act(i);
      await page.waitForTimeout(3500);
      rows.push({ click: i, posts: posts - p0, qty: `${q0}->${await cartQty(page)}`, drawer: await drawerOpen(page), msgs: await visibleMsgs(page) });
      await closeDrawer(page); await page.waitForTimeout(400);
    }
    out[label] = rows;
  }

  // T1 PDP simple ×3
  await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'networkidle' });
  await repeat('T1_pdp_simple_x3', 3, () => page.click('#product-addtocart-button'));
  await page.screenshot({ path: 'after-pdp-x3.png' });

  // T2 PDP configurable ×2
  await page.goto(BASE + '/chaz-kangeroo-hoodie.html', { waitUntil: 'networkidle' });
  const groups = await page.$$eval('#product_addtocart_form input[type="radio"][name^="super_attribute"]', els => [...new Set(els.map(e => e.name))]);
  for (const g of groups) { const id = await page.$eval(`#product_addtocart_form input[type="radio"][name="${g}"]`, e => e.id); await page.click(`label[for="${id}"]`); }
  await repeat('T2_pdp_configurable_x2', 2, () => page.click('#product-addtocart-button'));

  // T3 PDP configurable thiếu option — validation inline, 0 POST, không đụng message
  await page.goto(BASE + '/chaz-kangeroo-hoodie.html', { waitUntil: 'networkidle' });
  const p3 = posts; await page.click('#product-addtocart-button'); await page.waitForTimeout(1200);
  out.T3_config_missing_option = { posts: posts - p3, fieldError: await page.evaluate(() => !!document.querySelector('.field-error')) };

  // T4 PLP card ×2 (2 sản phẩm khác nhau)
  await page.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  await repeat('T4_plp_card_x2', 2, i => page.evaluate(i => document.querySelectorAll('.product_addtocart_form')[i - 1].requestSubmit(), i));

  // T5 Quick View ×2
  await page.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  await repeat('T5_quickview_x2', 2, async () => {
    await page.evaluate(() => document.querySelector('.hp-card-quickview-btn').click());
    await page.waitForSelector('dialog[open] button[type="submit"]:not([disabled])', { timeout: 10000 });
    await page.click('dialog[open] button[type="submit"]');
  });

  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
