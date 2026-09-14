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
    const btn = document.querySelector('#social-login-popup button.mfp-close');
    if (!btn) return 'no btn';
    const cs = getComputedStyle(btn);
    const rules = [];
    for (const ss of document.styleSheets) {
      let list; try { list = ss.cssRules; } catch (e) { continue; }
      const walk = (rs) => { for (const rule of rs) {
        if (rule.cssRules && rule.cssRules.length) { walk(rule.cssRules); continue; }
        if (!rule.selectorText || !rule.style) continue;
        try { if (btn.matches(rule.selectorText) && (rule.style.fontSize || rule.style.color || rule.style.background || rule.style.width)) {
          rules.push(rule.selectorText + ' => fs:' + rule.style.fontSize + ' color:' + rule.style.color + ' bg:' + (rule.style.background || rule.style.backgroundColor).slice(0, 30) + ' w:' + rule.style.width);
        } } catch (e) {}
      } };
      walk(list);
    }
    const d = document.querySelector('.wrap-modal-login');
    return {
      btn: { w: cs.width, h: cs.height, fs: cs.fontSize, color: cs.color, bg: cs.backgroundColor, radius: cs.borderRadius, pos: cs.position, top: cs.top, right: cs.right },
      rules: [...new Set(rules)].slice(0, 8),
      disappearAnim: (() => { d.classList.add('mfp-zoom-in-disappear'); const a = getComputedStyle(d).animationDuration + '|' + getComputedStyle(d).animationName; d.classList.remove('mfp-zoom-in-disappear'); return a; })()
    };
  });
  console.log(JSON.stringify(r, null, 1));
  await browser.close();
})();
