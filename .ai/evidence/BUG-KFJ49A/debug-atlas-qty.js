const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  const errors = [];
  page.on('console', m => m.type() === 'error' && errors.push(m.text()));
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto('http://slaunchpad.localhost/atlas-pouf.html?___store=launchpad_en', { waitUntil: 'networkidle' });

  const info = await page.evaluate(async () => {
    const form = document.querySelector('#product_addtocart_form');
    const out = {};
    out.novalidate = form.getAttribute('novalidate');
    const qty = document.querySelector('input[name="qty"]');
    out.qtyInFormElements = !!form.elements.namedItem('qty');
    out.qtyRequired = qty?.required;
    out.qtyDataValidate = qty?.dataset.validate;
    out.qtyMin = qty?.getAttribute('min');

    qty.value = '0';
    qty.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise(r => setTimeout(r, 100));
    document.querySelector('#product-addtocart-button').click();
    await new Promise(r => setTimeout(r, 1200));
    out.urlAfter = location.pathname;
    out.messageUls = Array.from(document.querySelectorAll('ul.messages')).map(u => u.textContent.trim()).filter(Boolean);
    out.fieldWrappers = document.querySelectorAll('.field.field-reserved').length;
    out.qtyStillHere = !!document.querySelector('input[name="qty"]');
    return out;
  });
  console.log(JSON.stringify(info, null, 2));
  console.log('console errors:', errors.join(' | ').slice(0, 300));
  await browser.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
