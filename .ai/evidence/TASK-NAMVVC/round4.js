const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const b = await chromium.launch();
  const results = [];
  const ok = (n, c, x='') => results.push(`${c ? 'PASS' : 'FAIL'} ${n}${x ? ' — ' + x : ''}`);

  // Mobile
  const cm = await b.newContext({ viewport: { width: 375, height: 812 } });
  const pm = await cm.newPage();
  await pm.goto(BASE + '/gear/bags.html', { waitUntil: 'load' });
  await pm.waitForTimeout(1200);
  const rows = await pm.evaluate(() => {
    const tb = document.querySelector('.toolbar-products.toolbar-bottom');
    if (!tb) return 'no bottom toolbar';
    const pages = tb.querySelector('.pages')?.closest('[class*="order-"], div');
    const pagesWrap = tb.querySelector('div.order-2, div[class*="order-2"]');
    const amount = tb.querySelector('.toolbar-amount');
    const limiter = tb.querySelector('.limiter');
    const pr = pagesWrap ? pagesWrap.getBoundingClientRect() : null;
    const ar = amount ? amount.getBoundingClientRect() : null;
    const lr = limiter ? limiter.getBoundingClientRect() : null;
    const cs = getComputedStyle(tb);
    return {
      display: cs.display,                       // grid (không còn flex đè)
      pagesTop: pr && Math.round(pr.top),
      amountTop: ar && Math.round(ar.top),
      limiterTop: lr && Math.round(lr.top),
      twoRows: pr && ar && Math.abs(pr.top - ar.top) > 20,
      sameRow2: ar && lr && Math.abs((ar.top + ar.height/2) - (lr.top + lr.height/2)) < 8,
      amountLeft: ar && lr ? ar.left < lr.left : null,
      pageBtnBorder: (() => { const b = tb.querySelector('.lp-pager-page'); return b ? getComputedStyle(b).borderTopColor : null; })(),
      currentBg: (() => { const b = tb.querySelector('.lp-pager-page-current'); return b ? getComputedStyle(b).backgroundColor : null; })()
    };
  });
  console.log('MOBILE:', JSON.stringify(rows));
  ok('R4.1 bottom grid (không còn flex 1 hàng)', rows.display === 'grid', rows.display);
  ok('R4.2 pages hàng 1 / amount+limiter hàng 2', rows.twoRows, `pages y=${rows.pagesTop} amount y=${rows.amountTop}`);
  ok('R4.3 amount trái + limiter phải cùng hàng 2', rows.sameRow2 && rows.amountLeft, JSON.stringify({a: rows.amountTop, l: rows.limiterTop}));
  ok('R4.4 page button ghost (border transparent)', rows.pageBtnBorder === 'rgba(0, 0, 0, 0)', rows.pageBtnBorder);
  ok('R4.5 current tint giữ', rows.currentBg === 'rgb(231, 241, 232)', rows.currentBg);
  await pm.locator('.toolbar-bottom').screenshot({ path: '/tmp/slp235-verify/r4-pager-mobile.png' }).catch(() => {});

  // Desktop regression: pager có border như design desktop
  const cd = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const pd = await cd.newPage();
  await pd.goto(BASE + '/gear/bags.html', { waitUntil: 'load' });
  await pd.waitForTimeout(1000);
  const d = await pd.evaluate(() => {
    const b = document.querySelector('.toolbar-bottom .lp-pager-page:not(.lp-pager-page-current)');
    return b ? getComputedStyle(b).borderTopColor : null;
  });
  ok('R4.6 desktop pager giữ border', d === 'rgb(209, 213, 219)', d);
  await pd.locator('.toolbar-bottom').screenshot({ path: '/tmp/slp235-verify/r4-pager-desktop.png' }).catch(() => {});

  console.log(results.join('\n'));
  console.log('\nALL:', results.every(r => r.startsWith('PASS')) ? 'PASS' : 'HAS FAILURES');
  await b.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
