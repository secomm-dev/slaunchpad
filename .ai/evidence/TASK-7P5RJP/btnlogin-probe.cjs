const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 375, height: 812 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2800);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1000);
  const r = await page.evaluate(() => {
    const btn = document.querySelector('#bnt-social-login-authentication');
    const toolbar = btn.closest('.actions-toolbar');
    const primary = btn.closest('.primary');
    const card = document.getElementById('social-login-popup');
    const fmt = (el) => el ? { cls: (el.className || '').toString().slice(0, 40), w: Math.round(el.getBoundingClientRect().width), x: Math.round(el.getBoundingClientRect().x), padL: getComputedStyle(el).paddingLeft, minW: getComputedStyle(el).minWidth, disp: getComputedStyle(el).display } : null;
    return { card: fmt(card), toolbar: fmt(toolbar), primary: fmt(primary), btn: fmt(btn) };
  });
  console.log(JSON.stringify(r, null, 1));
  await browser.close();
})();
