const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';

(async () => {
  const b = await chromium.launch();
  const errors = [];
  const pass = (name, ok, extra = '') => console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${extra ? ' — ' + extra : ''}`);

  // ---------- Desktop 1440 (vi) ----------
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  p.on('pageerror', e => errors.push('desktop pageerror: ' + e.message));
  await p.goto(BASE + '/', { waitUntil: 'networkidle' });
  await p.locator('.lp-flash').scrollIntoViewIfNeeded();
  await p.waitForTimeout(800);

  // AC-001: gradient + full-bleed theo elementFromPoint tại 2 mép + overflow
  const row = p.locator('[data-content-type="row"]:has(.lp-flash)');
  const grad = await row.evaluate(el => getComputedStyle(el).backgroundImage);
  pass('AC-001a gradient trên row PB', grad.includes('linear-gradient(278deg'), grad.slice(0, 70));
  const edges = await p.evaluate(() => {
    const r = document.querySelector('.lp-flash').getBoundingClientRect();
    const y = r.top + r.height / 2;
    return {
      left: (document.elementFromPoint(2, y) || {}).className || 'null',
      right: (document.elementFromPoint(document.documentElement.clientWidth - 2, y) || {}).className || 'null',
      rightFar: (document.elementFromPoint(window.innerWidth - 2, y) || {}).className || 'null'
    };
  });
  const inRow = s => String(s).includes('lp-flash') || String(s).includes('hp-pb') || String(s).includes('pagebuilder');
  pass('AC-001b full-bleed 2 mép', inRow(edges.left) && inRow(edges.right), JSON.stringify(edges));
  const overflow = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  pass('AC-001c không overflow-x', overflow <= 1, `delta=${overflow}`);

  // AC-002 title trắng
  const titleColor = await p.locator('.lp-flash-title').evaluate(el => getComputedStyle(el).color);
  pass('AC-002 title trắng', titleColor === 'rgb(255, 255, 255)', titleColor);

  // AC-003 box + alpha + tick + không wrap (3 box cùng height)
  const cells = await p.locator('.lp-countdown-cell').count();
  const seps = await p.locator('.lp-countdown-sep').count();
  pass('AC-003a 3 cell + 2 sep', cells === 3 && seps === 2, `cells=${cells}, seps=${seps}`);
  const cellStyle = await p.locator('.lp-countdown-cell').first().evaluate(el => {
    const s = getComputedStyle(el);
    return { alpha: s.backgroundColor.match(/0?\.\d+\)$|0\.\d+\)/)?.[0], radius: s.borderRadius };
  });
  pass('AC-003b box alpha 0.3 + rounded 8px', String(cellStyle.alpha).startsWith('0.3') && cellStyle.radius === '8px', JSON.stringify(cellStyle));
  const t1 = await p.locator('.lp-countdown-num').allTextContents();
  await p.waitForTimeout(2100);
  const t2 = await p.locator('.lp-countdown-num').allTextContents();
  pass('AC-003c đếm tick', JSON.stringify(t1) !== JSON.stringify(t2), `before=[${t1.join(':')}] after=[${t2.join(':')}]`);
  const heights = [];
  for (let i = 0; i < 3; i++) heights.push(await p.locator('.lp-countdown-cell').nth(i).evaluate(el => el.getBoundingClientRect().height));
  pass('AC-003d 3 box cùng height (không wrap)', new Set(heights).size === 1, `heights=${heights.map(Math.round).join(',')}`);

  await p.screenshot({ path: '/tmp/slp297-desktop.png', clip: await row.evaluate(el => { const r = el.getBoundingClientRect(); return { x: Math.max(0, r.x), y: Math.max(0, r.y), width: Math.min(r.width, 1440), height: Math.min(r.height, 900) }; }) });

  // ---------- Mobile 375 (vi) ----------
  const m = await (await b.newContext({ viewport: { width: 375, height: 812 } })).newPage();
  m.on('pageerror', e => errors.push('mobile pageerror: ' + e.message));
  await m.goto(BASE + '/', { waitUntil: 'networkidle' });
  await m.locator('.lp-flash').scrollIntoViewIfNeeded();
  await m.waitForTimeout(800);

  const mrow = m.locator('[data-content-type="row"]:has(.lp-flash)');
  const mGrad = await mrow.evaluate(el => getComputedStyle(el).backgroundImage).catch(() => 'n/a');
  pass('AC-005a mobile gradient', mGrad.includes('linear-gradient(278deg'), mGrad.slice(0, 50));
  const mNum = await m.locator('.lp-countdown-num').first().evaluate(el => {
    const s = getComputedStyle(el);
    return { fs: s.fontSize, lh: s.lineHeight, mw: s.minWidth, ws: s.whiteSpace };
  });
  pass('AC-005b mobile scale 24/28 min-w 32 + nowrap', mNum.fs === '24px' && mNum.lh === '28px' && mNum.mw === '32px' && mNum.ws === 'nowrap', JSON.stringify(mNum));
  const mEdges = await m.evaluate(() => {
    const r = document.querySelector('.lp-flash').getBoundingClientRect();
    const y = r.top + r.height / 2;
    return {
      left: (document.elementFromPoint(2, y) || {}).className || 'null',
      right: (document.elementFromPoint(373, y) || {}).className || 'null'
    };
  });
  pass('AC-005c mobile full-bleed 2 mép', inRow(mEdges.left) && inRow(mEdges.right), JSON.stringify(mEdges));
  const mOverflow = await m.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  pass('AC-005e mobile không overflow-x', mOverflow <= 1, `delta=${mOverflow}`);
  const gridCols = await m.locator('.lp-flash .hp-pb-slider > [data-track]').evaluate(el => getComputedStyle(el).gridTemplateColumns).catch(() => 'n/a');
  pass('AC-005d slider grid 2 cột giữ nguyên', (gridCols.match(/px/g) || []).length === 2, gridCols.slice(0, 50));

  await m.screenshot({ path: '/tmp/slp297-mobile.png', clip: await mrow.evaluate(el => { const r = el.getBoundingClientRect(); return { x: 0, y: Math.max(0, r.y), width: 375, height: Math.min(r.height, 812) }; }) });

  pass('AC-007 0 pageerror', errors.length === 0, errors.join(' | ').slice(0, 200));
  await b.close();
})();
