const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2800);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1000);
  const r = await page.evaluate(() => {
    const g = (sel) => { const el = document.querySelector(sel); if (!el) return null; const rc = el.getBoundingClientRect(); return { top: Math.round(rc.top), h: Math.round(rc.height), bottom: Math.round(rc.bottom) }; };
    const card = document.getElementById('social-login-popup');
    return {
      dialog: g('.wrap-modal-login'),
      popupTest: g('#popup_test'),
      test: g('#test'),
      card: g('#social-login-popup'),
      cardMargin: getComputedStyle(card).margin,
      testDisplay: getComputedStyle(document.getElementById('test')).display,
      testJustify: getComputedStyle(document.getElementById('test')).justifyContent, cardTransform: getComputedStyle(card).transform, cardInlineStyle: card.getAttribute('style')
    };
  });
  console.log(JSON.stringify(r, null, 1));
  await browser.close();
})();
