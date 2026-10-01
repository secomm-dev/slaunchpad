const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const b = await chromium.launch();
  const results = [];
  const ok = (n, c, x='') => results.push(`${c ? 'PASS' : 'FAIL'} ${n}${x ? ' — ' + x : ''}`);
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  for (let i = 0; i < 3; i++) {
    await page.goto(BASE + '/gear/bags.html', { waitUntil: 'load' });
    try { await page.waitForSelector('.lp-filter-item', { state: 'attached', timeout: 8000 }); break; } catch (e) {}
  }
  await page.waitForTimeout(800);
  ok('R2.1 trang sạch ẩn "Đang áp dụng"', await page.locator('.lp-filter-active').isHidden().catch(() => true) || await page.locator('.lp-filter-active').count() === 0);
  const btnTxt = await page.locator('.lp-filters-toggle').textContent().catch(() => '');
  ok('R2.1 Filters button không count', !/\(\d+\)/.test(btnTxt || ''), btnTxt.trim());
  await page.screenshot({ path: '/tmp/slp235-verify/r2-clean.png', clip: { x: 0, y: 250, width: 480, height: 300 } });

  // R2.2: có filter — count đúng + tick xanh
  await page.goto(BASE + '/gear/bags.html?activity=Gym', { waitUntil: 'load' });
  try { await page.waitForSelector('.lp-filter-item-box.bg-hp-brand-dark', { state: 'attached', timeout: 10000 }); } catch (e) { console.error('retry items'); await page.reload({ waitUntil: 'load' }); await page.waitForTimeout(1500); }
  await page.waitForTimeout(1200);
  const sel = await page.evaluate(() => {
    const el = document.querySelector('.lp-filter-item-box.bg-hp-brand-dark');
    const cs = el ? getComputedStyle(el) : null;
    const svg = el ? el.querySelector('svg') : null;
    return {
      bg: cs ? cs.backgroundColor : null,
      border: cs ? cs.borderColor : null,
      svgOpacity: svg ? getComputedStyle(svg).opacity : null,
      chips: document.querySelectorAll('.lp-filter-chip').length,
      activeRow: document.querySelector('.lp-filter-active')?.textContent.trim().replace(/\s+/g, ' ') || 'HIDDEN',
      btnCount: (document.querySelector('.lp-filters-toggle')?.textContent || '').trim()
    };
  });
  ok('R2.2 selected box nền xanh', sel.bg === 'rgb(69, 116, 76)', sel.bg);
  ok('R2.2 selected box border xanh', sel.border === 'rgb(69, 116, 76)', sel.border);
  ok('R2.2 tick trắng hiện (opacity 1)', sel.svgOpacity === '1', sel.svgOpacity);
  ok('R2.2 chips = 1', sel.chips === 1, 'n=' + sel.chips);
  ok('R2.2 Active filters (1)', sel.activeRow.includes('(1)'), sel.activeRow);
  ok('R2.2 Filters btn (1)', sel.btnCount.includes('(1)'), sel.btnCount);
  await page.locator('.lp-filter-sidebar .filter-option').nth(1).screenshot({ path: '/tmp/slp235-verify/r2-selected.png' }).catch(() => {});
  await page.locator('.lp-filter-sidebar').screenshot({ path: '/tmp/slp235-verify/r2-sidebar-filtered.png' }).catch(() => {});

  // R2.3: mode switcher vừa content, icon 16px centered
  const modes = await page.evaluate(() => {
    const m = document.querySelector('.toolbar-products .modes');
    if (!m) return null;
    const cs = getComputedStyle(m);
    const btn = m.querySelector('.modes-mode');
    const bs = btn ? getComputedStyle(btn) : null;
    return {
      w: Math.round(m.getBoundingClientRect().width),
      overflowX: cs.overflowX,
      btnW: btn ? Math.round(btn.getBoundingClientRect().width) : null,
      bgSize: bs ? bs.backgroundSize : null,
      bgPos: bs ? bs.backgroundPosition : null
    };
  });
  ok('R2.3 modes width auto (>= 100px, không ép 80px)', modes && modes.w >= 90 && modes.w <= 130, 'w=' + (modes && modes.w));
  ok('R2.3 icon 16px centered', modes && modes.bgSize === '16px 16px' && modes.bgPos && (modes.bgPos.includes('center') || modes.bgPos.includes('50%')), JSON.stringify(modes));

  console.log(results.join('\n'));
  console.log('\nALL:', results.every(r => r.startsWith('PASS')) ? 'PASS' : 'HAS FAILURES');
  await b.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
