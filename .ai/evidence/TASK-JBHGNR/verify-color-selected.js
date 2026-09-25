// TASK-JBHGNR CR1: selected colour swatch — no white bg, ring hugs the dot (round).
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const label = process.argv[2] || 'run';
(async () => {
  const b = await chromium.launch({ executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome', args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await b.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 2 })).newPage();
  const errs = []; page.on('pageerror', (e) => errs.push(String(e).slice(0, 160)));
  const read = (inp) => {
    const l = inp.closest('.swatch-option'); const s = [...l.querySelectorAll(':scope > span:not(.sr-only)')].find((x) => x.offsetWidth);
    const cs = getComputedStyle(l); const r = l.getBoundingClientRect(); const sr = s.getBoundingClientRect();
    return { label: `${Math.round(r.width)}x${Math.round(r.height)}`, dot: `${Math.round(sr.width)}x${Math.round(sr.height)}`, radius: cs.borderRadius, bg: cs.backgroundColor, border: `${cs.borderWidth} ${cs.borderColor}`, shadow: cs.boxShadow, padding: cs.padding };
  };
  const out = {};
  const shot = async (loc, name) => { await page.mouse.move(0, 0); await page.waitForTimeout(300); await loc.screenshot({ path: `${__dirname}/color-selected-${name}-${label}.png` }); };
  // card
  await page.goto('http://slaunchpad.localhost/living-room/living-room-seating.html', { waitUntil: 'load', timeout: 90000 });
  const card = page.locator('.hp-card:has(input[name="product"][value="2176"])').first();
  await card.scrollIntoViewIfNeeded(); await page.waitForTimeout(800);
  const ci = card.locator('input[data-option-label="Sage"]'); await ci.check({ force: true });
  out.card = await ci.evaluate(read); await shot(ci.locator('xpath=ancestor::div[1]'), 'card');
  // quick view
  await card.locator('.hp-card-quickview-btn').click();
  await page.waitForFunction(() => [...document.querySelectorAll('input[data-option-label="Sage"]')].some((i) => !i.closest('.hp-card')), null, { timeout: 20000 });
  const qi = page.locator('input[data-option-label="Sage"]:not(.hp-card input)').first(); await qi.check({ force: true });
  out.quickview = await qi.evaluate(read); await shot(qi.locator('xpath=ancestor::div[1]'), 'quickview');
  // pdp
  await page.goto('http://slaunchpad.localhost/meridian-modular-sofa.html', { waitUntil: 'load', timeout: 90000 });
  const pi = page.locator('#product_addtocart_form input[data-option-label="Sage"]'); await page.waitForTimeout(800); await pi.check({ force: true });
  out.pdp = await pi.evaluate(read); await shot(pi.locator('xpath=ancestor::div[1]'), 'pdp');
  out.pageerrors = errs;
  console.log(JSON.stringify(out, null, 1)); await b.close();
})();
