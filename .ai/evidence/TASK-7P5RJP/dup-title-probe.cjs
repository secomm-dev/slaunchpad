const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2800);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1200);
  const r = await page.evaluate(() => {
    const titles = [...document.querySelectorAll('#social-login-popup .social-login-title')];
    return {
      titleCount: titles.length,
      html: titles.map(t => t.outerHTML.slice(0, 200)),
      h2s: [...document.querySelectorAll('#social-login-popup .social-login-title h2')].map(h => {
        const rc = h.getBoundingClientRect();
        return { cls: h.className, text: h.textContent.trim(), x: Math.round(rc.x), y: Math.round(rc.y), color: getComputedStyle(h).color };
      })
    };
  });
  console.log(JSON.stringify(r, null, 1));
  await browser.close();
})();
