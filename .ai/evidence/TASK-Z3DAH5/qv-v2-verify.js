const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
const R = [];
const log = (n, p, d) => { R.push(p); console.log((p ? 'PASS' : 'FAIL') + ' | ' + n + (d ? ' | ' + d : '')); };
const cartCount = (pg) => pg.evaluate(() => { try { return JSON.parse(localStorage.getItem('mage-cache-storage'))?.cart?.summary_count ?? null; } catch(e) { return null; } });
const D = 'dialog[aria-labelledby="quickview-modal-title"]';

async function openQuickView(pg, label) {
  const t = pg.locator(`button[aria-label^="${label}"]`).first();
  await t.evaluate(el => el.scrollIntoView({ block: 'center' }));
  await t.click({ timeout: 10000 });
  await pg.locator(D).waitFor({ state: 'visible', timeout: 10000 });
  await pg.waitForFunction(() => (document.querySelector('#quickview-modal-title')?.textContent || '').trim().length > 0, { timeout: 12000 }).catch(() => null);
}

async function pickSwatches(pg) {
  // click swatch khả dụng từng fieldset (giống user bấm pill)
  const groups = pg.locator(`${D} fieldset`);
  const n = await groups.count();
  for (let i = 0; i < n; i++) {
    const swatches = groups.nth(i).locator('label.swatch-option');
    const cnt = await swatches.count();
    for (let j = 0; j < cnt; j++) {
      const sw = swatches.nth(j);
      const dis = await sw.evaluate(el => el.querySelector('input')?.disabled || el.classList.contains('opacity-40'));
      if (!dis) { await sw.click(); break; }
    }
    await pg.waitForTimeout(150);
  }
}

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  const errs = [];
  page.on('pageerror', e => errs.push(e.message.slice(0, 100)));
  page.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0, 100)); });

  // ===== T-A: Homepage widget Quick View =====
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('button[aria-label^="Xem nhanh"]', { timeout: 20000 });
  const triggers = await page.locator('button[aria-label^="Xem nhanh"]').count();
  const firstLabel = await page.locator('button[aria-label^="Xem nhanh"]').first().getAttribute('aria-label');
  await openQuickView(page, firstLabel);
  const hasSku = await page.locator(`${D} p:has-text("Mã SKU")`).count();
  const skuText = await page.locator(`${D} p span.font-medium`).first().innerText().catch(() => '');
  log('T-A homepage widget quick view opens + SKU row', triggers >= 5 && hasSku >= 1, `triggers=${triggers}, label="${firstLabel.slice(11, 40)}", sku=${skuText}`);
  const descVisible = await page.locator(`${D} [x-html*="description"], ${D} div[x-html]`).count();
  log('T-A2 description/More Information sections present', descVisible >= 1, `sections=${descVisible}`);
  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);

  // ===== T-B: Configurable text swatch (Ovale Dining Table) như PDP =====
  await page.goto(BASE + '/dining.html', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('button[aria-label^="Xem nhanh"]', { timeout: 20000 });
  const c0 = await cartCount(page);
  await openQuickView(page, 'Xem nhanh Ovale Dining Table');
  const swatchLabels = await page.locator(`${D} label.swatch-option`).count();
  const selectCount = await page.locator(`${D} select`).count();
  log('T-B1 swatch radio giống PDP (không select)', swatchLabels >= 2 && selectCount === 0, `swatches=${swatchLabels}, selects=${selectCount}`);
  const legendBefore = await page.locator(`${D} legend span.font-medium`).first().innerText();
  await pickSwatches(page);
  await page.waitForTimeout(400);
  const legendAfter = await page.locator(`${D} legend span.font-medium`).first().innerText();
  log('T-B2 legend shows selected label', legendBefore === '' && legendAfter.length > 0, `"${legendBefore}" → "${legendAfter}"`);
  await page.evaluate(() => { window.__nav = 'alive'; });
  await page.locator(`${D} button[type="submit"]`).click();
  const modalClosed = await page.waitForFunction(() => document.querySelector('dialog[aria-labelledby="quickview-modal-title"]')?.hasAttribute('open') === false, { timeout: 10000 }).then(() => true).catch(() => false);
  const drawer = await page.waitForFunction(() => document.getElementById('cart-drawer')?.hasAttribute('open') === true, { timeout: 10000 }).then(() => true).catch(() => false);
  await page.waitForTimeout(2000);
  const c1 = await cartCount(page);
  const navAlive = await page.evaluate(() => window.__nav === 'alive');
  const tb3state = await page.evaluate(() => {
    const d = window.Alpine.$data(document.querySelector('[x-data*="initQuickView"]'));
    return { isOpen: d?.isOpen, status: d?.status, msg: (d?.statusMessage || '').slice(0, 40), open: document.querySelector('dialog[aria-labelledby="quickview-modal-title"]')?.hasAttribute('open') };
  });
  log('T-B3 swatch ATC +1 handover drawer no reload', modalClosed && drawer && navAlive && c1 !== null && c1 > (c0 ?? 0), `count ${c0}→${c1}, drawer=${drawer}, modalClosed=${modalClosed}, navAlive=${navAlive}, state=${JSON.stringify(tb3state)}`);
  await page.keyboard.press('Escape');
  await page.waitForTimeout(500);

  // ===== T-C: Color + text swatch (sofa-meridian trên living-room) =====
  await page.goto(BASE + '/living-room.html', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('button[aria-label^="Xem nhanh"]', { timeout: 20000 });
  await openQuickView(page, 'Xem nhanh Meridian Modular Sofa');
  const colorSwatches = await page.locator(`${D} label.swatch-option[data-swatch-type="color"]`).count();
  const textSwatches = await page.locator(`${D} label.swatch-option[data-swatch-type="text"]`).count();
  const bgSample = await page.locator(`${D} label.swatch-option[data-swatch-type="color"] span`).first().getAttribute('style').catch(() => '');
  log('T-C swatch types color + text (PDP parity)', colorSwatches >= 3 && textSwatches >= 3, `color=${colorSwatches}, text=${textSwatches}, style="${bgSample}"`);
  await pickSwatches(page);
  await page.waitForTimeout(300);
  await page.locator(`${D} button[type="submit"]`).click();
  await page.waitForFunction(() => document.getElementById('cart-drawer')?.hasAttribute('open') === true, { timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(2000);
  const c2 = await cartCount(page);
  log('T-C2 color-swatch product ATC', c2 !== null && c2 > (c1 ?? 0), `count ${c1}→${c2}`);

  const real = errs.filter(e => !/ExtraFee|mpextrafee/i.test(e));
  log('T-D console clean (non-ambient)', real.length === 0, real.slice(0, 2).join(' || ') || 'clean');

  await browser.close();
  const f = R.filter(x => !x).length;
  console.log(`\nSUMMARY: ${R.length - f}/${R.length} PASS`);
  process.exit(f ? 1 : 0);
})().catch(e => { console.error('FATAL:', e.message.slice(0, 150)); process.exit(2); });
