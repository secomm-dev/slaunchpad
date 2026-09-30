// TASK-JBHGNR: synthetic check of the visual (colour) strike — marks one colour
// swatch aria-disabled="true" (selector covered by swatches.css) since no colour
// is fully OOS while show_out_of_stock is still 0 on local.
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome', args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await b.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  const out = {};
  for (const [name, url, rootSel] of [['card', 'bedroom/beds.html', '.hp-card:has(input[name="product"][value="2144"])'], ['pdp', 'haven-platform-bed.html', '#product_addtocart_form']]) {
    await page.goto('http://slaunchpad.localhost/' + url, { waitUntil: 'load', timeout: 90000 });
    const root = page.locator(rootSel).first();
    await root.scrollIntoViewIfNeeded(); await page.waitForTimeout(800);
    out[name] = await root.evaluate((r) => {
      const l = r.querySelector('.swatch-option[data-swatch-type="visual"]:has(input[data-option-label="Oatmeal"])');
      l.setAttribute('aria-disabled', 'true');
      const a = getComputedStyle(l, '::after'); const span = l.querySelector(':scope > span');
      const ar = { w: a.width, h: a.height, radius: a.borderRadius, bg: a.backgroundImage.slice(0, 90) };
      return { after: a.content === 'none' ? null : ar, spanBg: getComputedStyle(span).backgroundImage.slice(0, 90), spanSize: `${span.offsetWidth}x${span.offsetHeight}`, spanOpacity: getComputedStyle(span).opacity, border: getComputedStyle(l).borderColor };
    });
    const sw = root.locator('.swatch-option[data-swatch-type="visual"]').first().locator('xpath=..');
    await sw.screenshot({ path: `${__dirname}/visual-strike-${name}.png` });
  }
  console.log(JSON.stringify(out, null, 1)); await b.close();
})();
