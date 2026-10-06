const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });

  // ===== Probe A: qv-verify T1d repro — Linje simple modal ATC =====
  {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    const consoleErrors = [];
    const posts = [];
    page.on('pageerror', e => consoleErrors.push('pageerror: ' + e.message.slice(0, 120)));
    page.on('console', m => { if (m.type() === 'error') consoleErrors.push('console: ' + m.text().slice(0, 120)); });
    page.on('response', async r => {
      if (r.url().includes('checkout/cart/add')) {
        let body = '';
        try { body = (await r.text()).slice(0, 200); } catch (e) { body = '<no body>'; }
        posts.push(`POST ${r.url().slice(-30)} -> ${r.status()} body=${body}`);
      }
    });
    await page.goto(BASE + '/dining.html', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('button[aria-label^="Xem nhanh Linje"]', { timeout: 20000 });
    await page.locator('button[aria-label^="Xem nhanh Linje"]').first().click();
    const D = 'dialog[aria-labelledby="quickview-modal-title"]';
    await page.locator(D).waitFor({ state: 'visible', timeout: 10000 });
    await page.waitForFunction(() => (document.querySelector('#quickview-modal-title')?.textContent || '').trim().length > 0, { timeout: 12000 });

    const pre = await page.evaluate(() => {
      const root = document.querySelector('[x-data*="initQuickView"]');
      const d = window.Alpine?.$data(root);
      const btn = document.querySelector('dialog button[type="submit"]');
      const hidden = document.querySelector('dialog input[name="product"]');
      return {
        productLoaded: !!d?.product, productId: d?.product?.id, inStock: d?.inStock,
        qty: d?.qty, submitting: d?.submitting, status: d?.status, missingSelection: d?.missingSelection,
        btnDisabled: btn?.disabled, hiddenProductValue: hidden?.value, hiddenHasAttr: hidden?.hasAttribute('value')
      };
    });
    console.log('A pre-click state:', JSON.stringify(pre));

    await page.evaluate(() => { window.__flag = 'alive'; });
    await page.locator(`${D} button[type="submit"]`).click();
    await page.waitForTimeout(5000);
    const post = await page.evaluate(() => {
      const root = document.querySelector('[x-data*="initQuickView"]');
      const d = window.Alpine?.$data(root);
      const st = document.querySelector('dialog p[role="status"]');
      const al = document.querySelector('dialog p[role="alert"]');
      return {
        status: d?.status, msg: (d?.statusMessage || '').slice(0, 80), submitting: d?.submitting,
        statusVisible: st ? getComputedStyle(st).display !== 'none' : null,
        alertVisible: al ? getComputedStyle(al).display !== 'none' : null,
        cartCount: (() => { try { return JSON.parse(localStorage.getItem('mage-cache-storage'))?.cart?.summary_count ?? null; } catch (e) { return null; } })(),
        navAlive: window.__flag
      };
    });
    console.log('A post-click state:', JSON.stringify(post));
    console.log('A posts:', JSON.stringify(posts, null, 1));
    console.log('A consoleErrors:', JSON.stringify(consoleErrors.slice(0, 5)));
    await ctx.close();
  }

  // ===== Probe B: qv-v2 T-C repro — sofa-meridian swatch inventory =====
  {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    await page.goto(BASE + '/living-room.html', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('button[aria-label^="Xem nhanh"]', { timeout: 20000 });
    await page.locator('button[aria-label^="Xem nhanh Meridian"]').first().click();
    const D = 'dialog[aria-labelledby="quickview-modal-title"]';
    await page.locator(D).waitFor({ state: 'visible', timeout: 10000 });
    await page.waitForFunction(() => (document.querySelector('#quickview-modal-title')?.textContent || '').trim().length > 0, { timeout: 12000 });
    await page.waitForTimeout(800);
    const inv = await page.evaluate(() => {
      const labels = [...document.querySelectorAll('dialog label.swatch-option')];
      return labels.map(l => ({
        type: l.getAttribute('data-swatch-type'),
        inputType: l.querySelector('input')?.type,
        cls: (l.className || '').slice(0, 100),
        spanStyle: (l.querySelector('span')?.getAttribute('style') || '').slice(0, 80)
      }));
    });
    console.log('B swatch inventory:', JSON.stringify(inv, null, 1));
    const counts = await page.evaluate(() => {
      const dlg = document.querySelector('dialog');
      const c = s => dlg.querySelectorAll(s).length;
      return {
        color: c('label.swatch-option[data-swatch-type="color"]'),
        text: c('label.swatch-option[data-swatch-type="text"]'),
        visual: c('label.swatch-option[data-swatch-type="visual"]'),
        anySwatch: c('label.swatch-option')
      };
    });
    console.log('B counts:', JSON.stringify(counts));
    await ctx.close();
  }

  // ===== Probe C: qv-compat T3 repro — PDP form visibility, 2 rounds =====
  for (let round = 1; round <= 2; round++) {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    const cerrs = [];
    page.on('pageerror', e => cerrs.push('pageerror: ' + e.message.slice(0, 120)));
    page.on('console', m => { if (m.type() === 'error') cerrs.push('console: ' + m.text().slice(0, 120)); });
    await page.goto(BASE + '/linje-table-runner.html', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);
    const at2500 = await page.evaluate(() => {
      const f = document.querySelector('#product_addtocart_form');
      if (!f) return 'missing';
      const cs = getComputedStyle(f);
      return `display=${cs.display}, visibility=${cs.visibility}, hasXCloak=${f.hasAttribute('x-cloak')}, alpineDone=${document.documentElement.hasAttribute('x-data') && !!window.Alpine}`;
    });
    await page.waitForTimeout(4000);
    const at6500 = await page.evaluate(() => {
      const f = document.querySelector('#product_addtocart_form');
      if (!f) return 'missing';
      return `display=${getComputedStyle(f).display}`;
    });
    console.log(`C round${round}: at2500=${at2500} | at6500=${at6500} | errors=${JSON.stringify(cerrs.slice(0, 4))}`);
    await ctx.close();
  }

  await browser.close();
})().catch(e => { console.error('PROBE FATAL:', e.message.slice(0, 200)); process.exit(2); });
