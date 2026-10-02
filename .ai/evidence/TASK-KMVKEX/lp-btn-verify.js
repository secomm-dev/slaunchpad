const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(String(e).slice(0, 100)));
  await p.goto('http://slaunchpad.localhost/', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1500);

  const sec = p.locator('[data-content-type="button-item"] a.pagebuilder-button-secondary:not(.lp-promo-card a)');
  const n = await sec.count();
  console.log('secondary CTAs:', n);
  for (let i = 0; i < n; i++) {
    const el = sec.nth(i);
    await el.scrollIntoViewIfNeeded();
    await p.waitForTimeout(300);
    const def = await el.evaluate(a => {
      const cs = getComputedStyle(a);
      return { text: a.innerText.trim().slice(0, 25), bg: cs.backgroundColor, color: cs.color, radius: cs.borderRadius, h: Math.round(a.getBoundingClientRect().height), shadow: cs.boxShadow.slice(0, 30) };
    });
    await el.hover();
    await p.waitForTimeout(350);
    const hov = await el.evaluate(a => {
      const cs = getComputedStyle(a);
      return { bg: cs.backgroundColor, shadow: cs.boxShadow.slice(0, 60), color: cs.color };
    });
    console.log('DEF :', JSON.stringify(def));
    console.log('HOVER:', JSON.stringify(hov));
    pass = def.bg === 'rgb(231, 241, 232)' && def.color === 'rgb(53, 87, 58)' && hov.bg === 'rgb(169, 204, 174)';
    console.log(pass ? 'PASS' : 'FAIL', '\n');
  }
  // primary không đổi: hero + rails + blog
  for (const t of ['Xem Ngay', 'Bộ Sưu Tập', 'Xem tất cả']) {
    const el = p.locator(`[data-content-type="button-item"] a.pagebuilder-button-primary`, { hasText: t }).first();
    const bg = await el.evaluate(a => getComputedStyle(a).backgroundColor).catch(() => 'n/a');
    console.log('primary', t, '→', bg);
  }
  const ovf = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  console.log('overflow:', ovf, '| pageerrors:', errs.length);
  await b.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
