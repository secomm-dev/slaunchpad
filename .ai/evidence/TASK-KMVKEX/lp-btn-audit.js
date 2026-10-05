const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await p.goto('http://slaunchpad.localhost/', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1500);
  const btns = p.locator('[data-content-type="button-item"] a.pagebuilder-button-primary, [data-content-type="button-item"] a.pagebuilder-button-secondary');
  const n = await btns.count();
  console.log('total section CTAs:', n);
  for (let i = 0; i < n; i++) {
    const el = btns.nth(i);
    const info = await el.evaluate(a => {
      const cs = getComputedStyle(a);
      // heading gần nhất phía trên trong DOM
      let h = a.closest('[data-content-type="row"]');
      let heading = '';
      for (let k = 0; k < 3 && h; k++) {
        const cand = h.querySelector('h2, h3, [data-content-type="heading"]');
        if (cand && cand.innerText.trim()) { heading = cand.innerText.trim().slice(0, 40); break; }
        h = h.previousElementSibling;
      }
      const row = a.closest('[data-content-type="row"]');
      const rowVars = row ? getComputedStyle(row) : null;
      return {
        text: a.innerText.trim().slice(0, 30),
        href: a.getAttribute('href'),
        type: a.classList.contains('pagebuilder-button-secondary') ? 'SECONDARY' : 'primary',
        bg: cs.backgroundColor, color: cs.color,
        radius: cs.borderRadius, h: Math.round(a.getBoundingClientRect().height),
        rowLpCtaBg: rowVars ? rowVars.getPropertyValue('--lp-cta-bg').trim() : '?',
        section: heading
      };
    });
    console.log(JSON.stringify(info));
  }
  await b.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
