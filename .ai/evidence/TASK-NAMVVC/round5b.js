const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const b = await chromium.launch();
  const results = [];
  const ok = (n, c, x='') => results.push(`${c ? 'PASS' : 'FAIL'} ${n}${x ? ' — ' + x : ''}`);
  const measure = async (vw) => {
    const ctx = await b.newContext({ viewport: { width: vw, height: 900 } });
    const p = await ctx.newPage();
    await p.goto(BASE + '/gear/bags.html', { waitUntil: 'load' });
    await p.waitForTimeout(800);
    const g = await p.evaluate(() => {
      const h1 = document.querySelector('.page-main .page-title');
      const wrap = h1.parentElement;
      const bc = document.querySelector('.breadcrumbs .items');
      const section = document.querySelector('#product-list');
      const toolbar = section ? section.querySelector('.toolbar-products') : null;
      const r = el => { const x = el.getBoundingClientRect(); return { top: Math.round(x.top + scrollY), bottom: Math.round(x.bottom + scrollY) }; };
      const bcR = r(bc), h1R = r(h1), wrapR = r(wrap), tR = toolbar ? r(toolbar) : null;
      const wrapCs = getComputedStyle(wrap);
      return {
        gapBcH1: h1R.top - bcR.bottom,
        h1H: h1R.bottom - h1R.top,
        gapH1Border: wrapR.bottom - h1R.bottom,
        hasBorder: wrapCs.borderBottomWidth + ' ' + wrapCs.borderBottomColor,
        gapBorderToolbar: tR ? tR.top - wrapR.bottom : null,
        bcH: bcR.bottom - bcR.top
      };
    });
    await ctx.close();
    return g;
  };
  const d = await measure(1440);
  ok('R5b.1 desktop gap breadcrumb→H1 = 12px', d.gapBcH1 === 12, d.gapBcH1);
  ok('R5b.2 H1 72px + gap H1→border = 24px', d.h1H === 72 && d.gapH1Border === 24, `${d.h1H}/${d.gapH1Border}`);
  ok('R5b.3 border #e5e7eb 1px', d.hasBorder.includes('rgb(229, 231, 235)') && d.hasBorder.includes('1px'), d.hasBorder);
  ok('R5b.4 gap border→toolbar = 40px', d.gapBorderToolbar === 40, d.gapBorderToolbar);
  const m = await measure(375);
  ok('R5b.5 mobile gap breadcrumb→H1 = 12px', m.gapBcH1 === 12, m.gapBcH1);
  console.log(results.join('\n'));
  console.log('\nALL:', results.every(r => r.startsWith('PASS')) ? 'PASS' : 'HAS FAILURES');
  await b.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
