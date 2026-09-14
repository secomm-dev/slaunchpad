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
    const form = document.querySelector('#social-login-popup .mp-social-popup form');
    const out = { id: form?.id, cls: form?.className, inline: form?.getAttribute('style'), w: Math.round(form.getBoundingClientRect().width), cssW: getComputedStyle(form).width, maxW: getComputedStyle(form).maxWidth, minW: getComputedStyle(form).minWidth };
    const rules = [];
    for (const ss of document.styleSheets) {
      let list; try { list = ss.cssRules; } catch (e) { continue; }
      const walk = (rs) => { for (const rule of rs) {
        if (rule.cssRules && rule.cssRules.length) { walk(rule.cssRules); continue; }
        if (!rule.selectorText || !rule.style) continue;
        try { if (form.matches(rule.selectorText) && (rule.style.width || rule.style.maxWidth || rule.style.minWidth || rule.style.flex)) {
          rules.push(rule.selectorText + ' => w:' + rule.style.width + ' maxW:' + rule.style.maxWidth + ' minW:' + rule.style.minWidth + ' flex:' + rule.style.flex);
        } } catch (e) {}
      } };
      walk(list);
    }
    out.mpPad = (() => { const m = document.querySelector(".mp-social-popup"); const cs = getComputedStyle(m); return cs.paddingTop + " " + cs.paddingRight + " " + cs.paddingBottom + " " + cs.paddingLeft; })(); out.titlePad = (() => { const t = document.querySelector(".social-login.block-container.authentication .social-login-title"); const cs = getComputedStyle(t); return cs.paddingLeft + " w:" + cs.width; })(); return out;
    return out;
  });
  console.log(JSON.stringify(r, null, 1));
  await browser.close();
})();
