const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const browser = await chromium.launch();
  const results = [];
  const ok = (n, c, x='') => results.push(`${c ? 'PASS' : 'FAIL'} ${n}${x ? ' — ' + x : ''}`);
  // Mobile: sidebar hidden until opened
  const cm = await browser.newContext({ viewport: { width: 375, height: 812 } });
  const pm = await cm.newPage();
  await pm.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  ok('W2.19 sidebar hidden mobile (closed)', await pm.locator('.lp-filter-panel').first().isHidden());
  await pm.screenshot({ path: '/tmp/slp235-verify/w2b-mobile-closed.png' });
  await pm.locator('.lp-filters-toggle').click();
  await pm.waitForTimeout(400);
  ok('W2.20 sidebar visible when open', await pm.locator('.lp-filter-panel').first().isVisible());
  await pm.locator('.lp-filter-close').click();
  await pm.waitForTimeout(300);
  ok('W2.21 X closes panel', await pm.locator('.lp-filter-panel').first().isHidden());
  // Desktop unaffected
  const p = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await p.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  ok('W2.22 sidebar visible desktop', await p.locator('.lp-filter-panel').first().isVisible());
  console.log(results.join('\n'));
  console.log('ALL:', results.every(r => r.startsWith('PASS')) ? 'PASS' : 'HAS FAILURES');
  await browser.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
