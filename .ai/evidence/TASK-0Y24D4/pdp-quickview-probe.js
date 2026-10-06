/**
 * TASK-0Y24D4 (SLP-307) — OOS option states trên PDP + Quick View (scope mở rộng).
 * Cùng design Figma 3286-150414, nguồn chung components/swatches.css:
 *  - text OOS: bg #f3f4f6, border 2px #d1d5dc, label #99a1af @.75, strike 2px
 *  - visual OOS: opacity .5, không strike, badge trắng (PDP 20px = base 1.25rem;
 *    quickview 16px = 1rem override) + X red-700
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node pdp-quickview-probe.js
 */
const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
const SHOT = '/var/www/projects/slaunchpad/.ai/evidence/TASK-0Y24D4/';

const results = [];
function log(name, pass, detail) {
  results.push({ name, pass });
  console.log((pass ? 'PASS' : 'FAIL') + ' | ' + name + (detail ? ' | ' : '') + (detail || ''));
}
function waitFor(fn, timeout, iv = 150) {
  const t0 = Date.now();
  return new Promise((res) => {
    (function poll() {
      if (fn()) return res(true);
      if (Date.now() - t0 >= timeout) return res(false);
      setTimeout(poll, iv);
    })();
  });
}

const styleOf = (loc) => loc.evaluate((el) => {
  const cs = getComputedStyle(el);
  const span = el.querySelector('span:not(.sr-only)');
  const after = getComputedStyle(el, '::after');
  return {
    swatchType: el.dataset.swatchType,
    backgroundColor: cs.backgroundColor,
    borderColor: cs.borderColor,
    borderWidth: cs.borderTopWidth,
    opacity: cs.opacity,
    color: cs.color,
    backgroundImage: cs.backgroundImage,
    spanBackgroundImage: span ? getComputedStyle(span).backgroundImage : null,
    afterWidth: after.width,
    afterBg: after.backgroundColor,
    afterImage: after.backgroundImage,
  };
});

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1', '--disable-dev-shm-usage', '--disable-gpu'] });
  const page = await browser.newPage({ viewport: { width: 1280, height: 1000 } });
  const pageErrors = [];
  page.on('pageerror', (e) => pageErrors.push(String(e.message).slice(0, 100)));

  // ---------- PDP visual OOS: bed-haven, Queen → Sage ----------
  await page.goto(BASE + '/haven-platform-bed.html', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForSelector('#product_addtocart_form label.swatch-option', { timeout: 15000 });
  await page.waitForTimeout(2000);
  await page.locator('#product_addtocart_form label.swatch-option:has-text("Queen")').first().click();
  const okVis = await waitFor(
    () => page.locator('#product_addtocart_form label.swatch-option[data-swatch-type="visual"] input:disabled').count().then((c) => c > 0),
    8000
  );
  log('PDP visual: Sage disabled sau khi chọn Queen', okVis);
  if (okVis) {
    const s = await styleOf(page.locator('#product_addtocart_form label.swatch-option[data-swatch-type="visual"]:has(input:disabled)').first());
    log('PDP visual: opacity .5', s.opacity === '0.5', s.opacity);
    log('PDP visual: không strike trên dot', s.spanBackgroundImage === 'none', s.spanBackgroundImage);
    log('PDP visual: badge 20px trắng (base 1.25rem)', s.afterWidth === '20px' && s.afterBg === 'rgb(255, 255, 255)', s.afterWidth + ' / ' + s.afterBg);
    log('PDP visual: badge X red-700 ×2 gradient', /rgb\(193, 0, 7\)/.test(s.afterImage) && (s.afterImage.match(/linear-gradient/g) || []).length >= 2, s.afterImage.slice(0, 100));
    await page.locator('#product_addtocart_form').screenshot({ path: SHOT + 'pdp-oos-visual.png' });
  }

  // ---------- PDP text OOS: sofa-meridian, Charcoal → 3 Seat ----------
  await page.goto(BASE + '/meridian-modular-sofa.html', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForSelector('#product_addtocart_form label.swatch-option', { timeout: 15000 });
  await page.waitForTimeout(2000);
  await page.locator('#product_addtocart_form label.swatch-option:has-text("Charcoal")').first().click();
  const okText = await waitFor(
    () => page.locator('#product_addtocart_form label.swatch-option[data-swatch-type="text"] input:disabled').count().then((c) => c > 0),
    8000
  );
  log('PDP text: 3 Seat disabled sau khi chọn Charcoal', okText);
  if (okText) {
    const s = await styleOf(page.locator('#product_addtocart_form label.swatch-option[data-swatch-type="text"]:has(input:disabled)').first());
    log('PDP text: bg #f3f4f6 + border #d1d5dc 2px', s.backgroundColor === 'rgb(243, 244, 246)' && s.borderColor === 'rgb(209, 213, 220)' && s.borderWidth === '2px', s.backgroundColor + ' / ' + s.borderColor);
    log('PDP text: label #99a1af @.75', s.color === 'rgb(153, 161, 175)' && s.opacity === '0.75', s.color + ' / ' + s.opacity);
    log('PDP text: strike 2px', /rgba\(0, 0, 0, 0\) calc\(50% - 1px\)[^;]*rgb\(209, 213, 220\)[^;]*calc\(50% \+ 1px\)/.test(s.backgroundImage.replace(/\s+/g, ' ')), s.backgroundImage.slice(0, 100));
    await page.locator('#product_addtocart_form').screenshot({ path: SHOT + 'pdp-oos-text.png' });
  }

  // ---------- Quick View: sofa (text OOS) + bed (visual OOS) ----------
  const D = 'dialog[aria-labelledby="quickview-modal-title"]';
  const qvCase = async (listUrl, cardText, pickText) => {
    await page.goto(BASE + listUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('.hp-card label.swatch-option', { timeout: 15000 });
    await page.waitForTimeout(2000);
    const card = page.locator(`.hp-card:has-text("${cardText}")`).first();
    await card.locator('.hp-card-quickview-btn').click();
    await page.waitForSelector(D, { state: 'visible', timeout: 15000 });
    await page.waitForTimeout(2500); // GraphQL render + Alpine settle
    await page.locator(`${D} label.swatch-option:has-text("${pickText}")`).first().click();
    const ok = await waitFor(
      () => page.locator(`${D} label.swatch-option input:disabled`).count().then((c) => c > 0),
      8000
    );
    return ok;
  };

  const okQvText = await qvCase('/living-room/living-room-seating.html', 'Meridian Modular Sofa', 'Charcoal');
  log('QuickView text: disabled xuất hiện sau khi chọn Charcoal', okQvText);
  if (okQvText) {
    const s = await styleOf(page.locator(`${D} label.swatch-option[data-swatch-type="text"]:has(input:disabled)`).first());
    log('QuickView text: bg/border/label/opacity khớp design', s.backgroundColor === 'rgb(243, 244, 246)' && s.borderColor === 'rgb(209, 213, 220)' && s.color === 'rgb(153, 161, 175)' && s.opacity === '0.75', JSON.stringify({ bg: s.backgroundColor, bd: s.borderColor, cl: s.color, op: s.opacity }));
    log('QuickView text: strike 2px', /rgba\(0, 0, 0, 0\) calc\(50% - 1px\)[^;]*rgb\(209, 213, 220\)/.test(s.backgroundImage.replace(/\s+/g, ' ')), s.backgroundImage.slice(0, 90));
  }

  const okQvVis = await qvCase('/bedroom/beds.html', 'Haven Platform Bed', 'Queen');
  log('QuickView visual: disabled xuất hiện sau khi chọn Queen', okQvVis);
  if (okQvVis) {
    const s = await styleOf(page.locator(`${D} label.swatch-option[data-swatch-type="visual"]:has(input:disabled)`).first());
    log('QuickView visual: opacity .5 + badge 16px trắng + X red-700', s.opacity === '0.5' && s.afterWidth === '16px' && s.afterBg === 'rgb(255, 255, 255)' && /rgb\(193, 0, 7\)/.test(s.afterImage), s.opacity + ' / ' + s.afterWidth + ' / ' + s.afterBg);
    log('QuickView visual: dot không còn X 2 nét cũ (span none)', s.spanBackgroundImage === 'none', s.spanBackgroundImage);
    await page.screenshot({ path: SHOT + 'quickview-oos.png' });
  }

  log('Không pageerror mới (sau env-fix deployed_version)', pageErrors.length === 0, pageErrors.slice(0, 3).join('; '));

  await browser.close();
  const fails = results.filter((r) => !r.pass).length;
  console.log(`\n${results.length - fails}/${results.length} passed`);
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error('PROBE ERROR', e); process.exit(2); });
