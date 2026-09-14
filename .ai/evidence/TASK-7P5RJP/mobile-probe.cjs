const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 375, height: 812 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2800);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1000);
  const chain = await page.evaluate(() => {
    const out = { card: Math.round(document.getElementById('social-login-popup').getBoundingClientRect().width), chain: [] };
    const form = document.querySelector('.wrap-modal-login .mp-social-popup form');
    let n = form;
    while (n && n.id !== 'social-login-popup') {
      out.chain.push(`${(n.className || n.tagName).toString().slice(0, 42)} w:${Math.round(n.getBoundingClientRect().width)} padL:${getComputedStyle(n).paddingLeft} padR:${getComputedStyle(n).paddingRight} minW:${getComputedStyle(n).minWidth}`);
      n = n.parentElement;
    }
    out.cardChain = out.chain.reverse();
    const input = form.querySelector('input.input-text');
    out.inputW = Math.round(input.getBoundingClientRect().width);
    return out;
  });
  console.log('card:', chain.card);
  chain.cardChain.forEach(c => console.log(c));
  console.log('input:', chain.inputW);
  await browser.close();
})();
