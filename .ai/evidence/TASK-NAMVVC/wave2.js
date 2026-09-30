const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  const results = [];
  const ok = (n, c, x='') => results.push(`${c ? 'PASS' : 'FAIL'} ${n}${x ? ' — ' + x : ''}`);

  // Desktop
  await page.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  const cols = await page.evaluate(() => {
    const ul = document.querySelector('.products.wrapper ul');
    return ul ? getComputedStyle(ul).gridTemplateColumns.split(' ').length : 0;
  });
  ok('W2.1 grid 3 cols @1440', cols === 3, 'cols=' + cols);
  ok('W2.2 pager exists', await page.locator('.pager').count() >= 1);
  const modeVisible = await page.locator('.toolbar-products .modes').first().isVisible().catch(() => false);
  ok('W2.3 mode switcher visible desktop', modeVisible);
  const sortCtl = await page.locator('.lp-sorter-control').first().isVisible();
  ok('W2.4 sorter bordered control + label', sortCtl);
  ok('W2.5 filters btn hidden desktop', !(await page.locator('.lp-filters-toggle').first().isVisible()));
  ok('W2.6 sidebar Filter heading', (await page.locator('.lp-filter-title').first().textContent()).trim().length > 0);
  ok('W2.7 filter accordion count', await page.locator('.lp-filter-sidebar .filter-option').count() >= 1, 'n=' + await page.locator('.lp-filter-sidebar .filter-option').count());
  const skipHref = await page.locator('a[href="#product-list"]').count();
  ok('W2.8 skip-link target fixed', skipHref >= 1, 'n=' + skipHref);
  const panelFixed = await page.evaluate(() => {
    const el = document.querySelector('.lp-filter-panel');
    return el ? getComputedStyle(el).position : 'none';
  });
  ok('W2.9 panel static desktop', panelFixed !== 'fixed', panelFixed);
  await page.screenshot({ path: '/tmp/slp235-verify/w2-desktop.png' });

  // Filter item click still navigates (restyle-only behavior)
  const firstFilterLink = page.locator('.lp-filter-sidebar .lp-filter-item a').first();
  if (await firstFilterLink.count()) {
    const href = await firstFilterLink.getAttribute('href');
    ok('W2.10 filter item = real anchor (navigate-immediate)', !!href && href.length > 0, href);
  }

  // Mobile
  const cm = await browser.newContext({ viewport: { width: 375, height: 812 } });
  const pm = await cm.newPage();
  pm.on('pageerror', e => errors.push('m pageerror: ' + e.message));
  await pm.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  const mcols = await pm.evaluate(() => {
    const ul = document.querySelector('.products.wrapper ul');
    return ul ? getComputedStyle(ul).gridTemplateColumns.split(' ').length : 0;
  });
  ok('W2.11 mobile grid 2 cols', mcols === 2, 'cols=' + mcols);
  const fbtn = pm.locator('.lp-filters-toggle');
  ok('W2.12 Filters button visible mobile', await fbtn.isVisible());
  const modesHidden = await pm.locator('.toolbar-products .modes').first().isHidden();
  ok('W2.13 mode switcher hidden mobile', modesHidden);
  await fbtn.click();
  await pm.waitForTimeout(400);
  const panelOpen = await pm.evaluate(() => {
    const el = document.querySelector('.lp-filter-panel');
    return el ? { pos: getComputedStyle(el).position, cls: el.className.includes('lp-filter-panel-open') } : null;
  });
  ok('W2.14 panel opens fixed full-screen', panelOpen && panelOpen.pos === 'fixed' && panelOpen.cls, JSON.stringify(panelOpen));
  ok('W2.15 CTA visible', await pm.locator('.lp-filter-cta button').isVisible());
  const headerVisible = await pm.locator('.lp-filter-header .lp-filter-title').isVisible();
  ok('W2.16 panel header visible', headerVisible);
  await pm.screenshot({ path: '/tmp/slp235-verify/w2-mobile-panel.png' });
  await pm.locator('.lp-filter-cta button').click();
  await pm.waitForTimeout(300);
  const panelClosed = await pm.evaluate(() => !document.querySelector('.lp-filter-panel').className.includes('lp-filter-panel-open'));
  ok('W2.17 CTA closes panel', panelClosed);
  // mobile sort visible
  ok('W2.18 sorter visible mobile', await pm.locator('.lp-sorter-control').first().isVisible());
  await pm.screenshot({ path: '/tmp/slp235-verify/w2-mobile.png' });

  console.log(results.join('\n'));
  console.log('\npageerrors:', errors.length ? errors.join(' | ') : '(none)');
  console.log('\nALL:', results.every(r => r.startsWith('PASS')) ? 'PASS' : 'HAS FAILURES');
  await browser.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
