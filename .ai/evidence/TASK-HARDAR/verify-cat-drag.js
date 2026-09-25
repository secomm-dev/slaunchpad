// TASK-HARDAR round 5 (SLP-267): mouse drag + arrows (≥48rem).
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.PW_CHROME || undefined, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const out = {};
  for (const width of [375, 768, 1440]) {
    const page = await (await browser.newContext({ viewport: { width, height: 900 } })).newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(String(e).slice(0, 120)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'load', timeout: 60000 });
    const sel = '[data-secomm-ui-component="categories_a"]';
    await page.locator(sel).scrollIntoViewIfNeeded();
    await page.waitForTimeout(1000);
    const state = () => page.$eval(sel, (sec) => {
      const t = sec.querySelector('[data-track]');
      const vis = (el) => !!el && getComputedStyle(el).display !== 'none' && getComputedStyle(el).visibility !== 'hidden' && getComputedStyle(el.parentElement).display !== 'none';
      const base = t.firstElementChild.offsetLeft;
      return { scrollLeft: Math.round(t.scrollLeft), snappedToTile: Array.from(t.children).some((c) => Math.abs(c.offsetLeft - base - t.scrollLeft) <= 2), arrows: vis(sec.querySelector('[data-next]')), dragging: t.classList.contains('is-dragging'), cursor: getComputedStyle(t).cursor };
    });
    const r = { before: await state() };
    const box = await page.locator(`${sel} .lp-cat-tile`).nth(1).boundingBox();
    const url0 = page.url();
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down();
    for (let i = 1; i <= 10; i++) await page.mouse.move(box.x + box.width / 2 - i * 25, box.y + box.height / 2);
    r.midDrag = await state();
    await page.mouse.up();
    await page.waitForTimeout(900);
    r.afterDrag = await state();
    r.navigatedByDrag = page.url() !== url0;
    // plain click still opens the category
    const href = await page.locator(`${sel} .lp-cat-tile`).nth(2).getAttribute('href');
    await Promise.all([page.waitForURL((u) => u.pathname === href, { timeout: 20000 }).catch(() => {}), page.locator(`${sel} .lp-cat-tile`).nth(2).click()]);
    r.clickOpens = new URL(page.url()).pathname === href;
    r.pageErrors = errs;
    out[width] = r;
    await page.close();
  }
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
