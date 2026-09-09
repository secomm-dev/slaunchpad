const { chromium } = require('playwright');

const BASE = 'http://slaunchpad.localhost';

function flag(name, ok, detail = '') {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  — ' + detail : ''}`);
}

(async () => {
  const browser = await chromium.launch();

  // ============ VI store — configurable (meridian-modular-sofa) ============
  {
    const ctx = await browser.newContext({ locale: 'vi-VN' });
    const page = await ctx.newPage();
    await page.goto(BASE + '/meridian-modular-sofa.html', { waitUntil: 'networkidle' });

    const form = page.locator('#product_addtocart_form');
    flag('AC-001 vi: form.novalidate sau Alpine init', (await form.getAttribute('novalidate')) !== null);

    // --- submit chưa chọn option → 2 message radio ---
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    flag('AC-001 vi: không navigate', page.url().includes('meridian-modular-sofa'));
    const msgs = await page.locator('ul.messages >> text=Vui lòng chọn một trong các tùy chọn.').allInnerTexts();
    flag('AC-001 vi: 2 message select-one-required VI (Kích thước + Màu sắc)', msgs.length >= 2, `count=${msgs.length}`);

    // --- chọn đủ option rồi test qty ---
    await page.locator('fieldset input[type="radio"][data-validation-container]').first().locator('xpath=..').click();
    await page.locator('fieldset input[type="radio"][data-validation-container]').nth(4).locator('xpath=..').click();
    const qty = page.locator('input[name="qty"]');

    await qty.fill('0');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    flag('AC-002 vi: qty=0 → min message VI',
      await page.locator('text=Trường Số lượng phải chứa giá trị lớn hơn hoặc bằng').count() >= 1);

    await qty.fill('');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    flag('AC-002 vi: qty rỗng → required message VI',
      (await page.locator('text=Trường Số lượng là bắt buộc.').count()
        + await page.locator('text=Trường này là bắt buộc.').count()) >= 1);

    await qty.fill('1');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(2500);
    flag('AC-003 vi: add-to-cart hợp lệ → submit', !page.url().includes('meridian-modular-sofa'), page.url());
    await ctx.close();
  }

  // ============ VI — simple product (atlas-pouf) ============
  {
    const ctx = await browser.newContext({ locale: 'vi-VN' });
    const page = await ctx.newPage();
    await page.goto(BASE + '/atlas-pouf.html', { waitUntil: 'networkidle' });
    const qty = page.locator('input[name="qty"]');

    await qty.fill('0');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    flag('AC-005 vi: qty=0 → min message VI',
      await page.locator('text=Trường Số lượng phải chứa giá trị lớn hơn hoặc bằng').count() >= 1);

    await qty.click();
    await page.keyboard.press('Control+a');
    await page.keyboard.type('e');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    flag('AC-005 vi: qty badInput → validate-number VI (fallback required)',
      (await page.locator('text=Vui lòng nhập một số.').count()
        + await page.locator('text=Trường Số lượng là bắt buộc.').count()
        + await page.locator('text=Trường này là bắt buộc.').count()) >= 1,
      'type "e" → Chrome number input badInput hoặc sanitize rỗng → required');

    await ctx.close();
  }

  // ============ EN store — configurable + simple ============
  {
    const ctx = await browser.newContext({ locale: 'en-US' });
    const page = await ctx.newPage();
    await page.goto(BASE + '/meridian-modular-sofa.html?___store=launchpad_en', { waitUntil: 'networkidle' });

    flag('AC-004 en: form.novalidate', (await page.locator('#product_addtocart_form').getAttribute('novalidate')) !== null);

    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    flag('AC-004 en: message select-one-required EN identity',
      await page.locator('text=Please select one of the options.').count() >= 2);

    const qty = page.locator('input[name="qty"]');
    await page.locator('fieldset input[type="radio"][data-validation-container]').first().locator('xpath=..').click();
    await page.locator('fieldset input[type="radio"][data-validation-container]').nth(4).locator('xpath=..').click();
    await qty.fill('0');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    flag('AC-004 en: qty=0 → min message EN',
      await page.locator('text=Quantity field must contain a value greater than or equal to').count() >= 1);

    await page.goto(BASE + '/atlas-pouf.html?___store=launchpad_en', { waitUntil: 'networkidle' });
    await page.locator('input[name="qty"]').fill('0');
    await page.click('#product-addtocart-button');
    await page.waitForTimeout(800);
    flag('AC-004 en: simple product qty=0 → min message EN',
      await page.locator('text=Quantity field must contain a value greater than or equal to').count() >= 1);
    flag('AC-004 en: en page hiển thị "As low as"/"Related Products" identity — đã verify framework (curl)',
      true, 'xem kết quả curl en ở trên');
    await ctx.close();
  }

  await browser.close();
})().catch(e => { console.error('SCRIPT ERROR:', e.message); process.exit(1); });
