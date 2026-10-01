const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const b = await chromium.launch();
  const results = [];
  const ok = (n, c, x='') => results.push(`${c ? 'PASS' : 'FAIL'} ${n}${x ? ' — ' + x : ''}`);

  // Desktop 1440
  const cd = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const pd = await cd.newPage();
  await pd.goto(BASE + '/gear/bags.html', { waitUntil: 'load' });
  await pd.waitForTimeout(800);
  const d = await pd.evaluate(() => {
    const h1 = document.querySelector('.page-main .page-title');
    const cs = h1 ? getComputedStyle(h1) : null;
    const bc = document.querySelector('.breadcrumbs');
    const bcItems = document.querySelector('.breadcrumbs .items');
    const bcCs = bcItems ? getComputedStyle(bcItems) : null;
    const firstItem = document.querySelector('.breadcrumbs .item a, .breadcrumbs .item');
    const sep = document.querySelector('.breadcrumbs .separator');
    const wrap = h1 ? h1.closest('.page-main > .container') : null;
    const wrapCs = wrap ? getComputedStyle(wrap) : null;
    return {
      h1Size: cs ? cs.fontSize : null,
      h1Weight: cs ? cs.fontWeight : null,
      h1Ls: cs ? cs.letterSpacing : null,
      h1Mb: cs ? cs.marginBottom : null,
      bcBg: bc ? getComputedStyle(bc).backgroundColor : null,
      bcShadow: bc ? getComputedStyle(bc).boxShadow : null,
      bcFont: bcCs ? bcCs.fontSize + '/' + bcCs.fontWeight : null,
      bcColor: firstItem ? getComputedStyle(firstItem).color : null,
      hasChevron: sep ? !!sep.querySelector('svg') : false,
      sepText: sep ? sep.textContent.trim() : null,
      wrapBorder: wrapCs ? wrapCs.borderBottomColor + ' ' + wrapCs.borderBottomWidth : null,
      wrapPb: wrapCs ? wrapCs.paddingBottom : null
    };
  });
  ok('R5.1 H1 60px bold desktop', d.h1Size === '60px' && d.h1Weight === '700', d.h1Size + '/' + d.h1Weight);
  ok('R5.2 H1 tracking -1px + mb-0', d.h1Ls === '-1px' && d.h1Mb === '0px', d.h1Ls + '/' + d.h1Mb);
  ok('R5.3 breadcrumb nền trắng không shadow', d.bcBg === 'rgba(0, 0, 0, 0)' && d.bcShadow === 'none', d.bcBg);
  ok('R5.4 breadcrumb 14px medium #101828', d.bcFont === '14px/500' && d.bcColor === 'rgb(16, 24, 40)', d.bcFont + ' ' + d.bcColor);
  ok('R5.5 separator = chevron svg', d.hasChevron && d.sepText === '', d.sepText || 'svg');
  ok('R5.6 wrapper border-bottom #e5e7eb + pb 24', d.wrapBorder === 'rgb(229, 231, 235) 1px' && d.wrapPb === '24px', d.wrapBorder + ' pb=' + d.wrapPb);
  await pd.screenshot({ path: '/tmp/slp235-verify/r5-desktop.png', clip: { x: 0, y: 120, width: 1440, height: 260 } });

  // Mobile 375 — H1 40px
  const cm = await b.newContext({ viewport: { width: 375, height: 812 } });
  const pm = await cm.newPage();
  await pm.goto(BASE + '/gear/bags.html', { waitUntil: 'load' });
  await pm.waitForTimeout(800);
  const m = await pm.evaluate(() => {
    const h1 = document.querySelector('.page-main .page-title');
    return h1 ? getComputedStyle(h1).fontSize : null;
  });
  ok('R5.7 H1 40px mobile', m === '40px', m);
  await pm.screenshot({ path: '/tmp/slp235-verify/r5-mobile.png', clip: { x: 0, y: 120, width: 375, height: 220 } });

  console.log(results.join('\n'));
  console.log('\nALL:', results.every(r => r.startsWith('PASS')) ? 'PASS' : 'HAS FAILURES');
  await b.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
