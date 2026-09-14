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
    const test = document.getElementById('test');
    const marks = [];
    const t0 = performance.now();
    const snap = (label) => {
      const bg = getComputedStyle(test).backgroundColor;
      const bd = getComputedStyle(d).getPropertyValue('backdrop') === '' ? null : null;
      marks.push(`${label || ''} @${Math.round(performance.now() - t0)}ms test-bg=${bg} dlgOpacity=${(+getComputedStyle(d).opacity).toFixed(2)} open=${d.open}`);
    };
    snap('open');
    closeModal();
    const iv = setInterval(() => {
      snap();
      if (!d.open || performance.now() - t0 > 1500) { clearInterval(iv); resolve(marks); }
    }, 70);
  }));
  frames.forEach(f => console.log(f));
  await browser.close();
})();
