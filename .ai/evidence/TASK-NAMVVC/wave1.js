const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
  const results = [];
  const ok = (name, cond, extra='') => results.push(`${cond ? 'PASS' : 'FAIL'} ${name}${extra ? ' — ' + extra : ''}`);

  // --- T1: PLP grid render ---
  await page.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  const cards = page.locator('.hp-card');
  ok('T1 card count', await cards.count() === 12, 'count=' + await cards.count());
  const atc = page.locator('.hp-btn-atc').first();
  ok('T1 ATC visible', await atc.isVisible());
  const atcBox = await atc.boundingBox();
  ok('T1 ATC in actions row (in-flow, not overlay)', atcBox && atcBox.y > 300, 'y=' + (atcBox && Math.round(atcBox.y)));
  ok('T1 no gallery-debug', !(await page.content()).includes('gallery-debug'));
  // badge sale on one card
  ok('T1 sale badge present', await page.locator('.hp-card-badge-sale').count() >= 1);
  // icons
  ok('T1 quickview icon', await page.locator('.hp-card-quickview-btn').count() === 12, 'n=' + await page.locator('.hp-card-quickview-btn').count());
  ok('T1 compare icon', await page.locator('.hp-card-compare-btn').count() === 12, 'n=' + await page.locator('.hp-card-compare-btn').count());
  ok('T1 wishlist icon', await page.locator('.hp-card-wishlist-btn').count() === 12, 'n=' + await page.locator('.hp-card-wishlist-btn').count());
  // white card bg + radius
  const cardBg = await cards.first().evaluate(el => getComputedStyle(el).backgroundColor + ' / ' + getComputedStyle(el).borderRadius);
  ok('T1 card white + radius', /255/ .test(cardBg) && cardBg.includes('8px'), cardBg);
  await page.screenshot({ path: '/tmp/slp235-verify/w1-plp-grid.png', fullPage: false });

  // --- T2: ATC AJAX no-reload ---
  await page.evaluate(() => window.__slpNoNav = true);
  const cartCountBefore = await page.evaluate(() => {
    const el = document.querySelector('.counter-number, [data-role=counter] ');
    return el ? el.textContent.trim() : null;
  });
  await atc.click();
  await page.waitForTimeout(2500);
  ok('T2 no navigation', await page.evaluate(() => window.__slpNoNav === true));
  ok('T2 same URL', page.url().includes('/gear/bags.html'), page.url());
  // minicart counter updated
  const counterTxt = await page.evaluate(() => {
    const el = document.querySelector('.counter-number');
    return el ? el.textContent.trim() : 'MISSING';
  });
  ok('T2 cart counter > 0', counterTxt !== 'MISSING' && counterTxt !== '0' && counterTxt !== cartCountBefore, 'counter=' + counterTxt + ' before=' + cartCountBefore);
  // drawer opened per config
  const drawerVisible = await page.evaluate(() => {
    const d = document.querySelector('[data-async-dialog] dialog[open], dialog[open].wrap-modal, #cart-drawer dialog[open], dialog[open]');
    return d ? getComputedStyle(d).display !== 'none' : false;
  });
  ok('T2 drawer/dialog visible after ATC', drawerVisible);
  await page.screenshot({ path: '/tmp/slp235-verify/w1-atc-after.png' });

  // --- T3: homepage rails render with new card ---
  const page2 = await ctx.newPage();
  await page2.goto(BASE + '/', { waitUntil: 'networkidle' });
  ok('T3 homepage ATC count', await page2.locator('.hp-btn-atc').count() >= 20, 'n=' + await page2.locator('.hp-btn-atc').count());
  ok('T3 homepage cards', await page2.locator('.hp-card').count() >= 20, 'n=' + await page2.locator('.hp-card').count());
  const overflow2 = await page2.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  ok('T3 no horizontal overflow', overflow2 <= 0, 'delta=' + overflow2);
  await page2.screenshot({ path: '/tmp/slp235-verify/w1-home.png', fullPage: false });
  await page2.close();

  // --- T4: mobile 375 grid 2 cols ---
  const ctxM = await browser.newContext({ viewport: { width: 375, height: 812 } });
  const pageM = await ctxM.newPage();
  await pageM.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  const cols = await pageM.evaluate(() => {
    const list = document.querySelector('.product-item')?.parentElement;
    return list ? getComputedStyle(list).gridTemplateColumns.split(' ').length : 0;
  });
  ok('T4 mobile grid cols (current theme classes, design target 2)', cols === 2, 'cols=' + cols + ' (Wave 2 fixes container)');
  await pageM.screenshot({ path: '/tmp/slp235-verify/w1-mobile.png' });
  await ctxM.close();

  console.log(results.join('\n'));
  console.log('\nJS errors (pageerror only; ExtraFee fetch noise excluded):');
  console.log(errors.filter(e => e.startsWith('pageerror')).join('\n') || '(none)');
  console.log('\nALL:', results.every(r => r.startsWith('PASS')) ? 'PASS' : 'HAS FAILURES');
  await browser.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
