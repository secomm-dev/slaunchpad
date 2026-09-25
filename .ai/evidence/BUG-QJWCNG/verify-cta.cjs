// BUG-QJWCNG: CTA section homepage khớp Figma 2-10 (size XL + arrow trailing + variant theo section).
// Chạy: node verify-cta.cjs (Chrome 150 — xem memory local-playwright).
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const W = 'rgb(255, 255, 255)', B500 = 'rgb(88, 143, 96)', B600 = 'rgb(69, 116, 76)', B700 = 'rgb(53, 87, 58)', B50 = 'rgb(244, 249, 244)', B900 = 'rgb(41, 62, 45)', T = 'rgba(255, 255, 255, 0)';
const want = {
  'Explore all#1': { bg: B500, color: W, hover: B600, ring: false },
  'Explore all#2': { bg: B500, color: W, hover: B600, ring: false },
  'Go to Collection': { bg: B600, color: W, hover: B700, ring: false },
  'Explore': { bg: T, color: B900, hover: B50, ring: true },
  'Shop in-stock sofa': { bg: W, color: B900, hover: B50, ring: false },
  'View all post': { bg: B500, color: W, hover: B600, ring: false },
};
(async () => {
  const browser = await chromium.launch({ executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome', args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  let fail = 0;
  const check = (label, pass, info) => { fail += pass ? 0 : 1; console.log(`${pass ? 'PASS' : 'FAIL'} ${label} ${info}`); };
  for (const width of [1440, 375]) {
    const page = await (await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 2 })).newPage();
    const errs = []; page.on('pageerror', (e) => errs.push(String(e).slice(0, 120)));
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'load', timeout: 60000 });
    await page.waitForTimeout(800);
    const links = page.locator('main [data-content-type="button-item"] a');
    const n = await links.count(); const seen = {};
    for (let i = 0; i < n; i++) {
      const a = links.nth(i);
      if (await a.evaluate((e) => !!e.closest('[data-content-type="slide"], .lp-promo-card'))) continue;
      const m = await a.evaluate((e) => {
        const cs = getComputedStyle(e), af = getComputedStyle(e, '::after'), b = e.getBoundingClientRect();
        const r = document.createRange(); r.selectNodeContents(e); const tb = r.getBoundingClientRect();
        return { txt: e.textContent.trim(), h: Math.round(b.height), font: `${cs.fontSize}/${cs.lineHeight}/${cs.fontWeight}`, ff: cs.fontFamily.split(',')[0], pad: cs.padding, r: cs.borderTopLeftRadius, gap: cs.gap, bg: cs.backgroundColor, color: cs.color, shadow: cs.boxShadow, afPos: af.position, afW: af.width, textRight: Math.round(tb.right), btnRight: Math.round(b.right), oneLine: b.height < 60 };
      });
      seen[m.txt] = (seen[m.txt] || 0) + 1;
      const key = m.txt === 'Explore all' ? `Explore all#${seen[m.txt]}` : m.txt; const w = want[key];
      const font = width >= 768 ? '16px/24px/500' : '14px/20px/500';
      check(`${width} ${key} size`, m.h === 48 && m.font === font && m.ff === 'Inter' && m.pad === '12px 22px 12px 24px' && m.r === '6px' && m.gap === '6px' && m.oneLine, JSON.stringify({ h: m.h, font: m.font, ff: m.ff, pad: m.pad, r: m.r, gap: m.gap }));
      // arrow in flow: 16px, static, text ends before (btnRight - padEnd 22 - arrow 16 - gap 6) + 1
      check(`${width} ${key} arrow`, m.afPos === 'static' && m.afW === '16px' && m.textRight <= m.btnRight - 22 - 16 - 6 + 1, JSON.stringify({ afPos: m.afPos, afW: m.afW, textRight: m.textRight, btnRight: m.btnRight }));
      const ringOk = w && (w.ring ? m.shadow.includes('rgb(41, 62, 45) 0px 0px 0px 1px inset') : !m.shadow.includes('0px 0px 0px 1px inset'));
      check(`${width} ${key} color`, !!w && m.bg === w.bg && m.color === w.color && ringOk, JSON.stringify({ bg: m.bg, color: m.color, shadow: m.shadow }));
      if (width === 1440 && w) {
        await a.scrollIntoViewIfNeeded(); await a.hover(); await page.waitForTimeout(250);
        const hb = await a.evaluate((e) => getComputedStyle(e).backgroundColor);
        check(`${width} ${key} hover`, hb === w.hover, hb);
        await page.keyboard.press('Tab'); await a.focus(); await page.waitForTimeout(400);
        const fs = await a.evaluate((e) => (e === document.activeElement ? getComputedStyle(e).boxShadow : 'not focused'));
        check(`${width} ${key} focus ring`, fs.includes('0px 0px 0px 4px'), fs);
        await page.mouse.move(0, 0);
      }
      await a.evaluate((e) => e.blur()); await a.scrollIntoViewIfNeeded(); await page.mouse.move(0, 0); await page.waitForTimeout(400);
      const b = await a.boundingBox();
      await page.screenshot({ path: `${__dirname}/cta-${width}-${Object.values(seen).reduce((x, y) => x + y)}.png`, clip: { x: Math.max(0, b.x - 16), y: b.y - 16, width: b.width + 32, height: b.height + 32 } });
    }
    check(`${width} 6 CTA found`, Object.values(seen).reduce((x, y) => x + y, 0) === 6, JSON.stringify(seen));
    const hero = await page.$eval('[data-content-type="slide"] .pagebuilder-button-primary', (e) => { const cs = getComputedStyle(e); const af = getComputedStyle(e, '::after'); return { h: Math.round(e.getBoundingClientRect().height), bg: cs.backgroundColor, afPos: af.position, afMask: af.maskImage }; });
    // hero ::after = vùng chạm absolute của btn (không mask arrow)
    check(`${width} hero unchanged`, hero.h === 48 && hero.bg === B500 && hero.afPos === 'absolute' && hero.afMask === 'none', JSON.stringify(hero));
    const promo = await page.$$eval('.lp-promo-card [data-element="link"]', (as) => as.map((e) => { const cs = getComputedStyle(e), b = e.getBoundingClientRect(); return `${Math.round(b.width)}x${Math.round(b.height)} ${cs.borderTopWidth} ${cs.borderTopColor} ${cs.backgroundColor}`; }));
    check(`${width} promo unchanged`, promo.length > 0 && promo.every((p) => p === '36x36 1px rgb(255, 255, 255) rgba(0, 0, 0, 0)'), JSON.stringify(promo));
    // CR1 AC5: rail nav ‹ › — outline brand-500, chevron heroicons 24px
    const navs = await page.$$eval('.lp-nav-btn', (bs) => bs.filter((b) => b.getBoundingClientRect().width > 0).map((b) => { const cs = getComputedStyle(b), r = b.getBoundingClientRect(), svg = b.querySelector('svg'); return { wh: `${Math.round(r.width)}x${Math.round(r.height)}`, border: `${cs.borderTopWidth} ${cs.borderTopColor}`, bg: cs.backgroundColor, color: cs.color, r: cs.borderTopLeftRadius, svg: svg ? `${svg.getAttribute('width')}x${svg.getAttribute('height')} ${svg.querySelector('path').getAttribute('d')}` : 'none' }; }));
    const navOk = (n, i) => n.wh === '48x48' && n.border === `1px ${B500}` && n.bg === 'rgba(255, 255, 255, 0)' && n.color === B500 && n.r === '6px' && n.svg === (i % 2 ? '24x24 M9 5l7 7-7 7' : '24x24 M15 19l-7-7 7-7');
    check(`${width} rail nav (${navs.length})`, navs.length >= 2 && navs.every(navOk), JSON.stringify(navs.slice(0, 2)));
    const next = page.locator('.lp-nav-btn[data-next]:not([disabled])').first();
    if (width === 1440 && await next.count()) {
      await next.scrollIntoViewIfNeeded(); await next.hover(); await page.waitForTimeout(300);
      const hb = await next.evaluate((e) => getComputedStyle(e).backgroundColor);
      check(`${width} rail nav hover`, hb === B50, hb);
      await page.mouse.move(0, 0);
      const nb = await next.boundingBox(); const prevB = await page.locator('.lp-nav-btn[data-prev]').first().boundingBox();
      await page.screenshot({ path: `${__dirname}/nav-${width}.png`, clip: { x: prevB.x - 16, y: prevB.y - 16, width: nb.x + nb.width - prevB.x + 32, height: nb.height + 32 } });
    }
    // CR1 AC6: newsletter button — DS size L + btn-primary
    const nl = page.locator('.lp-newsletter-btn').first();
    await nl.scrollIntoViewIfNeeded(); await page.mouse.move(0, 0); await page.waitForTimeout(300);
    const nlm = await nl.evaluate((e) => { const cs = getComputedStyle(e); return { h: Math.round(e.getBoundingClientRect().height), pad: cs.padding, font: `${cs.fontSize}/${cs.lineHeight}/${cs.fontWeight}`, bg: cs.backgroundColor, color: cs.color, r: cs.borderTopLeftRadius }; });
    const nfont = width >= 768 ? '16px/24px/500' : '14px/20px/500';
    check(`${width} newsletter btn`, nlm.h === 44 && nlm.pad === "10px 20px" && nlm.font === nfont && nlm.bg === B600 && nlm.color === W && nlm.r === "6px", JSON.stringify(nlm));
    const form = await page.locator('.lp-newsletter-form').first().boundingBox();
    await page.screenshot({ path: `${__dirname}/newsletter-${width}.png`, clip: { x: form.x, y: form.y - 8, width: form.width, height: form.height + 16 } });
    if (width === 1440) {
      await nl.hover(); await page.waitForTimeout(300);
      const hb = await nl.evaluate((e) => getComputedStyle(e).backgroundColor);
      check(`${width} newsletter hover`, hb === B700, hb);
      await page.mouse.move(0, 0);
    }
    check(`${width} 0 pageerror`, errs.length === 0, JSON.stringify(errs));
  }
  await browser.close();
  console.log(fail ? `RESULT: ${fail} FAIL` : 'RESULT: ALL PASS');
  process.exit(fail ? 1 : 0);
})();
