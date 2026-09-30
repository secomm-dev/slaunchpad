const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const browser = await chromium.launch();
  const results = [];
  const errors = [];
  const ok = (n, c, x='') => results.push(`${c ? 'PASS' : 'FAIL'} ${n}${x ? ' — ' + x : ''}`);
  const gotoR = async (p, url) => { for (let i = 0; i < 3; i++) { try { await p.goto(url, { waitUntil: 'load', timeout: 30000 }); await p.waitForTimeout(800); return true; } catch (e) { console.error('goto retry', i, url); } } return false; };
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  page.on('pageerror', e => errors.push(e.message));

  // F1: list mode = 1 item / row
  await page.goto(BASE + '/gear/bags.html?product_list_mode=list', { waitUntil: 'networkidle' });
  const listCols = await page.evaluate(() => {
    const ul = document.querySelector('.products.wrapper ul');
    return { cols: getComputedStyle(ul).gridTemplateColumns.split(' ').length,
             firstW: Math.round(document.querySelector('.hp-card').getBoundingClientRect().width) };
  });
  ok('F1 list mode 1 cột', listCols.cols === 1, JSON.stringify(listCols));
  await page.screenshot({ path: '/tmp/slp235-verify/r1-list.png' });

  // F2: custom sort dropdown
  await page.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  const trigger = page.locator('.lp-sorter-control');
  ok('F2 sort trigger visible', await trigger.isVisible());
  ok('F2 menu đóng mặc định', await page.locator('.lp-sorter-menu').isHidden());
  await trigger.click();
  await page.waitForTimeout(300);
  ok('F2 menu mở sau click', await page.locator('.lp-sorter-menu').isVisible());
  const optCount = await page.locator('.lp-sorter-option').count();
  ok('F2 options render', optCount >= 3, 'n=' + optCount);
  const selectedCls = await page.locator('.lp-sorter-option[aria-selected="true"]').getAttribute('class');
  ok('F2 option selected bg xanh', selectedCls.includes('bg-hp-brand-dark'), selectedCls.slice(0, 60));
  await page.screenshot({ path: '/tmp/slp235-verify/r1-sort-open.png' });
  const priceOpt = page.locator('.lp-sorter-option', { hasText: 'Giá' }).first();
  await priceOpt.click();
  await page.waitForLoadState('networkidle');
  ok('F2 chọn Giá → navigate product_list_order', page.url().includes('product_list_order=price'), page.url());
  // menu value updated
  const newVal = await page.locator('.lp-sorter-value').textContent();
  ok('F2 trigger value cập nhật', newVal.trim() === 'Giá', newVal.trim());

  // F3: unrated product — no stars
  await gotoR(page, BASE + '/catalogsearch/result/?q=luna');
  const lunaCard = page.locator('.hp-card', { hasText: 'Luna Wall Fireplace' }).first();
  const ratingCount = await lunaCard.locator('.hp-card-rating svg').count();
  ok('F3 Luna (không rating) 0 sao', ratingCount === 0, 'svg=' + ratingCount);
  await lunaCard.screenshot({ path: '/tmp/slp235-verify/r1-luna-card.png' });
  // regression: rated products still show stars
  await page.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  const ratedCards = await page.locator('.hp-card').count();
  const ratedWithStars = await page.locator('.hp-card-rating').count();
  ok('F3 regression: card có rating vẫn hiện', ratedWithStars >= 1, `${ratedWithStars}/${ratedCards}`);

  // F5: pager styled
  const pageBtn = page.locator('.pages-item-next');
  const btnCls = await pageBtn.getAttribute('class');
  ok('F5 pager arrow class design', btnCls.includes('lp-pager-arrow') && btnCls.includes('rounded-md') && !btnCls.includes('rounded-3xl'), btnCls.slice(0, 80));
  // go to p=2 to see current page style
  await gotoR(page, BASE + '/gear/bags.html?p=2');
  const currentCls = await page.locator('.pages a[aria-current="page"]').getAttribute('class');
  ok('F5 current page bg tint', currentCls.includes('lp-pager-page-current'), currentCls.slice(0, 60));
  await page.locator('.pages').screenshot({ path: '/tmp/slp235-verify/r1-pager.png' });

  // F4: drag card gallery (homepage — card galleries ở rails/flash)
  const hp = await ctx.newPage();
  hp.on('pageerror', e => errors.push('hp: ' + e.message));
  await hp.goto(BASE + '/', { waitUntil: 'networkidle' });
  const gallery = hp.locator('[data-lp-card-slider]').first();
  const hasGallery = await gallery.count();
  if (hasGallery) {
    await gallery.scrollIntoViewIfNeeded();
    await hp.waitForTimeout(600);
    const track = gallery.locator('[data-track]').first();
    const before = await track.evaluate(el => el.scrollLeft);
    const tb = await track.boundingBox();
    // mouse drag trái → phải (nội dung trượt về slide đầu)
    await hp.mouse.move(tb.x + tb.width * 0.7, tb.y + tb.height / 2);
    await hp.mouse.down();
    for (let i = 1; i <= 10; i++) await hp.mouse.move(tb.x + tb.width * 0.7 - i * 25, tb.y + tb.height / 2);
    await hp.mouse.up();
    await hp.waitForTimeout(700);
    const after = await track.evaluate(el => el.scrollLeft);
    ok('F4 card gallery drag bằng chuột', after !== before, `scrollLeft ${before} -> ${after}`);
  } else {
    ok('F4 card gallery drag bằng chuột', false, 'no gallery on homepage');
  }

  console.log(results.join('\n'));
  console.log('\npageerrors:', errors.length ? errors.join(' | ') : '(none)');
  console.log('\nALL:', results.every(r => r.startsWith('PASS')) ? 'PASS' : 'HAS FAILURES');
  await browser.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
