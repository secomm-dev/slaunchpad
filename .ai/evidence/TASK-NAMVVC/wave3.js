const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const browser = await chromium.launch();
  const results = [];
  const errors = [];
  const ok = (n, c, x='') => results.push(`${c ? 'PASS' : 'FAIL'} ${n}${x ? ' — ' + x : ''}`);
  const track = p => { p.on('pageerror', e => errors.push('pageerror: ' + e.message)); };

  // === 1. PLP grid vi ===
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage(); track(page);
  await page.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  const viStrings = ['Bộ lọc', 'Sắp xếp theo', 'Thêm vào giỏ', 'Còn hàng', 'Clear all'];
  for (const s of ['Bộ lọc', 'Sắp xếp theo', 'Thêm vào giỏ', 'Còn hàng']) {
    ok(`PLP vi "${s}"`, (await page.content()).includes(s));
  }
  ok('PLP vi "Xóa tất cả" (Clear all)', (await page.content()).includes('Xóa tất cả'));
  ok('PLP khong EN leak "Add to Cart"', !(await page.content()).includes('>Add to Cart<'));

  // === 2. Empty state (price filter yielding 0) ===
  await page.goto(BASE + '/gear/bags.html?price=74.99-75', { waitUntil: 'networkidle' });
  const emptyVisible = await page.locator('.lp-empty').isVisible().catch(() => false);
  ok('Empty state renders', emptyVisible);
  if (emptyVisible) {
    const emptyText = await page.locator('.lp-empty').textContent();
    ok('Empty state vi text', emptyText.includes('Không tìm thấy sản phẩm') && emptyText.includes('Xóa bộ lọc'), emptyText.slice(0, 80));
    ok('Empty state "Quay lại" (Back to %1)', emptyText.includes('Quay lại'));
    const clearHref = await page.locator('.lp-empty a').first().getAttribute('href');
    ok('Clear filters URL sạch (không query)', clearHref && !clearHref.includes('?'), clearHref);
  }
  await page.screenshot({ path: '/tmp/slp235-verify/w3-empty.png' });

  // === 3. Compare e2e (click → navigate theo vendor contract) ===
  await page.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  const cmpReq = page.waitForRequest(r => r.url().includes('product_compare/add'), { timeout: 5000 }).catch(() => null);
  await page.locator('.hp-card-compare-btn').first().click();
  const cmp = await cmpReq;
  ok('Compare POST fired', !!cmp, cmp ? cmp.url().split('/').slice(3,6).join('/') : 'no request');
  await page.waitForTimeout(2500);
  ok('Compare redirect về PLP (uenc)', page.url().includes('/gear/bags.html'), page.url());

  // === 4. Wishlist click (guest) — request fired, không crash ===
  const wishReq = page.waitForRequest(r => r.url().includes('wishlist/index/add'), { timeout: 5000 }).catch(() => null);
  await page.locator('.hp-card-wishlist-btn').first().click();
  const wsh = await wishReq;
  ok('Wishlist request fired', !!wsh);
  await page.waitForTimeout(1500);

  // === 5. Quickview e2e (regression SLP-157) ===
  const qvBefore = page.url();
  await page.locator('.hp-card-quickview-btn').first().click();
  await page.waitForTimeout(2500);
  const qvOpen = await page.evaluate(() => !!document.querySelector('dialog[open].wrap-modal, dialog[open]'));
  ok('Quickview modal opens', qvOpen);
  ok('Quickview không điều hướng', page.url() === qvBefore);
  await page.keyboard.press('Escape');

  // === 6. ATC từ card (regression Wave1 + QuickCart drawer) ===
  const counter0 = parseInt(await page.locator('[x-text="summaryCount"]').first().textContent());
  await page.locator('.hp-btn-atc').nth(1).click();
  await page.waitForTimeout(2500);
  const counter1 = parseInt(await page.locator('[x-text="summaryCount"]').first().textContent());
  ok('ATC +1 (2nd card)', counter1 === counter0 + 1, `${counter0} -> ${counter1}`);

  // === 7. Pager navigation ===
  const nextBtn = page.locator('.pager .pages-item-next:not([aria-disabled="true"])');
  if (await nextBtn.count()) {
    await nextBtn.click();
    await page.waitForLoadState('networkidle');
    ok('Pager next navigates', page.url().includes('p=2'), page.url());
  } else {
    ok('Pager next navigates', false, 'no next button');
  }

  // === 8. Homepage regression ===
  const hp = await ctx.newPage(); track(hp);
  await hp.goto(BASE + '/', { waitUntil: 'networkidle' });
  ok('Homepage ATC count', await hp.locator('.hp-btn-atc').count() >= 20, 'n=' + await hp.locator('.hp-btn-atc').count());
  const hpOverflow = await hp.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  ok('Homepage no overflow-x', hpOverflow <= 0, 'delta=' + hpOverflow);
  await hp.screenshot({ path: '/tmp/slp235-verify/w3-home.png' });
  await hp.close();

  // === 9. PDP regression (related slider + ATC AJAX SLP-264) ===
  const pdp = await ctx.newPage(); track(pdp);
  await pdp.goto(BASE + '/push-it-messenger-bag.html', { waitUntil: 'networkidle' });
  ok('PDP loads', (await pdp.title()).length > 0, await pdp.title());
  const pdpAtcForm = await pdp.locator('form.product_addtocart_form, #product_addtocart_form').count();
  ok('PDP ATC form present', pdpAtcForm >= 1);
  await pdp.close();

  // === 10. Search page (card + filter pipeline) ===
  const sp = await ctx.newPage(); track(sp);
  await sp.goto(BASE + '/catalogsearch/result/?q=bag', { waitUntil: 'networkidle' });
  ok('Search loads 200', (await sp.title()).length > 0);
  ok('Search cards render', await sp.locator('.hp-card').count() >= 1, 'n=' + await sp.locator('.hp-card').count());
  ok('Search filter sidebar', await sp.locator('.lp-filter-sidebar').count() >= 1);
  await sp.close();

  // === 11. Mobile card visual ===
  const cm = await browser.newContext({ viewport: { width: 375, height: 812 } });
  const pm = await cm.newPage(); track(pm);
  await pm.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  const atcBox = await pm.locator('.hp-btn-atc').first().boundingBox();
  ok('Mobile ATC trong viewport (không tràn)', atcBox && atcBox.x >= 0 && (atcBox.x + atcBox.width) <= 375, JSON.stringify(atcBox && {x: Math.round(atcBox.x), w: Math.round(atcBox.width)}));
  await pm.locator('.hp-card').first().screenshot({ path: '/tmp/slp235-verify/w3-mobile-card.png' });
  await cm.close();

  console.log(results.join('\n'));
  console.log('\npageerrors:', errors.length ? '\n' + errors.join('\n') : '(none)');
  console.log('\nALL:', results.every(r => r.startsWith('PASS')) ? 'PASS' : 'HAS FAILURES');
  await browser.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
