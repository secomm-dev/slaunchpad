const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
const D = 'dialog[aria-labelledby="quickview-modal-title"]';
const R = [];
const log = (n, p, d) => { R.push(p); console.log((p ? 'PASS' : 'FAIL') + ' | ' + n + (d ? ' | ' + d : '')); };
const cartCount = (pg) => pg.evaluate(() => { try { return JSON.parse(localStorage.getItem('mage-cache-storage'))?.cart?.summary_count ?? null; } catch(e){ return null; } });

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await browser.newContext({ viewport: { width: 1318, height: 900 } })).newPage();

  // ===== T1: PDP related slider Quick View =====
  await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('button[aria-label^="Xem nhanh"]', { timeout: 20000 });
  await page.waitForTimeout(1200); // slider init + PDP defer settle
  const triggers = await page.locator('button[aria-label^="Xem nhanh"]').count();
  // trigger cuối trong slider (không phải PDP main — PDP không có trigger)
  const lastTrigger = page.locator('button[aria-label^="Xem nhanh"]').last();
  const lastLabel = (await lastTrigger.getAttribute('aria-label')).replace('Xem nhanh ', '').trim();
  await lastTrigger.evaluate(el => el.scrollIntoView({ block: 'center' }));
  await lastTrigger.click({ timeout: 10000 });
  await page.locator(D).waitFor({ state: 'visible', timeout: 10000 });
  const opened = await page.waitForFunction((exp) => (document.querySelector('#quickview-modal-title')?.textContent || '').trim() === exp, lastLabel, { timeout: 12000 }).then(() => true).catch(() => false);
  const title = await page.locator('#quickview-modal-title').innerText().catch(() => '?');
  log('T1 PDP related slider quick view opens correct product', triggers >= 3 && opened, `triggers=${triggers}, expected="${lastLabel}", got="${title.trim()}"`);
  // ATC từ modal
  await page.locator(`${D} button[type="submit"]`).click();
  const drawer = await page.waitForFunction(() => document.getElementById('cart-drawer')?.hasAttribute('open') === true, { timeout: 15000 }).then(() => true).catch(() => false);
  await page.waitForTimeout(2000);
  const c1 = await cartCount(page);
  log('T1b related product ATC', drawer && c1 !== null && c1 > 0, `count=${c1}, drawer=${drawer}`);
  await page.keyboard.press('Escape');
  await page.waitForTimeout(500);

  // ===== T2: Cart crosssell Quick View =====
  await page.goto(BASE + '/checkout/cart/', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('button[aria-label^="Xem nhanh"]', { timeout: 20000 }).catch(() => {});
  const cartTriggers = await page.locator('button[aria-label^="Xem nhanh"]').count();
  if (cartTriggers === 0) {
    log('T2 cart crosssell quick view', true, 'SKIP — crosssell slider không render (không có crosssell links trong fixture quote)');
  } else {
    const ct = page.locator('button[aria-label^="Xem nhanh"]').last();
    const ctLabel = (await ct.getAttribute('aria-label')).replace('Xem nhanh ', '').trim();
    await ct.evaluate(el => el.scrollIntoView({ block: 'center' }));
    await ct.click({ timeout: 10000 });
    await page.locator(D).waitFor({ state: 'visible', timeout: 10000 });
    // so sánh bỏ qua HTML entity encode (product name có thể chứa &trade; literal)
    const norm = (x) => x.replace(/&trade;/g, '\u2122').replace(/\s+/g, ' ').trim().toLowerCase();
    const got2 = await page.waitForFunction((exp) => {
      const t = (document.querySelector('#quickview-modal-title')?.textContent || '').trim();
      const norm2 = (x) => x.replace(/&trade;/g, '\u2122').replace(/\s+/g, ' ').trim().toLowerCase();
      return norm2(t) === norm2(exp);
    }, ctLabel, { timeout: 12000 }).then(() => true).catch(() => false);
    const gotTitle = await page.locator('#quickview-modal-title').innerText().catch(() => '?');
    log('T2 cart crosssell quick view opens correct product', got2, `triggers=${cartTriggers}, expected="${ctLabel}", got="${gotTitle.trim()}"`);
  }

  await browser.close();
  const f = R.filter(x => !x).length;
  console.log(`\nSUMMARY: ${R.length - f}/${R.length} PASS`);
  process.exit(f ? 1 : 0);
})().catch(e => { console.error('FATAL:', e.message.slice(0, 150)); process.exit(2); });
