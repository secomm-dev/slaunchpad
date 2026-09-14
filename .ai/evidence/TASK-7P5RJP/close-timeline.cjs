const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2800);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1200);
  const timeline = await page.evaluate(() => new Promise((resolve) => {
    const d = document.querySelector('.wrap-modal-login');
    const marks = [];
    const t0 = performance.now();
    const snap = (label) => marks.push(`${label} @${Math.round(performance.now() - t0)}ms cls=[${d.className.split(' ').filter(c => /appear|disappear|hidden/.test(c)).join(',') || '-'}] open=${d.open} disp=${getComputedStyle(d).display}`);
    snap('before');
    closeModal();
    snap('after close()');
    const iv = setInterval(() => {
      snap('poll');
      if (marks.length > 1 && (d.classList.contains('hidden') || !d.open)) {
        clearInterval(iv);
        resolve(marks);
      }
      if (performance.now() - t0 > 4000) { clearInterval(iv); resolve([...marks, 'TIMEOUT']); }
    }, 60);
  }));
  timeline.forEach(t => console.log(t));
  await browser.close();
})();
