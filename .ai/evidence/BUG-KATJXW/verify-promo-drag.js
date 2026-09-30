// BUG-KATJXW (SLP-269): verify "Everyday more value" rail = slider (mouse drag + snap).
// Run: PW_CHROME=<chrome ≥105, cần :has()> NODE_PATH=<node_modules có playwright> node verify-promo-drag.js
const { chromium } = require('playwright');
const RAIL = '[data-content-type="column-line"]:has(> .lp-promo-card), [data-content-type="column-group"]:has(> .lp-promo-card)';
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.PW_CHROME || undefined, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  let fail = 0;
  const check = (width, name, ok, detail) => { if (!ok) fail++; console.log(`${width} ${ok ? 'PASS' : 'FAIL'} ${name} ${JSON.stringify(detail)}`); };
  for (const width of [375, 768, 1440]) {
    const page = await (await browser.newContext({ viewport: { width, height: 900 } })).newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(String(e).slice(0, 120)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'load', timeout: 60000 });
    await page.waitForTimeout(1000);
    const rail = page.locator(RAIL).first();
    await rail.scrollIntoViewIfNeeded();
    const state = await rail.evaluate((r) => {
      const cs = getComputedStyle(r);
      const cards = Array.from(r.children).filter((c) => c.classList.contains('lp-promo-card'));
      const start = r.getBoundingClientRect().left + r.clientLeft;
      return {
        hasSupport: CSS.supports('selector(:has(> a))'),
        snap: cs.scrollSnapType, cursor: cs.cursor, overflowX: cs.overflowX,
        cards: cards.map((c) => [Math.round(c.getBoundingClientRect().width), Math.round(c.getBoundingClientRect().height)]),
        offsets: cards.map((c) => Math.round(c.getBoundingClientRect().left - start + r.scrollLeft)),
        max: r.scrollWidth - r.clientWidth,
        railRight: Math.round(r.getBoundingClientRect().right),
        pageHScroll: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        catSlider: !!document.querySelector('[data-secomm-ui-component="categories_a"] [data-track]'),
        hero: !!document.querySelector('.lp-hero-slider [data-track]'),
      };
    });
    const [cw, ch] = width >= 1024 ? [360, 502] : [320, 400];
    check(width, 'env :has() supported', state.hasSupport, {});
    check(width, 'rail overflow + snap x mandatory + cursor grab', state.overflowX === 'auto' && state.snap.includes('x') && state.snap.includes('mandatory') && state.cursor === 'grab', { overflowX: state.overflowX, snap: state.snap, cursor: state.cursor });
    check(width, `cards ${cw}x${ch}`, state.cards.length === 4 && state.cards.every(([w, h]) => w === cw && h === ch), state.cards);
    check(width, 'no page h-scroll', !state.pageHScroll, {});
    if (width >= 1024) check(width, 'rail bleeds to viewport right edge', state.railRight >= width - 20, { railRight: state.railRight });
    check(width, 'other sections intact (category slider + hero)', state.catSlider && state.hero, { cat: state.catSlider, hero: state.hero });

    // AC3: programmatic scroll lệch giữa card → snap về mép card (hoặc max).
    await rail.evaluate((r) => { r.scrollTo({ left: 0 }); });
    await page.waitForTimeout(200);
    await rail.evaluate((r) => { r.scrollLeft = 120; });
    await page.waitForTimeout(900);
    const snapped = await rail.evaluate((r) => r.scrollLeft);
    const snapTargets = state.offsets.map((o) => Math.min(o, state.max));
    check(width, 'snap to card edge after scroll 120', snapTargets.some((o) => Math.abs(o - snapped) <= 2), { snapped, snapTargets });

    // AC1: mouse drag từ thân card (không phải link) sang trái.
    await rail.evaluate((r) => { r.scrollTo({ left: 0 }); });
    await page.waitForTimeout(600);
    const box = await rail.boundingBox();
    const y = box.y + 40;
    const urlBefore = page.url();
    await page.mouse.move(box.x + Math.min(box.width, width) * 0.8, y);
    await page.mouse.down();
    await page.mouse.move(box.x + Math.min(box.width, width) * 0.55, y, { steps: 12 });
    const midDrag = await rail.evaluate((r) => ({ sl: r.scrollLeft, dragging: r.classList.contains('lp-promo-dragging'), cursor: getComputedStyle(r).cursor }));
    await page.mouse.up();
    await page.waitForTimeout(1000);
    const afterDrag = await rail.evaluate((r) => ({ sl: r.scrollLeft, dragging: r.classList.contains('lp-promo-dragging') }));
    check(width, 'mouse drag follows pointer (grabbing)', midDrag.sl > 0 && midDrag.dragging && midDrag.cursor === 'grabbing', midDrag);
    check(width, 'release settles on next card edge / max, snap restored', afterDrag.sl > 0 && snapTargets.some((o) => Math.abs(o - afterDrag.sl) <= 2) && !afterDrag.dragging, { ...afterDrag, snapTargets });

    // Drag ngược lại (phải) → về card trước.
    await page.mouse.move(box.x + Math.min(box.width, width) * 0.3, y);
    await page.mouse.down();
    await page.mouse.move(box.x + Math.min(box.width, width) * 0.5, y, { steps: 8 });
    await page.mouse.up();
    await page.waitForTimeout(1000);
    const back = await rail.evaluate((r) => r.scrollLeft);
    check(width, 'drag right returns to previous card edge', back < afterDrag.sl && snapTargets.some((o) => Math.abs(o - back) <= 2), { back });

    // AC2: kéo bắt đầu trên nút "→" → không điều hướng.
    await rail.evaluate((r) => { r.scrollTo({ left: 0 }); });
    await page.waitForTimeout(600);
    const link = page.locator('.lp-promo-card [data-element="link"]').first();
    const lb = await link.boundingBox();
    await page.mouse.move(lb.x + lb.width / 2, lb.y + lb.height / 2);
    await page.mouse.down();
    await page.mouse.move(lb.x - 120, lb.y + lb.height / 2, { steps: 10 });
    await page.mouse.up();
    await page.waitForTimeout(1200);
    check(width, 'drag started on "→" link does not navigate', page.url() === urlBefore, { url: page.url() });

    if (width === 1440) {
      await page.screenshot({ path: `${__dirname}/promo-${width}.png`, clip: { x: 0, y: Math.max(0, box.y - 160), width, height: Math.min(box.height + 200, 900) } });
      await rail.evaluate((r) => { r.scrollTo({ left: 0 }); });
      await page.waitForTimeout(600);
      await Promise.all([page.waitForURL(/\/value/, { timeout: 30000 }).catch(() => null), link.click()]);
      check(width, 'plain click on "→" navigates to /value', /\/value/.test(page.url()), { url: page.url() });
    } else {
      await page.screenshot({ path: `${__dirname}/promo-${width}.png`, clip: { x: 0, y: Math.max(0, box.y - 120), width, height: Math.min(box.height + 160, 900) } });
    }
    check(width, '0 pageerror', errs.length === 0, errs);
    await page.context().close();
  }
  await browser.close();
  console.log(fail ? `RESULT: ${fail} FAIL` : 'RESULT: ALL PASS');
  process.exit(fail ? 1 : 0);
})();
