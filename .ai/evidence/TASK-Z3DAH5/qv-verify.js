const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
const results = [];
function log(name, pass, detail) {
  results.push({ name, pass, detail });
  console.log((pass ? 'PASS' : 'FAIL') + ' | ' + name + (detail ? ' | ' + detail : ''));
}

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });

  const openQuickView = async (pg, dlg, labelPrefix) => {
    const trigger = pg.locator(`button[aria-label^="${labelPrefix}"]`).first();
    await trigger.scrollIntoViewIfNeeded();
    await pg.waitForFunction((sel) => {
      const b = document.querySelector(`button[aria-label^="${sel}"]`);
      return b && Object.keys(b).some(k => k.startsWith('_x_'));
    }, labelPrefix, { timeout: 10000 }).catch(() => {});
    await trigger.click();
    await dlg.waitFor({ state: 'visible', timeout: 10000 });
  };

  const cartCount = (page) => page.evaluate(() => {
    try { return JSON.parse(localStorage.getItem('mage-cache-storage'))?.cart?.summary_count ?? null; }
    catch (e) { return null; }
  });

  // ---------- VI DESKTOP ----------
  const viCtx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await viCtx.newPage();
  const consoleErrors = [];
  const graphqlPayloads = [];
  page.on('pageerror', e => consoleErrors.push('pageerror: ' + e.message));
  page.on('console', m => { if (m.type() === 'error') consoleErrors.push('console: ' + m.text()); });
  page.on('response', async r => {
    if (r.url().includes('/graphql')) {
      try { const body = await r.request().postDataJSON(); graphqlPayloads.push(body?.query || ''); } catch (e) {}
    }
  });

  await page.goto(BASE + '/dining.html', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('button[aria-label^="Xem nhanh"]', { timeout: 20000 });
  const triggers = await page.locator('button[aria-label^="Xem nhanh"]').count();
  log('T0 trigger cards vi', triggers >= 5, 'count=' + triggers);

  const before = await cartCount(page);

  // ===== T1 simple product modal + ATC =====
  const dialog = page.locator('dialog[aria-labelledby="quickview-modal-title"]');
  await openQuickView(page, dialog, 'Xem nhanh Linje Table Runner');
  const loaded = await page.waitForFunction(() => (document.querySelector('#quickview-modal-title')?.textContent || '').trim().length > 0, { timeout: 12000 }).catch(() => null);
  if (!loaded) {
    const err = await page.evaluate(() => window.Alpine.$data(document.querySelector('[x-data*="initQuickView"]')).loadError);
    console.log('FAIL | T1-load | loadError=' + err);
    process.exit(1);
  }
  const modalTitle = (await page.locator('#quickview-modal-title').innerText()).trim();
  log('T1a modal open + name', modalTitle === 'Linje Table Runner', 'title=' + modalTitle);
  const priceText = (await dialog.locator('.text-xl.font-semibold').first().innerText()).trim();
  log('T1b price rendered', /\d/.test(priceText), 'price=' + priceText);
  const galImgs = await dialog.locator('img:visible').count();
  log('T1c gallery imgs', galImgs >= 1, 'imgs=' + galImgs);

  await page.evaluate(() => { window.__qv_noreload = 'alive'; });
  await dialog.locator('button[type="submit"]').click();
  await dialog.locator('p[role="status"]').waitFor({ state: 'visible', timeout: 10000 });
  const successText = await dialog.locator('p[role="status"]').innerText();
  log('T1d ATC success message', /Bạn đã thêm Linje Table Runner vào giỏ hàng/.test(successText), successText.trim());
  const flagStill = await page.evaluate(() => window.__qv_noreload);
  log('T1e no reload AC-001', flagStill === 'alive' && page.url().indexOf('/dining.html') > -1, 'flag=' + flagStill);
  await page.waitForTimeout(2000); // section reload
  const after1 = await cartCount(page);
  log('T1f minicart count AC-003', after1 !== null && after1 > (before ?? 0), 'before=' + before + ' after=' + after1);

  await dialog.locator('button[aria-label="Đóng"]').click();
  await dialog.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
  const closedByX = await dialog.evaluate(el => !el.open).catch(() => false);
  log('T1g close button', closedByX, 'open=' + !closedByX);

  // ===== T2 configurable =====
  const countBeforeCfg = await cartCount(page);
  await openQuickView(page, dialog, 'Xem nhanh Ovale Dining Table');
  await page.waitForFunction(() => document.querySelectorAll('dialog[aria-labelledby="quickview-modal-title"] select').length > 0, { timeout: 12000 }).catch(() => {});
  const selects = dialog.locator('select');
  const selCount = await selects.count();
  log('T2a configurable selects', selCount >= 1, 'selects=' + selCount);

  await dialog.locator('button[type="submit"]').click();
  const alertText = await dialog.locator('p[role="alert"]').innerText();
  log('T2b missing-selection guard AC-002', /Bạn cần chọn tùy chọn cho sản phẩm/.test(alertText), alertText.trim());
  const countNoChange = await cartCount(page);
  log('T2c cart unchanged after guard', countNoChange === countBeforeCfg, 'count=' + countNoChange);

  const priceBefore = (await dialog.locator('.text-xl.font-semibold').first().innerText()).trim();
  for (let i = 0; i < selCount; i++) {
    const s = selects.nth(i);
    if (i === 0) {
      const disabledCount = await s.locator('option[disabled]').count();
      log('T2d oos option disabling (info)', true, 'disabledOptions=' + disabledCount);
    }
    const options = s.locator('option');
    const n = await options.count();
    let picked = false;
    for (let j = 1; j < n; j++) {
      const dis = await options.nth(j).getAttribute('disabled');
      if (dis === null) { await s.selectOption({ index: j }); picked = true; break; }
    }
    if (!picked) await s.selectOption({ index: 1 });
    await page.waitForTimeout(200);
  }
  await page.waitForTimeout(400);
  const priceAfter = (await dialog.locator('.text-xl.font-semibold').first().innerText()).trim();
  log('T2e price after selection', /\d/.test(priceAfter), 'before=' + priceBefore + ' after=' + priceAfter);
  await dialog.locator('button[type="submit"]').click();
  await dialog.locator('p[role="status"]').waitFor({ state: 'visible', timeout: 10000 });
  await page.waitForTimeout(2000);
  const afterCfg = await cartCount(page);
  log('T2f configurable ATC AC-003', afterCfg !== null && afterCfg > (countNoChange ?? 0), 'after=' + afterCfg);

  // ===== T3 Esc + focus (a11y) =====
  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);
  const escClosed = await dialog.evaluate(el => !el.open);
  log('T3a Esc closes dialog AC-004', escClosed, 'open=' + !escClosed);
  await openQuickView(page, dialog, 'Xem nhanh Linje Table Runner');
  const focusInside = await dialog.evaluate(el => el.contains(document.activeElement));
  log('T3b focus moves into dialog', focusInside, 'active=' + focusInside);
  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);

  log('T3c graphql products query fired', graphqlPayloads.some(q => q.includes('configurable_options')), 'queries=' + graphqlPayloads.length);

  // console errors — ambient ExtraFee được lọc
  const ambient = consoleErrors.filter(e => /ExtraFee|mpextrafee|extrafee/i.test(e));
  const realErrors = consoleErrors.filter(e => !/ExtraFee|mpextrafee|extrafee/i.test(e));
  log('T3d console errors (non-ambient)', realErrors.length === 0, realErrors.slice(0, 3).join(' || ') || 'clean (ambient ExtraFee filtered: ' + ambient.length + ')');

  await viCtx.close();

  // ---------- MOBILE 375 ----------
  const mobCtx = await browser.newContext({ viewport: { width: 375, height: 720 } });
  const mp = await mobCtx.newPage();
  await mp.goto(BASE + '/dining.html', { waitUntil: 'domcontentloaded' });
  await mp.waitForSelector('button[aria-label^="Xem nhanh"]', { timeout: 20000 });
  await openQuickView(mp, mp.locator('dialog[aria-labelledby="quickview-modal-title"]'), 'Xem nhanh Linje Table Runner');
  await mp.waitForFunction(() => (document.querySelector('#quickview-modal-title')?.textContent || '').trim().length > 0, { timeout: 12000 }).catch(() => null);
  const mobScroll = await mp.evaluate(() => document.documentElement.scrollWidth);
  const dialBox = await mp.locator('dialog[aria-labelledby="quickview-modal-title"]').boundingBox();
  log('T4 mobile 375 no h-scroll AC-004', mobScroll <= 376, 'scrollWidth=' + mobScroll);
  log('T4b mobile dialog fits', dialBox && dialBox.x >= 0 && dialBox.x + dialBox.width <= 376, JSON.stringify(dialBox));
  await mobCtx.close();

  // ---------- EN STORE ----------
  const enCtx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const ep = await enCtx.newPage();
  await ep.goto(BASE + '/dining.html?___store=launchpad_en', { waitUntil: 'domcontentloaded' });
  await ep.waitForSelector('button[aria-label^="Quick view"]', { timeout: 20000 });
  const enTriggers = await ep.locator('button[aria-label^="Quick view"]').count();
  log('T5a en trigger labels', enTriggers >= 5, 'count=' + enTriggers);
  const enDialog = ep.locator('dialog[aria-labelledby="quickview-modal-title"]');
  await openQuickView(ep, enDialog, 'Quick view Linje Table Runner');
  await ep.waitForFunction(() => (document.querySelector('#quickview-modal-title')?.textContent || '').trim().length > 0, { timeout: 12000 }).catch(() => null);
  await ep.evaluate(() => { window.__qv_en_alive = 'alive'; });
  const enSubmit = (await enDialog.locator('button[type="submit"]').innerText()).trim();
  log('T5b en modal labels identity', /Add to Cart/.test(enSubmit), 'submit=' + enSubmit);
  await enDialog.locator('button[type="submit"]').click();
  await enDialog.locator('p[role="status"]').waitFor({ state: 'visible', timeout: 10000 });
  const enSuccess = await enDialog.locator('p[role="status"]').innerText();
  const enAlive = await ep.evaluate(() => window.__qv_en_alive);
  log('T5c en ATC no reload + identity', enAlive === 'alive' && /You added Linje Table Runner to your shopping cart\./.test(enSuccess), enSuccess.trim());
  await enCtx.close();

  // ---------- REGRESSION: card ATC form POST (full page) ----------
  const rgCtx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const rp = await rgCtx.newPage();
  await rp.goto(BASE + '/dining.html', { waitUntil: 'domcontentloaded' });
  await rp.waitForSelector('button[aria-label^="Thêm vào giỏ Linje Table Runner"]', { timeout: 20000 });
  const rgBefore = await cartCount(rp);
  await rp.locator('button[aria-label^="Thêm vào giỏ Linje Table Runner"]').first().click();
  await rp.waitForLoadState('load');
  await rp.waitForTimeout(2500);
  const rgAfter = await cartCount(rp);
  log('T6 card ATC form POST regression', rgAfter !== null && rgAfter > (rgBefore ?? 0), 'before=' + rgBefore + ' after=' + rgAfter + ' url=' + rp.url());
  await rgCtx.close();

  await browser.close();
  const failed = results.filter(r => !r.pass);
  console.log('\nSUMMARY: ' + (results.length - failed.length) + '/' + results.length + ' PASS');
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('FATAL: ' + e.message); process.exit(2); });
