const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  for (const vp of [{ width: 1280, height: 900 }, { width: 770, height: 860 }, { width: 375, height: 812 }]) {
    const ctx = await browser.newContext({ viewport: vp });
    const page = await ctx.newPage();
    await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(2800);
    await page.evaluate(() => { try { onClick(); } catch (e) {} });
    await page.waitForTimeout(1200);
    const m = await page.evaluate(() => {
      const card = document.getElementById('social-login-popup');
      const d = document.querySelector('.wrap-modal-login');
      const cr = card.getBoundingClientRect();
      return {
        cardCenterY: Math.round(cr.top + cr.height / 2), viewportH: innerHeight,
        offCenter: Math.abs((cr.top + cr.height / 2) - innerHeight / 2),
        scroll: { sh: d.scrollHeight, ch: d.clientHeight, hasScroll: d.scrollHeight > d.clientHeight },
        cardW: Math.round(cr.width)
      };
    });
    console.log(vp.width + 'px:', JSON.stringify(m));
    await page.screenshot({ path: `/tmp/sl160/center-${vp.width}.png` });
    await ctx.close();
  }
  await browser.close();
})();
