const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
const R = [];
const log = (n, p, d) => { R.push(p); console.log((p ? 'PASS' : 'FAIL') + ' | ' + n + (d ? ' | ' + d : '')); };
const cartCount = (pg) => pg.evaluate(() => { try { return JSON.parse(localStorage.getItem('mage-cache-storage'))?.cart?.summary_count ?? null; } catch(e) { return null; } });

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  const consoleErrors = [];
  let monsoonSuccessFired = 0;
  page.on('pageerror', e => consoleErrors.push('pageerror: ' + e.message.slice(0, 100)));
  page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text().slice(0, 100)); });
  page.addInitScript(() => {
    window.addEventListener('product-addtocart-success', () => { window.__monsoonSuccess = (window.__monsoonSuccess || 0) + 1; });
  });

  await page.goto(BASE + '/dining.html', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('button[aria-label^="Xem nhanh"]', { timeout: 20000 });

  // ===== T1: Card ATC qua Monsoon (AJAX, no navigation) =====
  const c0 = await cartCount(page);
  const cardBtn = page.locator('button[aria-label^="Thêm vào giỏ Linje Table Runner"]').first();
  await cardBtn.scrollIntoViewIfNeeded();
  await page.evaluate(() => { window.__nav_flag = 'alive'; });
  await cardBtn.click();
  await page.waitForTimeout(2500); // monsoon delay 1000ms
  const c1 = await cartCount(page);
  const noNav = await page.evaluate(() => window.__nav_flag === 'alive');
  const monsoonFired = await page.evaluate(() => window.__monsoonSuccess || 0);
  log('T1 card ATC via Monsoon AJAX (no nav, +1, success event)', noNav && c1 !== null && c1 > (c0 ?? 0) && monsoonFired >= 1,
    `count ${c0}→${c1}, navAlive=${noNav}, successEvt=${monsoonFired}`);
  // drawer mở theo config ajax_cart_open_after_add_to_cart=1 — đóng để test tiếp
  const drawer1 = await page.evaluate(() => document.getElementById('cart-drawer')?.hasAttribute('open') ?? false);
  await page.keyboard.press('Escape');
  await page.waitForTimeout(500);

  // ===== T2: Quick View modal ATC khi Monsoon active — KHÔNG double-add =====
  const dialog = page.locator('dialog[aria-labelledby="quickview-modal-title"]');
  const trigger = page.locator('button[aria-label^="Xem nhanh Ovale Dining Table"]').first();
  await trigger.scrollIntoViewIfNeeded();
  await trigger.click();
  await dialog.waitFor({ state: 'visible', timeout: 10000 });
  await page.waitForFunction(() => document.querySelectorAll('dialog select').length > 0, { timeout: 12000 }).catch(() => {});
  // chọn option đầu khả dụng
  const sels = dialog.locator('select');
  const n = await sels.count();
  for (let i = 0; i < n; i++) {
    const s = sels.nth(i); const opts = s.locator('option'); const cnt = await opts.count();
    for (let j = 1; j < cnt; j++) { if ((await opts.nth(j).getAttribute('disabled')) === null) { await s.selectOption({ index: j }); break; } }
  }
  await page.evaluate(() => { window.__monsoonSuccess = 0; });
  await dialog.locator('button[type="submit"]').click();
  // ajax_cart_open_after_add_to_cart=1 → modal đóng + drawer mở (handover)
  const modalClosed = await page.waitForFunction(() => document.querySelector('dialog[aria-labelledby="quickview-modal-title"]')?.hasAttribute('open') === false, { timeout: 10000 }).then(() => true).catch(() => false);
  await page.waitForFunction(() => document.getElementById('cart-drawer')?.hasAttribute('open') === true, { timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(2000); // section reload
  const c2 = await cartCount(page);
  const monsoonInterceptedModal = await page.evaluate(() => window.__monsoonSuccess || 0);
  const drawer2 = await page.evaluate(() => document.getElementById('cart-drawer')?.hasAttribute('open') ?? false);
  log('T2 Quick View ATC (+1, no double, hands over to drawer)', modalClosed && c2 === c1 + 1 && monsoonInterceptedModal === 0 && drawer2 === true,
    `count ${c1}→${c2} (expected ${c1 + 1}), monsoonEvt=${monsoonInterceptedModal}, modalClosed=${modalClosed}, drawerOpen=${drawer2}`);
  await page.keyboard.press('Escape');
  await page.waitForTimeout(500);

  // ===== T3: PDP ATC qua Monsoon (AJAX, fresh page) =====
  const pdpCtx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const pdpPage = await pdpCtx.newPage();
  pdpPage.addInitScript(() => {
    window.addEventListener('product-addtocart-success', () => { window.__monsoonSuccess = (window.__monsoonSuccess || 0) + 1; });
  });
  await pdpPage.goto(BASE + '/linje-table-runner.html', { waitUntil: 'domcontentloaded' });
  await pdpPage.waitForTimeout(2500); // PDP form hiện sau khi Alpine/defer init xong
  await pdpPage.waitForSelector('#product_addtocart_form', { timeout: 20000 });
  const c3 = await cartCount(pdpPage);
  await pdpPage.evaluate(() => { window.__nav_flag = 'alive'; });
  await pdpPage.locator('#product_addtocart_form button[type="submit"], #product_addtocart_form button:not([type])').first().click();
  await pdpPage.waitForTimeout(2500);
  const c4 = await cartCount(pdpPage);
  const noNavPdp = await pdpPage.evaluate(() => window.__nav_flag === 'alive');
  log('T3 PDP ATC via Monsoon AJAX (no nav, +1)', noNavPdp && c4 !== null && c4 > (c3 ?? 0), `count ${c3}→${c4}, navAlive=${noNavPdp}`);
  await pdpCtx.close();

  // ===== T4: console =====
  const real = consoleErrors.filter(e => !/ExtraFee|mpextrafee/i.test(e));
  log('T4 console clean (non-ambient)', real.length === 0, real.slice(0, 2).join(' || ') || 'clean');

  await browser.close();
  const failed = R.filter(x => !x).length;
  console.log(`\nSUMMARY: ${R.length - failed}/${R.length} PASS`);
  process.exit(failed ? 1 : 0);
})().catch(e => { console.error('FATAL:', e.message); process.exit(2); });
