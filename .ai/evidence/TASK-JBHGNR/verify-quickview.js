// TASK-JBHGNR: Quick View — sofa-meridian, pick Charcoal → Corner (corner-charcoal OOS) disabled + OOS style.
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome', args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await b.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  const errs = []; page.on('pageerror', (e) => errs.push(String(e).slice(0, 160)));
  await page.goto('http://slaunchpad.localhost/living-room/living-room-seating.html', { waitUntil: 'load', timeout: 90000 });
  const card = page.locator('.hp-card:has(input[name="product"][value="2176"])').first();
  await card.scrollIntoViewIfNeeded();
  await card.locator('.hp-card-quickview-btn').click();
  await page.waitForFunction(() => document.querySelectorAll('input[data-option-label="Charcoal"]').length > 1 || [...document.querySelectorAll('input[data-option-label="Charcoal"]')].some((i) => !i.closest('.hp-card')), null, { timeout: 20000 });
  const qv = page.locator('input[data-option-label="Charcoal"]:not(.hp-card input)').first();
  await qv.check({ force: true });
  await page.mouse.move(0, 0); await page.waitForTimeout(500);
  const res = await qv.evaluate((el) => {
    const root = el.closest('form') || el.closest('fieldset').parentElement.parentElement;
    return Array.from(root.querySelectorAll('label.swatch-option')).map((l) => {
      const i = l.querySelector('input'); const cs = getComputedStyle(l);
      return { label: i.dataset.optionLabel, disabled: i.disabled, border: cs.borderColor, color: cs.color, opacity: cs.opacity, strike: cs.backgroundImage !== 'none' || getComputedStyle(l.querySelector('span.block') || l).backgroundImage !== 'none', cursor: cs.cursor };
    });
  });
  await qv.evaluate((el) => { const r = el.closest('form') || el.closest('fieldset').parentElement.parentElement; r.setAttribute('data-shot', '1'); return true; });
  await page.locator('[data-shot="1"]').screenshot({ path: `${__dirname}/quickview-sofa-charcoal.png` });
  console.log(JSON.stringify({ options: res, pageerrors: errs }, null, 1));
  await b.close();
})();
