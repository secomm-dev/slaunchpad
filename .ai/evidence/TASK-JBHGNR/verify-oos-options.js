// TASK-JBHGNR (SLP-272): OOS configurable options on product card / PDP.
// usage: node verify-oos-options.js <label>   (PW lib + Chrome 150, see memory)
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const label = process.argv[2] || 'run';
const outDir = __dirname;
const BASE = 'http://slaunchpad.localhost/';
const CARDS = [
  { url: 'bedroom/beds.html', pid: 2144 },
  { url: 'living-room/living-room-seating.html', pid: 2176 },
  { url: 'dining/dining-tables.html', pid: 2157 },
];
const PDPS = ['haven-platform-bed.html', 'meridian-modular-sofa.html'];

const readOptions = (root) => Array.from(root.querySelectorAll('.swatch-option')).map((l) => {
  const cs = getComputedStyle(l);
  const inner = l.querySelector(':scope > span, :scope > img');
  const after = getComputedStyle(l, '::after');
  const input = l.querySelector('input');
  return {
    text: (l.textContent || '').trim() || input?.dataset.optionLabel,
    type: l.dataset.swatchType,
    disabled: !!input?.disabled,
    border: `${cs.borderStyle} ${cs.borderColor}`,
    color: cs.color,
    opacity: cs.opacity,
    innerOpacity: inner ? getComputedStyle(inner).opacity : null,
    bgImage: cs.backgroundImage.slice(0, 60),
    after: after.content !== 'none' ? after.backgroundImage.slice(0, 60) : null,
    cursor: cs.cursor,
  };
});

(async () => {
  const browser = await chromium.launch({
    executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome',
    args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
  });
  const result = { cards: {}, pdp: {} };
  const errs = [];
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  page.on('pageerror', (e) => errs.push(String(e).slice(0, 160)));

  for (const c of CARDS) {
    await page.goto(BASE + c.url, { waitUntil: 'load', timeout: 90000 });
    const card = page.locator(`.hp-card:has(input[name="product"][value="${c.pid}"])`).first();
    if (!(await card.count())) { result.cards[c.url] = 'card not found'; continue; }
    await card.scrollIntoViewIfNeeded();
    await page.waitForTimeout(800);
    result.cards[c.url] = await card.evaluate(readOptions);
    await card.screenshot({ path: `${outDir}/card-${c.pid}-${label}.png` });
  }

  // Combination check on sofa: pick "Charcoal" → Corner (only corner-charcoal OOS) must disable.
  {
    await page.goto(BASE + 'living-room/living-room-seating.html', { waitUntil: 'load', timeout: 90000 });
    const card = page.locator('.hp-card:has(input[name="product"][value="2176"])').first();
    if (await card.count()) {
      await card.scrollIntoViewIfNeeded();
      await page.waitForTimeout(800);
      const charcoal = card.locator('input[data-option-label="Charcoal"]');
      if (await charcoal.count()) {
        await charcoal.check({ force: true });
        await page.mouse.move(0, 0);
        await page.waitForTimeout(400);
        result.sofaAfterCharcoal = await card.evaluate((r) => Array.from(r.querySelectorAll('input[type=radio]')).map((i) => `${i.dataset.optionLabel}:${i.disabled ? 'disabled' : 'enabled'}`));
        result.sofaAfterCharcoalStyles = await card.evaluate(readOptions);
        await card.screenshot({ path: `${outDir}/card-2176-charcoal-${label}.png` });
      }
    }
  }

  for (const u of PDPS) {
    await page.goto(BASE + u, { waitUntil: 'load', timeout: 90000 });
    const form = page.locator('#product_addtocart_form');
    await page.waitForTimeout(800);
    result.pdp[u] = await form.evaluate(readOptions);
    const box = form.locator('.swatch-option').first().locator('xpath=ancestor::div[fieldset][1]');
    await (await box.count() ? box : form).screenshot({ path: `${outDir}/pdp-${u.replace('.html', '')}-${label}.png` });
  }
  result.pageerrors = errs;
  console.log(JSON.stringify(result, null, 1));
  await browser.close();
})();
