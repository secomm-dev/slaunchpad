const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2800);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1200);
  const anim = await page.evaluate(() => {
    const d = document.querySelector('.wrap-modal-login');
    d.classList.add('mfp-zoom-in-disappear');
    const dur = getComputedStyle(d).animationDuration;
    d.classList.remove('mfp-zoom-in-disappear');
    return dur;
  });
  console.log('disappear duration:', anim, anim === '0.3s' ? 'OK (sync với closeTime 300ms)' : 'MISMATCH');
  // close flow timing: click × → dialog hidden sau ~300ms (không cắt ngang animation)
  const t0 = Date.now();
  await page.locator('#social-login-popup button.mfp-close').click();
  const hidden = await page.waitForFunction(() => {
    const d = document.querySelector('.wrap-modal-login');
    return d.classList.contains('hidden') || !d.open;
  }, { timeout: 3000 }).then(() => Date.now() - t0).catch(() => -1);
  console.log('hidden after', hidden, 'ms (expect ~300-450ms, không phải ~200ms cắt ngang)');
  // reopen để screenshot nút close
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1200);
  await page.screenshot({ path: '/tmp/sl160/close-btn-style.png', clip: { x: 900, y: 0, width: 380, height: 200 } });
  await browser.close();
})();
