const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2500);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(800);
  await page.evaluate(() => { try { createBtn(); } catch (e) {} });
  await page.waitForTimeout(500);
  const rules = await page.evaluate(() => {
    const el = document.querySelector('#social-login-popup .field.choice');
    const out = [];
    for (const ss of document.styleSheets) {
      let list; try { list = ss.cssRules; } catch (e) { continue; }
      const walk = (rules) => {
        for (const r of rules) {
          if (r.cssRules && r.cssRules.length) { walk(r.cssRules); continue; }
          if (!r.selectorText || !r.style) continue;
          try { if (el.matches(r.selectorText) && r.style.display) out.push(r.selectorText + ' => display:' + r.style.display); } catch (e) {}
        }
      };
      walk(list);
    }
    return out;
  });
  console.log(rules.join('\n'));
  await browser.close();
})();
