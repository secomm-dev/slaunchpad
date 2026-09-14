const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2800);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1500);
  const frames = await page.evaluate(() => new Promise((resolve) => {
    const d = document.querySelector('.wrap-modal-login');
    const card = document.getElementById('social-login-popup');
    const marks = [];
    const t0 = performance.now();
    const snap = () => {
      const cr = card.getBoundingClientRect();
      const dr = d.getBoundingClientRect();
      marks.push({
        t: Math.round(performance.now() - t0),
        cls: d.className.split(' ').filter(c => /appear|disappear|hidden/.test(c)).join(',') || '-',
        cardTop: Math.round(cr.top), cardH: Math.round(cr.height),
        dlgTransform: getComputedStyle(d).transform.slice(0, 40),
        dlgOpacity: getComputedStyle(d).opacity,
        cardTransform: getComputedStyle(card).transform.slice(0, 40),
        open: d.open, disp: getComputedStyle(d).display
      });
    };
    snap();
    closeModal();
    snap();
    const iv = setInterval(snap, 70);
    setTimeout(() => { clearInterval(iv); resolve(marks); }, 1400);
  }));
  frames.forEach(f => console.log(JSON.stringify(f)));
  await browser.close();
})();
