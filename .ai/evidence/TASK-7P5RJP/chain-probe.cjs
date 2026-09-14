const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2800);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1000);
  const chain = await page.evaluate(() => {
    const form = document.querySelector('#social-login-popup .mp-social-popup form');
    const out = [];
    let n = form;
    while (n && n.id !== 'social-login-popup') {
      const cs = getComputedStyle(n);
      out.push(`${(n.className || n.tagName).toString().slice(0, 45)} | w:${Math.round(n.getBoundingClientRect().width)} padL:${cs.paddingLeft} padR:${cs.paddingRight} mL:${cs.marginLeft} borderL:${cs.borderLeftWidth}`);
      n = n.parentElement;
    }
    return out;
  });
  chain.forEach(c => console.log(c));
  await browser.close();
})();
