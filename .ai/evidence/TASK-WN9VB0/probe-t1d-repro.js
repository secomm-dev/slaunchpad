const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  const consoleErrors = [];
  const posts = [];
  const responses = [];
  page.on('pageerror', e => consoleErrors.push('pageerror: ' + e.message.slice(0, 200)));
  page.on('console', m => { if (m.type() === 'error' || m.type() === 'warning') consoleErrors.push(m.type() + ': ' + m.text().slice(0, 200)); });
  page.on('response', async r => {
    if (r.url().includes('checkout/cart/add')) {
      let body = '';
      try { body = (await r.text()).slice(0, 300); } catch (e) { body = '<no body>'; }
      posts.push(`POST ${r.status()} body=${body}`);
    }
  });
  page.on('request', r => { if (r.url().includes('checkout/cart/add')) responses.push('REQ ' + r.url().slice(0, 120) + ' body=' + (r.postData() || '').slice(0, 200)); });

  // exact qv-verify openQuickView
  const dialog = page.locator('dialog[aria-labelledby="quickview-modal-title"]');
  await page.goto(BASE + '/dining.html', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('button[aria-label^="Xem nhanh"]', { timeout: 20000 });
  const labelPrefix = 'Xem nhanh Linje Table Runner';
  const trigger = page.locator(`button[aria-label^="${labelPrefix}"]`).first();
  await trigger.scrollIntoViewIfNeeded();
  await page.waitForFunction((sel) => {
    const b = document.querySelector(`button[aria-label^="${sel}"]`);
    return b && Object.keys(b).some(k => k.startsWith('_x_'));
  }, labelPrefix, { timeout: 10000 }).catch(() => {});
  await trigger.click();
  await dialog.waitFor({ state: 'visible', timeout: 10000 });

  // exact T1a/T1b/T1c reads
  const modalTitle = (await page.locator('#quickview-modal-title').innerText()).trim();
  console.log('T1a title=', JSON.stringify(modalTitle));
  const priceText = (await dialog.locator('.text-xl.font-semibold').first().innerText()).trim();
  console.log('T1b price=', JSON.stringify(priceText));
  const galImgs = await dialog.locator('img:visible').count();
  console.log('T1c imgs=', galImgs);

  await page.evaluate(() => { window.__qv_noreload = 'alive'; });
  await dialog.locator('button[type="submit"]').click();
  await page.waitForTimeout(12000);

  const st = await page.evaluate(() => {
    const root = document.querySelector('[x-data*="initQuickView"]');
    const d = window.Alpine?.$data(root);
    const stP = document.querySelector('dialog p[role="status"]');
    const alP = document.querySelector('dialog p[role="alert"]');
    const qe = document.getElementById('quickview-qty-error');
    return {
      status: d?.status, msg: (d?.statusMessage || ''), qtyError: d?.qtyError || (qe?.textContent || ''),
      qty: d?.qty, submitting: d?.submitting, statusVisible: stP ? getComputedStyle(stP).display !== 'none' : null,
      alertText: alP && getComputedStyle(alP).display !== 'none' ? alP.textContent.trim().slice(0, 120) : null,
      cart: (() => { try { return JSON.parse(localStorage.getItem('mage-cache-storage'))?.cart?.summary_count ?? null; } catch (e) { return null; } })(),
      navAlive: window.__qv_noreload,
      url: location.pathname
    };
  });
  console.log('state-after:', JSON.stringify(st, null, 1));
  console.log('requests:', JSON.stringify(responses, null, 1));
  console.log('responses:', JSON.stringify(posts, null, 1));
  console.log('consoleErrors:', JSON.stringify(consoleErrors, null, 1));
  await page.screenshot({ path: '/var/www/projects/slaunchpad/.ai/evidence/TASK-WN9VB0/probe-t1d-state.png' });
  await browser.close();
})().catch(e => { console.error('PROBE FATAL:', e.message.slice(0, 300)); process.exit(2); });
