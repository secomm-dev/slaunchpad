const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 770, height: 860 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2800);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1000);
  const r = await page.evaluate(() => {
    const a = document.querySelector('#social-login-popup .actions-toolbar.social-btn a');
    if (!a) return 'no anchor';
    const cs = getComputedStyle(a);
    const title = document.querySelector('#mp-popup-social-content .block-title');
    const tcs = title ? getComputedStyle(title) : null;
    return {
      anchor: {
        w: Math.round(a.getBoundingClientRect().width),
        scrollW: a.scrollWidth, clientW: a.clientWidth,
        overflow: a.scrollWidth > a.clientWidth,
        font: cs.fontSize, family: cs.fontFamily.slice(0, 30), weight: cs.fontWeight,
        padding: cs.paddingLeft + '/' + cs.paddingRight, gap: cs.gap
      },
      column: Math.round(document.getElementById('mp-popup-social-content').getBoundingClientRect().width),
      title: title ? { text: title.textContent.trim().slice(0, 30), font: tcs.fontSize, weight: tcs.fontWeight, color: tcs.color, align: tcs.textAlign } : null
    };
  });
  console.log(JSON.stringify(r, null, 1));
  await browser.close();
})();
