const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2800);
  await page.evaluate(() => { try { onClick(); } catch (e) {} });
  await page.waitForTimeout(1200);
  const r = await page.evaluate(() => {
    const out = {};
    // what is the white pill top-left?
    const card = document.getElementById('social-login-popup');
    const cr = card.getBoundingClientRect();
    out.pill = (() => {
      const el = document.elementFromPoint(cr.left + 60, cr.top + 20);
      if (!el) return null;
      const path = [];
      let n = el;
      while (n && n !== document.body && path.length < 5) { path.push(n.tagName + (n.id ? '#' + n.id : '') + (n.className && typeof n.className === 'string' ? '.' + n.className.split(' ').slice(0,3).join('.') : '')); n = n.parentElement; }
      return { tag: path.join(' < '), text: (el.textContent || '').trim().slice(0, 40), rect: JSON.stringify(el.getBoundingClientRect()) };
    })();
    // fake-email display
    const fe = document.querySelector('.block-container.fake-email');
    out.fakeEmail = fe ? { display: getComputedStyle(fe).display, rect: JSON.stringify(fe.getBoundingClientRect()) } : null;
    // what limits .control / input width?
    const input = document.querySelector('#social-login-popup .fieldset .field .control input.input-text');
    const control = input?.closest('.control');
    const field = input?.closest('.field');
    const w = (el) => el ? { w: Math.round(el.getBoundingClientRect().width), cssWidth: getComputedStyle(el).width } : null;
    out.input = w(input); out.control = w(control); out.field = w(field);
    out.formColumn = w(document.querySelector('#social-login-popup > .mp-social-popup'));
    // who sets control width
    const rules = [];
    for (const ss of document.styleSheets) {
      let list; try { list = ss.cssRules; } catch (e) { continue; }
      const walk = (rs) => { for (const rule of rs) {
        if (rule.cssRules && rule.cssRules.length) { walk(rule.cssRules); continue; }
        if (!rule.selectorText || !rule.style) continue;
        try { if (control && control.matches(rule.selectorText) && rule.style.width) rules.push(rule.selectorText + ' => width:' + rule.style.width); } catch (e) {}
      } };
      walk(list);
    }
    out.controlWidthRules = rules;
    return out;
  });
  console.log(JSON.stringify(r, null, 1));
  await browser.close();
})();
