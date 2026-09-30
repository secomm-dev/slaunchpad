const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const b = await chromium.launch();
  const results = [];
  const ok = (n, c, x='') => results.push(`${c ? 'PASS' : 'FAIL'} ${n}${x ? ' — ' + x : ''}`);
  const cm = await b.newContext({ viewport: { width: 375, height: 812 } });
  const pm = await cm.newPage();
  const errs = [];
  pm.on('pageerror', e => errs.push(e.message.slice(0, 100)));

  for (let i = 0; i < 3; i++) {
    await pm.goto(BASE + '/gear/bags.html', { waitUntil: 'load' });
    try { await pm.waitForSelector('.lp-filter-item', { state: 'attached', timeout: 8000 }); break; } catch (e) {}
  }
  await pm.waitForTimeout(800);

  // R3.1: toolbar 1 hàng — Filters + Sort cùng baseline, không wrap
  const bar = await pm.evaluate(() => {
    const f = document.querySelector('.lp-filters-toggle');
    const s = document.querySelector('.lp-sorter-control');
    if (!f || !s) return null;
    const fr = f.getBoundingClientRect(), sr = s.getBoundingClientRect();
    return {
      sameRow: Math.abs(fr.top - sr.top) < 8,
      fText: f.textContent.trim().replace(/\s+/g, ' '),
      fOneLine: fr.height < 55,
      fRight: Math.round(fr.right),
      sLeft: Math.round(sr.left),
      overlap: fr.right > sr.left
    };
  });
  ok('R3.1 Filters + Sort cùng 1 hàng', bar && bar.sameRow && !bar.overlap, JSON.stringify(bar));
  ok('R3.1 nút Bộ lọc 1 dòng chữ (không wrap)', bar && bar.fOneLine, 'h=' + (bar && bar.fText) + ' / ' + (bar && bar.fOneLine));
  await pm.screenshot({ path: '/tmp/slp235-verify/r3-toolbar.png', clip: { x: 0, y: 210, width: 375, height: 90 } });

  // R3.2: ATC icon-only mobile
  const atc = await pm.evaluate(() => {
    const btn = document.querySelector('.hp-btn-atc');
    if (!btn) return null;
    const r = btn.getBoundingClientRect();
    const span = btn.querySelector('span');
    return { w: Math.round(r.width), h: Math.round(r.height), textHidden: span ? getComputedStyle(span).display === 'none' : false };
  });
  ok('R3.2 ATC icon-only (36px vuông, text ẩn)', atc && atc.w <= 40 && atc.h <= 40 && atc.textHidden, JSON.stringify(atc));

  // R3.3: gap nhỏ giữa card
  const gap = await pm.evaluate(() => {
    const ul = document.querySelector('.products.wrapper ul');
    const cs = getComputedStyle(ul);
    return { col: cs.columnGap, row: cs.rowGap };
  });
  ok('R3.3 gap mobile nhỏ (12/16)', gap.col === '12px' && gap.row === '16px', JSON.stringify(gap));

  // R3.4: title 1 hàng + ellipsis (tạo title dài giả lập qua scrollWidth)
  const title = await pm.evaluate(() => {
    const a = document.querySelector('.hp-card-name');
    if (!a) return null;
    const cs = getComputedStyle(a);
    return { display: cs.display, whiteSpace: cs.whiteSpace, overflow: cs.overflowX,
             oneLine: a.getBoundingClientRect().height < 30 };
  });
  ok('R3.4 title block + nowrap + 1 hàng', title && title.display === 'block' && title.whiteSpace === 'nowrap' && title.oneLine, JSON.stringify(title));

  // R3.5: không đổi ở desktop (ATC full text)
  const cd = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const pd = await cd.newPage();
  await pd.goto(BASE + '/gear/bags.html', { waitUntil: 'load' });
  await pd.waitForTimeout(1000);
  const atcD = await pd.evaluate(() => {
    const btn = document.querySelector('.hp-btn-atc');
    const span = btn.querySelector('span');
    return { w: Math.round(btn.getBoundingClientRect().width), textShown: getComputedStyle(span).display !== 'none' };
  });
  ok('R3.5 desktop ATC giữ nguyên (text + flex)', atcD.textShown && atcD.w > 100, JSON.stringify(atcD));

  console.log(results.join('\n'));
  console.log('pageerrors:', errs.length ? errs.join('|') : '(none)');
  console.log('\nALL:', results.every(r => r.startsWith('PASS')) ? 'PASS' : 'HAS FAILURES');
  await b.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
