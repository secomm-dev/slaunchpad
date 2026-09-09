const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';

function flag(name, ok, detail = '') {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  — ' : ''}${detail}`);
}

(async () => {
  const browser = await chromium.launch();
  let pass = 0, fail = 0;
  const check = (name, ok, detail) => { ok ? pass++ : fail++; flag(name, ok, detail); };

  // ================= VI — configurable (meridian) =================
  {
    const ctx = await browser.newContext({ locale: 'vi-VN', viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto(BASE + '/meridian-modular-sofa.html', { waitUntil: 'networkidle' });
    const form = page.locator('#product_addtocart_form');
    check('AC-001 vi: form.novalidate sau Alpine init', (await form.getAttribute('novalidate')) !== null);

    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    check('AC-001 vi: không navigate', page.url().includes('meridian-modular-sofa'));
    const msgs = await page.locator('div[id^="attribute-messages-"] ul.messages li').allInnerTexts();
    check('AC-001 vi: 2 message radio VI dưới fieldset', msgs.length === 2
      && msgs.every(t => t.includes('Vui lòng chọn một trong các tùy chọn.')), 'count=' + msgs.length);

    // chọn option + qty 0 → min message dưới row
    await page.locator('fieldset input[type="radio"][data-validation-container]').first().locator('xpath=..').click();
    await page.locator('fieldset input[type="radio"][data-validation-container]').nth(4).locator('xpath=..').click();
    const qty = page.locator('input[name="qty"]');
    await qty.fill('0');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);

    const g = await page.evaluate(() => {
      const input = document.querySelector('input[name="qty"]');
      const btn = document.querySelector('#product-addtocart-button');
      const container = document.getElementById('qty-messages-' + input.id.match(/\d+/)[0]);
      const ul = container?.querySelector('ul.messages');
      const row = input.closest('.flex.gap-2').getBoundingClientRect();
      const i = input.getBoundingClientRect(), b = btn.getBoundingClientRect();
      const u = ul?.getBoundingClientRect();
      return {
        wrapper: !!input.closest('.field.field-reserved'),
        delta: Math.round((b.y - i.y) * 100) / 100,
        rowH: Math.round(row.height),
        msgIn: !!ul,
        msgBelowRow: ul ? (ul.getBoundingClientRect().y + window.scrollY) >= (row.bottom + window.scrollY) : false,
        msgW: u ? Math.round(u.width) : 0,
        msgLi: ul?.querySelector('li')?.innerText || '',
      };
    });
    check('AC-002 vi: qty=0 → min message VI', g.msgLi.includes('phải chứa giá trị lớn hơn hoặc bằng'), g.msgLi);
    check('UI-01 vi: không runtime wrapper quanh qty', g.wrapper === false);
    check('UI-02 vi: input thẳng hàng button (delta=0)', Math.abs(g.delta) < 0.5, 'delta=' + g.delta);
    check('UI-03 vi: row không giãn (h≈42)', g.rowH <= 44, 'h=' + g.rowH);
    check('UI-04 vi: message dưới row, full width', g.msgIn && g.msgBelowRow && g.msgW >= 400, 'w=' + g.msgW);

    // screenshot khu vực fix (vi)
    await page.evaluate(() => {
      const input = document.querySelector('input[name="qty"]');
      document.getElementById('qty-messages-' + input.id.match(/\d+/)[0])
        ?.scrollIntoView({ block: 'center' });
    });
    await page.waitForTimeout(300);
    await page.screenshot({ path: '/tmp/pw-cal/slp183-fixed-vi.png', clip: { x: 640, y: 100, width: 640, height: 560 } });

    // qty rỗng → required
    await qty.fill('');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    const emptyMsg = await page.evaluate(() => {
      const input = document.querySelector('input[name="qty"]');
      const c = document.getElementById('qty-messages-' + input.id.match(/\d+/)[0]);
      return c?.querySelector('ul.messages li')?.innerText || '';
    });
    check('AC-002 vi: qty rỗng → required message VI',
      emptyMsg.includes('là bắt buộc'), emptyMsg);

    // AC-003: add hợp lệ → item vào cart (assert message success + cart page)
    await qty.fill('1');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(2500);
    const successMsg = await page.locator('.page.messages').innerText().catch(() => '');
    check('AC-003 vi: add-to-cart thành công (message success)',
      successMsg.includes('giỏ hàng của bạn'), successMsg.slice(0, 80));
    await page.goto(BASE + '/checkout/cart/', { waitUntil: 'networkidle' });
    const cartHasItem = await page.locator('input[name^="cart["]').count()
      + await page.locator('.cart td.item, li.item-product, .cart-item').count();
    check('AC-003 vi: cart chứa item', cartHasItem >= 1, 'match=' + cartHasItem);
    check('AC-001 vi: console không pageerror mới', errors.length === 0, errors[0] || '');
    await ctx.close();
  }

  // ================= VI — simple (atlas-pouf) =================
  {
    const ctx = await browser.newContext({ locale: 'vi-VN' });
    const page = await ctx.newPage();
    await page.goto(BASE + '/atlas-pouf.html', { waitUntil: 'networkidle' });
    const qty = page.locator('input[name="qty"]');
    await qty.fill('0');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    const msg = await page.evaluate(() => {
      const input = document.querySelector('input[name="qty"]');
      return document.getElementById('qty-messages-' + input.id.match(/\d+/)[0])
        ?.querySelector('ul.messages li')?.innerText || '';
    });
    check('AC-005 vi: simple qty=0 → min message VI trong container', msg.includes('phải chứa giá trị'), msg);
    await ctx.close();
  }

  // ================= EN store =================
  {
    const ctx = await browser.newContext({ locale: 'en-US', viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    await page.goto(BASE + '/meridian-modular-sofa.html?___store=launchpad_en', { waitUntil: 'networkidle' });
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    const enRadio = await page.locator('div[id^="attribute-messages-"] ul.messages li').allInnerTexts();
    check('AC-004 en: radio message EN ×2', enRadio.length === 2
      && enRadio.every(t => t.includes('Please select one of the options.')), 'count=' + enRadio.length);
    await page.locator('fieldset input[type="radio"][data-validation-container]').first().locator('xpath=..').click();
    await page.locator('fieldset input[type="radio"][data-validation-container]').nth(4).locator('xpath=..').click();
    await page.locator('input[name="qty"]').fill('0');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    const enQty = await page.evaluate(() => {
      const input = document.querySelector('input[name="qty"]');
      const c = document.getElementById('qty-messages-' + input.id.match(/\d+/)[0]);
      return c?.querySelector('ul.messages li')?.innerText || '';
    });
    check('AC-004 en: qty min message EN trong container', enQty.includes('greater than or equal to'), enQty);
    await ctx.close();
  }

  console.log(`\n=== TỔNG: ${pass} PASS / ${fail} FAIL ===`);
  await browser.close();
  process.exit(fail > 0 ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR:', e.message); process.exit(1); });
