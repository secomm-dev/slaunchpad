// BUG-KATJXW CR2 (SLP-269): nút "→" của .lp-promo-card giữ hình tròn 36×36 viền trắng
// với mọi PB button_type (Admin sửa link → class <a> bị PB thay bằng button_type).
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.PW_CHROME || undefined, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  let fail = 0;
  const measure = (as) => as.map((a) => {
    const cs = getComputedStyle(a); const b = a.getBoundingClientRect(); const after = getComputedStyle(a, '::after').content;
    return { cls: a.className.split(' ')[0], href: a.getAttribute('href'), w: Math.round(b.width), h: Math.round(b.height), radius: parseFloat(cs.borderTopLeftRadius) >= 18, border: cs.borderTopWidth + ' ' + cs.borderTopColor, bg: cs.backgroundColor, color: cs.color, after, text: a.textContent.trim() };
  });
  const ok = (r) => r.w === 36 && r.h === 36 && r.radius && r.border === '1px rgb(255, 255, 255)' && r.bg === 'rgba(0, 0, 0, 0)' && r.color === 'rgb(255, 255, 255)' && (r.after === 'none' || r.after === 'normal') && r.text === '→';
  for (const width of [375, 1440]) {
    const page = await (await browser.newContext({ viewport: { width, height: 900 } })).newPage();
    const errs = []; page.on('pageerror', (e) => errs.push(String(e).slice(0, 120)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'load', timeout: 60000 });
    await page.waitForTimeout(800);
    const live = await page.$$eval('.lp-promo-card [data-element="link"]', measure);
    live.forEach((r, i) => { const pass = ok(r); fail += pass ? 0 : 1; console.log(`${width} ${pass ? 'PASS' : 'FAIL'} live card ${i + 1} ${JSON.stringify(r)}`); });
    for (const type of ['pagebuilder-button-primary', 'pagebuilder-button-secondary', 'pagebuilder-button-link']) {
      const sim = await page.$$eval('.lp-promo-card [data-element="link"]', (as, t) => { as.forEach((a) => { a.className = t; }); return null; }, type);
      const res = await page.$$eval('.lp-promo-card [data-element="link"]', measure);
      const pass = res.every(ok); fail += pass ? 0 : 1;
      console.log(`${width} ${pass ? 'PASS' : 'FAIL'} simulated ${type} ${JSON.stringify(res[0])}`);
    }
    const hover = await (async () => {
      const a = page.locator('.lp-promo-card [data-element="link"]').first();
      await a.scrollIntoViewIfNeeded(); await a.hover(); await page.waitForTimeout(300);
      return a.evaluate((el) => getComputedStyle(el).backgroundColor);
    })();
    const hp = hover === 'rgba(255, 255, 255, 0.1)'; fail += hp ? 0 : 1;
    console.log(`${width} ${hp ? 'PASS' : 'FAIL'} hover bg ${hover}`);
    if (width === 1440) await page.reload({ waitUntil: 'load' }).then(() => page.waitForTimeout(800)).then(() => page.locator('.lp-promo-card').nth(1).scrollIntoViewIfNeeded()).then(() => page.locator('[data-content-type="column-line"]:has(> .lp-promo-card)').screenshot({ path: `${__dirname}/promo-buttons-1440.png` }));
    fail += errs.length ? 1 : 0;
    console.log(`${width} ${errs.length ? 'FAIL' : 'PASS'} 0 pageerror ${JSON.stringify(errs)}`);
  }
  await browser.close();
  console.log(fail ? `RESULT: ${fail} FAIL` : 'RESULT: ALL PASS');
  process.exit(fail ? 1 : 0);
})();
