// SLP-264 staging double-add — 1 click ATC trên PDP phải = 1 POST tới checkout/cart/add
// Usage: node probe-264-double-add.js <phase-label>   (pre-fix | post-fix)
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';
const PHASE = process.argv[2] || 'run';
const cartQty = page => page.evaluate(() => (JSON.parse(localStorage.getItem('mage-cache-storage') || '{}')?.cart?.summary_count) ?? 0);

(async () => {
  const browser = await chromium.launch({
    executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome',
    args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1']
  });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  const bodies = [];
  page.on('request', r => {
    if (r.method() === 'POST' && r.url().includes('checkout/cart/add')) bodies.push(r.postData() || '');
  });
  const out = { phase: PHASE, selectorConfig: null };

  async function oneClick(label, url, prep) {
    await page.goto(BASE + url, { waitUntil: 'networkidle' });
    // config đang effective trong page (đọc từ block Monsoon — selectors render vào default arg)
    if (!out.selectorConfig) {
      out.selectorConfig = await page.evaluate(() => {
        const s = window.setAjaxCart ? window.setAjaxCart.toString() : '';
        const m = s.match(/selectors = '([^']*)'/);
        return m ? m[1] : null;
      });
    }
    // đếm lời gọi ajaxSubmitCart (wrap property — cả 2 path đều lookup tại call-time)
    await page.evaluate(() => {
      window.__atcCalls = 0;
      const o = window.ajaxSubmitCart;
      if (o) window.ajaxSubmitCart = function (...a) { window.__atcCalls++; return o.apply(this, a); };
    });
    if (prep) await prep(page);
    const pid = await page.$eval('#product_addtocart_form input[name="product"]', e => e.value);
    const p0 = bodies.length, q0 = await cartQty(page), c0 = await page.evaluate(() => window.__atcCalls);
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(3500);
    const myPosts = bodies.slice(p0).filter(b => b.includes(`product=${pid}&`) || b.endsWith(`product=${pid}`));
    out[label] = {
      postsThisProduct: myPosts.length,
      postsTotal: bodies.length - p0,
      atcCalls: (await page.evaluate(() => window.__atcCalls)) - c0,
      qty: `${q0}->${await cartQty(page)}`
    };
  }

  const selectAllOptions = async page => {
    const groups = await page.$$eval('#product_addtocart_form input[type="radio"][name^="super_attribute"]', els => [...new Set(els.map(e => e.name))]);
    for (const g of groups) {
      const id = await page.$eval(`#product_addtocart_form input[type="radio"][name="${g}"]`, e => e.id);
      await page.click(`label[for="${id}"]`);
    }
    return groups.length;
  };

  await oneClick('T1_pdp_simple_1click', '/joust-duffle-bag.html', null);
  out.T1_optionsSelected = 'n/a';
  await oneClick('T2_pdp_configurable_1click', '/chaz-kangeroo-hoodie.html', async page => {
    out.T2_optionGroups = await selectAllOptions(page);
  });

  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
