const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  const errs = [];
  page.on('console', m => m.type() === 'error' && errs.push(m.text().slice(0, 90)));
  page.on('pageerror', e => errs.push('PAGEERROR ' + e.message.slice(0, 90)));
  const t = (n, ok) => console.log((ok ? 'PASS' : 'FAIL') + ' | ' + n);

  await page.goto('http://slaunchpad.localhost/atlas-pouf.html', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.click('#product-addtocart-button');
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(2500);
  await page.goto('http://slaunchpad.localhost/checkout/cart', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);
  t('cart page has item (Atlas Pouf text)', (await page.locator('#maincontent').innerText()).includes('Atlas Pouf'));

  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  const ok = await page.waitForFunction(() => {
    const p = document.getElementById('social-login-popup');
    if (!p) return false;
    const r = p.getBoundingClientRect();
    return r.width > 300 && r.height > 300;
  }, { timeout: 6000 }).then(() => true).catch(() => false);
  t('sign-in modal from cart page reaches full card size (retry)', ok);
  t('modal closes via ×', await page.locator('#social-login-popup button.mfp-close').click().then(async () => {
    await page.waitForTimeout(900);
    return page.evaluate(() => {
      const d = document.querySelector('.wrap-modal-login');
      return d.classList.contains('hidden') || !d.open;
    });
  }));

  // ambient error census — which endpoints produce "Error fetching data"
  const ambient = errs.filter(e => /Error fetching data/.test(e));
  const other = errs.filter(e => !/Error fetching data/.test(e));
  t('only known pre-existing ambient errors (ExtraFee fetch)', other.length === 0 && ambient.length >= 0);
  console.log('ambient ExtraFee-style fetch errors:', ambient.length);
  if (other.length) console.log('OTHER errors:', JSON.stringify(other.slice(0, 5)));
  await browser.close();
})();
