const { chromium } = require('playwright');

const BASE = 'http://slaunchpad.localhost';

async function state(page) {
  return page.evaluate(() => {
    let cartCount = null, drawerOpen = null;
    try {
      cartCount = (JSON.parse(localStorage.getItem('mage-cache-storage') || '{}')?.cart?.items || []).length;
    } catch (e) {}
    const drawer = document.querySelector('#cart-drawer');
    if (drawer && window.Alpine) {
      try { drawerOpen = !!window.Alpine.$data(drawer).open; } catch (e) {}
    }
    return { url: location.href, cartCount, drawerOpen };
  });
}

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext()).newPage();

  const posts = [];
  page.on('request', r => {
    if (r.method() === 'POST' && r.url().includes('checkout/cart/add')) posts.push(r.url());
  });

  const results = {};

  // T1: PDP simple product — AJAX, no-nav, 1 POST, drawer opens
  await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'networkidle' });
  const before1 = await state(page);
  posts.length = 0;
  await page.click('#product-addtocart-button');
  await page.waitForTimeout(3000);
  const after1 = await state(page);
  results.T1_pdp_simple = {
    noNav: before1.url === after1.url,
    posts: posts.length,
    cartBefore: before1.cartCount,
    cartAfter: after1.cartCount,
    drawerOpen: after1.drawerOpen,
  };

  // T2: configurable missing option — inline validation, 0 POST
  await page.goto(BASE + '/chaz-kangeroo-hoodie.html', { waitUntil: 'networkidle' });
  posts.length = 0;
  await page.click('#product-addtocart-button');
  await page.waitForTimeout(1200);
  results.T2_config_missing_option = {
    posts: posts.length,
    noNav: (await state(page)).url.includes('chaz-kangeroo-hoodie'),
    fieldError: await page.evaluate(() => !!document.querySelector('.field-error')),
    msg: await page.evaluate(() => {
      const el = document.querySelector('.field-error .messages, .field-error .message, .messages .message');
      return el ? el.textContent.trim().slice(0, 90) : null;
    }),
  };

  // T3: configurable with ALL option groups selected — AJAX like simple
  await page.goto(BASE + '/chaz-kangeroo-hoodie.html', { waitUntil: 'networkidle' });
  posts.length = 0;
  const before3 = await state(page);
  const groups = await page.$$eval('#product_addtocart_form input[type="radio"][name^="super_attribute"]',
    els => [...new Set(els.map(e => e.name))]);
  for (const g of groups) {
    const id = await page.$eval(`#product_addtocart_form input[type="radio"][name="${g}"]`, e => e.id);
    await page.click(`label[for="${id}"]`);
  }
  await page.click('#product-addtocart-button');
  await page.waitForTimeout(3000);
  const after3 = await state(page);
  results.T3_config_with_option = {
    groupsSelected: groups.length,
    noNav: before3.url === after3.url,
    posts: posts.length,
    cartBefore: before3.cartCount,
    cartAfter: after3.cartCount,
    drawerOpen: after3.drawerOpen,
  };

  // T4: PLP card ATC regression — Monsoon bind path unchanged
  await page.goto(BASE + '/gear/bags.html', { waitUntil: 'networkidle' });
  posts.length = 0;
  const cardForms = await page.$$('form.product_addtocart_form');
  if (cardForms.length) {
    await page.$eval('form.product_addtocart_form', f => f.querySelector('button[data-addto="cart"]').click());
    await page.waitForTimeout(3000);
    results.T4_plp_card = { noNav: (await state(page)).url.includes('/gear/bags.html'), posts: posts.length, btnFound: true };
  } else {
    results.T4_plp_card = { btnFound: false, note: 'no form.product_addtocart_form on /gear/bags.html' };
  }

  console.log(JSON.stringify(results, null, 2));
  await browser.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
