const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(3000);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1000);
  await page.evaluate(() => { try { createBtn(); } catch (e) {} });
  await page.waitForTimeout(600);
  const info = await page.evaluate(() => {
    const probe = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const cs = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return { w: Math.round(r.width), display: cs.display, float: cs.cssFloat, width: cs.width, paddingTop: cs.paddingTop };
    };
    const title = document.querySelector('.social-login.block-container.create .social-login-title h2');
    const tcs = title ? getComputedStyle(title) : null;
    return {
      popup: probe('#social-login-popup'),
      formsWrap: probe('.mp-social-popup'),
      socialContent: probe('#mp-popup-social-content'),
      titleH2: title ? { text: title.textContent.trim().slice(0, 30), color: tcs.color, fontSize: tcs.fontSize, display: tcs.display } : null,
      choice: probe('.field.choice')
    };
  });
  console.log(JSON.stringify(info, null, 1));
  await browser.close();
})();
