const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  let successEvt = 0;
  page.addInitScript(() => { window.addEventListener('product-addtocart-success', () => { window.__ms = (window.__ms || 0) + 1; }); });
  await page.goto('http://slaunchpad.localhost/linje-table-runner.html', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);
  await page.waitForSelector('#product_addtocart_form', { state: 'attached', timeout: 20000 });
  const formVisible = await page.evaluate(() => getComputedStyle(document.querySelector('#product_addtocart_form')).display !== 'none');
  console.log('form visible:', formVisible);
  const posts = [];
  page.on('response', r => { if (r.url().includes('checkout/cart/add')) posts.push(r.url().slice(0, 60) + ' -> ' + r.status()); });
  await page.evaluate(() => { window.__nav = 'alive'; });
  await page.locator('button[form="product_addtocart_form"]').first().click();
  await page.waitForTimeout(2500);
  const c = await page.evaluate(() => { try { return JSON.parse(localStorage.getItem('mage-cache-storage'))?.cart?.summary_count ?? null; } catch(e){ return null; } });
  const nav = await page.evaluate(() => window.__nav === 'alive');
  successEvt = await page.evaluate(() => window.__ms || 0);
  console.log('POSTs:', JSON.stringify(posts));
  const singleAdd = c === 1; // context mới, cart trống: đúng 1 item, KHÔNG double
  console.log((singleAdd ? 'PASS' : 'FAIL') + ` | T3 PDP ATC (native POST — selectors đã loại PDP khỏi Monsoon) | count=${c} (single=${singleAdd}), posts=${posts.length}`);
  await browser.close();
  process.exit(nav && c !== null && c > 0 && successEvt >= 1 ? 0 : 1);
})().catch(e => { console.error('FATAL:', e.message.slice(0, 150)); process.exit(2); });
