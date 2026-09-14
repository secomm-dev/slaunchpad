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
    const out = { count: document.querySelectorAll('#social-login-popup').length };
    document.querySelectorAll('.wrap-modal-login').forEach(d => {
      const form = d.querySelector('.mp-social-popup form');
      if (!form) return;
      const chain = [];
      let n = form;
      while (n && n.id !== 'social-login-popup') {
        chain.push(`${(n.className || n.tagName).toString().slice(0, 40)} w:${Math.round(n.getBoundingClientRect().width)} padL:${getComputedStyle(n).paddingLeft}`);
        n = n.parentElement;
      }
      out.dialogChain = chain;
      out.inDialog = { form: Math.round(form.getBoundingClientRect().width) };
    });
    return out;
  });
  console.log(JSON.stringify(r, null, 1));
  await browser.close();
})();
