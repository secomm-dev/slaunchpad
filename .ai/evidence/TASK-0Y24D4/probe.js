/**
 * TASK-0Y24D4 (SLP-307) — card OOS swatch states per Figma 3286-150414.
 * OOS state on cards manifests via Hyvä combo filtering (SLP-272 semantics):
 * select one attribute → sibling options with no salable combination get
 * disabled → :has(:disabled) → CSS applies.
 * AC-001 text swatch OOS: sofa-meridian, pick colour "Charcoal" → size
 *   "3 Seat" + "Corner" disabled → bg #f3f4f6, border 2px #d1d5dc, label
 *   #99a1af @ .75, 2px strike gradient.
 * AC-002 visual swatch OOS: bed-haven, pick size "Queen" → colour "Sage"
 *   disabled → opacity .5, no strike on dot, ::after 16px white badge with
 *   red-700 X gradients.
 * AC-003 selected swatch keeps green ring (SLP-272 CR1).
 * Pre-existing pageerrors (optionIsDisabled race on fresh load, jackets too)
 * are OUT OF SCOPE — flagged separately.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe.js
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
    afterContent: after.content,
    afterWidth: after.width,
    afterBg: after.backgroundColor,
    afterImage: after.backgroundImage,
  };
});

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1', '--disable-dev-shm-usage', '--disable-gpu'] });
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

  const pickThenOOS = async (url, pickText) => {
    await page.goto(BASE + url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('.hp-card label.swatch-option', { timeout: 15000 });
    await page.waitForTimeout(2500); // Alpine settle (pre-existing init race)
    const pick = page.locator(`.hp-card label.swatch-option:has-text("${pickText}")`).first();
    await pick.click();
    const appeared = await waitFor(
      () => page.locator('.hp-card label.swatch-option input:disabled').count().then((c) => c > 0),
      8000
    );
    return appeared;
  };

  // ---------- AC-001 text swatch OOS (sofa-meridian: Charcoal → 3 Seat) ----------
  const ok1 = await pickThenOOS('/living-room/living-room-seating.html', 'Charcoal');
  log('AC-001 disabled swatch appears after picking Charcoal', ok1);
  if (ok1) {
    const textOOS = page.locator('.hp-card label.swatch-option[data-swatch-type="text"]:has(input:disabled)').first();
    const n = await page.locator('.hp-card label.swatch-option[data-swatch-type="text"]:has(input:disabled)').count();
    log('AC-001 disabled text swatch(es) present', n > 0, `${n} disabled (expect 3 Seat + Corner)`);
    const s = await styleOf(textOOS);
    log('AC-001 bg #f3f4f6', s.backgroundColor === 'rgb(243, 244, 246)', s.backgroundColor);
    log('AC-001 border #d1d5dc 2px', s.borderColor === 'rgb(209, 213, 220)' && s.borderWidth === '2px', s.borderColor + ' / ' + s.borderWidth);
    log('AC-001 label #99a1af @ .75', s.color === 'rgb(153, 161, 175)' && s.opacity === '0.75', s.color + ' / ' + s.opacity);
    log('AC-001 2px strike gradient', /rgba\(0, 0, 0, 0\) calc\(50% - 1px\)[^;]*rgb\(209, 213, 220\)[^;]*calc\(50% \+ 1px\)/.test(s.backgroundImage.replace(/\s+/g, ' ')), s.backgroundImage.slice(0, 130));
    const card = page.locator('.hp-card:has(label.swatch-option[data-swatch-type="text"] input:disabled)').first();
    await card.screenshot({ path: SHOT + 'card-oos-text.png' });
  }

  // ---------- AC-002 visual swatch OOS (bed-haven: Queen → Sage) ----------
  const ok2 = await pickThenOOS('/bedroom/beds.html', 'Queen');
  log('AC-002 disabled swatch appears after picking Queen', ok2);
  if (ok2) {
    const visOOS = page.locator('.hp-card label.swatch-option[data-swatch-type="visual"]:has(input:disabled)').first();
    const s = await styleOf(visOOS);
    log('AC-002 swatch type visual', s.swatchType === 'visual', s.swatchType);
    log('AC-002 opacity .5', s.opacity === '0.5', s.opacity);
    log('AC-002 no strike on dot', s.spanBackgroundImage === 'none', s.spanBackgroundImage);
    log('AC-002 badge ::after 16px white', s.afterContent === '""' && s.afterWidth === '16px' && s.afterBg === 'rgb(255, 255, 255)', s.afterContent + ' / ' + s.afterWidth + ' / ' + s.afterBg);
    log('AC-002 badge X red-700 x2 gradients', /rgb\(193, 0, 7\)/.test(s.afterImage) && (s.afterImage.match(/linear-gradient/g) || []).length >= 2, s.afterImage.slice(0, 110));
    const card = page.locator('.hp-card:has(label.swatch-option[data-swatch-type="visual"] input:disabled)').first();
    await card.screenshot({ path: SHOT + 'card-oos-visual.png' });

    // ---------- AC-003 selected swatch unchanged (Queen just picked) ----------
    await page.waitForTimeout(400); // let the 150ms border-color transition finish
    const sel = await page.locator('.hp-card label.swatch-option:has(input:checked)').first().evaluate((el) => {
      const cs = getComputedStyle(el);
      return { borderColor: cs.borderColor, shadow: cs.boxShadow, opacity: cs.opacity };
    });
    log('AC-003 selected keeps green ring, full opacity', sel.borderColor === 'rgb(88, 143, 96)' && sel.shadow.includes('rgb(88, 143, 96)') && sel.opacity === '1', JSON.stringify(sel));
  }

  await browser.close();
  const fails = results.filter((r) => !r.pass).length;
  console.log(`\n${results.length - fails}/${results.length} passed`);
  process.exit(fails ? 1 : 0);
})().catch((e) => { console.error('PROBE ERROR', e); process.exit(2); });
