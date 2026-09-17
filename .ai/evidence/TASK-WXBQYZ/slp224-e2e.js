/* TASK-WXBQYZ (SLP-224) — Playwright e2e: empty coupon submit → inline warning below Apply button.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node /tmp/slp224-e2e.js
 */
const { chromium } = require('playwright');

const BASE = 'http://slaunchpad.localhost';
const PDP = '/joust-duffle-bag.html';
const results = [];
const check = (name, ok, extra = '') => {
  results.push(`${ok ? 'PASS' : 'FAIL'} ${name}${extra ? ' — ' + extra : ''}`);
  if (!ok) process.exitCode = 1;
};

(async () => {
  const browser = await chromium.launch({
    headless: true,
    args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
  });

  // Open drawer, return page handle + request counter scoped to the coupon endpoint.
  const openDrawer = async (ctx, suffix, viewport) => {
    const page = await ctx.newPage();
    if (viewport) await page.setViewportSize(viewport);
    const posts = [];
    page.on('request', (r) => r.url().includes('quickcart/coupon/post') && posts.push(r.postData() || ''));
    const consoleErrors = [];
    page.on('console', (m) => m.type() === 'error' && consoleErrors.push(m.text()));
    await page.goto(`${BASE}${PDP}${suffix}`, { waitUntil: 'load' });
    await page.click('#product-addtocart-button');
    await page.waitForLoadState('load');
    await page.waitForTimeout(1500); // redirect back + customer-data sections settle
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('toggle-cart')));
    await page.waitForSelector('.coupon-form', { timeout: 15000 });
    await page.click('.coupon-form summary');
    await page.waitForSelector('#drawer_coupon_code', { state: 'visible', timeout: 10000 });
    return { page, posts, consoleErrors };
  };

  const msgLoc = (page) => page.locator('.coupon-form p.message');
  const btnLoc = (page) => page.locator('.coupon-form form button[type=submit]');

  // ---------- suite 1: vi store, desktop ----------
  {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const { page, posts, consoleErrors } = await openDrawer(ctx, '');
    const viText = 'Vui lòng nhập mã giảm giá';

    // T1: empty submit → warning visible, no POST
    await btnLoc(page).click();
    await page.waitForTimeout(400);
    const t1Visible = await msgLoc(page).isVisible();
    const t1Text = t1Visible ? (await msgLoc(page).innerText()).trim() : '';
    const t1Class = t1Visible ? (await msgLoc(page).getAttribute('class')) || '' : '';
    check('vi/T1 empty → warning visible', t1Visible);
    check('vi/T1 text đúng', t1Text === viText, t1Text);
    check('vi/T1 class = message warning', t1Class.includes('message') && t1Class.includes('warning'), t1Class);
    check('vi/T1 không có POST', posts.length === 0, `posts=${posts.length}`);

    // T2: whitespace-only → still warning, no POST
    await page.fill('#drawer_coupon_code', '   ');
    await btnLoc(page).click();
    await page.waitForTimeout(400);
    const t2Text = (await msgLoc(page).isVisible()) ? (await msgLoc(page).innerText()).trim() : '';
    check('vi/T2 whitespace → warning giữ nguyên', t2Text === viText, t2Text);
    check('vi/T2 không có POST', posts.length === 0, `posts=${posts.length}`);

    // T3: typing clears the message
    await page.fill('#drawer_coupon_code', 'ABC');
    await page.waitForTimeout(300);
    check('vi/T3 gõ chữ → message ẩn', !(await msgLoc(page).isVisible()));

    // position: message box sits BELOW the Apply button
    const below = await page.evaluate(() => {
      const b = document.querySelector('.coupon-form form button[type=submit]').getBoundingClientRect();
      const m = document.querySelector('.coupon-form p.message').getBoundingClientRect();
      return { below: m.top >= b.bottom, gapPx: Math.round(m.top - b.bottom) };
    });
    check('vi/position message ngay dưới button', below.below, `gap=${below.gapPx}px`);

    // T4: invalid code → server error path (red, POST fired)
    await page.fill('#drawer_coupon_code', 'INVALIDSLP224');
    await btnLoc(page).click();
    await page.waitForTimeout(2000);
    const t4Visible = await msgLoc(page).isVisible();
    const t4Class = t4Visible ? (await msgLoc(page).getAttribute('class')) || '' : '';
    const t4Text = t4Visible ? (await msgLoc(page).innerText()).trim() : '';
    check('vi/T4 mã sai → error hiển thị', t4Visible, t4Text.slice(0, 80));
    check('vi/T4 class = message error (không warning)', t4Class.includes('error') && !t4Class.includes('warning'), t4Class);
    check('vi/T4 đúng 1 POST', posts.length === 1, `posts=${posts.length}`);

    check('vi/console errors = 0', consoleErrors.length === 0, consoleErrors.join(' | ').slice(0, 200));
    await ctx.close();
  }

  // ---------- suite 2: en store ----------
  {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const { page, posts } = await openDrawer(ctx, '?___store=launchpad_en');
    await btnLoc(page).click();
    await page.waitForTimeout(400);
    const enVisible = await msgLoc(page).isVisible();
    const enText = enVisible ? (await msgLoc(page).innerText()).trim() : '';
    check('en/empty → warning EN đúng', enVisible && enText === 'Please enter a coupon code', enText);
    check('en/không có POST', posts.length === 0, `posts=${posts.length}`);
    await ctx.close();
  }

  // ---------- suite 3: vi mobile 375px ----------
  {
    const ctx = await browser.newContext({ viewport: { width: 375, height: 667 } });
    const { page } = await openDrawer(ctx, '', { width: 375, height: 667 });
    await btnLoc(page).click();
    await page.waitForTimeout(400);
    const mVisible = await msgLoc(page).isVisible();
    const mText = mVisible ? (await msgLoc(page).innerText()).trim() : '';
    const mBelow = await page.evaluate(() => {
      const b = document.querySelector('.coupon-form form button[type=submit]').getBoundingClientRect();
      const m = document.querySelector('.coupon-form p.message').getBoundingClientRect();
      return m.top >= b.bottom && m.left >= 0 && m.right <= 376;
    });
    check('mobile/empty → warning visible', mVisible && mText === 'Vui lòng nhập mã giảm giá', mText);
    check('mobile/message dưới button, không tràn viewport', mBelow);
    await ctx.close();
  }

  await browser.close();
  console.log(results.join('\n'));
})();
