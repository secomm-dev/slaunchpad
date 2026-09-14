const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2800);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1500);
  const r = await page.evaluate(() => {
    const title = document.querySelector('.social-login.block-container.authentication .social-login-title');
    const h2 = title?.querySelector('h2');
    const btn = document.querySelector('#bnt-social-login-authentication');
    const cs = (el) => el ? { bg: getComputedStyle(el).backgroundColor, color: getComputedStyle(el).color, radius: getComputedStyle(el).borderRadius } : null;
    return { title: cs(title), h2: cs(h2), btn: cs(btn) };
  });
  console.log(JSON.stringify(r, null, 1));
  await page.screenshot({ path: '/tmp/sl160/config-respect-1280.png' });
  await browser.close();
})();
