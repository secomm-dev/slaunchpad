// SLP-296 probe #2 — why is PDP old price still 16px: which CSS files load, do they carry the new rule, what wins.
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://slaunchpad.localhost/norrland-throw.html', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(3000);

  const out = await page.evaluate(async () => {
    const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map((l) => l.href);
    const cssChecks = [];
    for (const href of links) {
      try {
        const txt = await (await fetch(href)).text();
        cssChecks.push({
          href: href.split('/').slice(-3).join('/'),
          len: txt.length,
          hasNewRule: /\.product-info-main \.old-price \.price\{[^}]*875rem/.test(txt),
          hasOldVar: /\.product-info-main \.old-price\{--price-font-size:1em\}/.test(txt),
        });
      } catch (e) {
        cssChecks.push({ href, error: String(e) });
      }
    }
    const op = document.querySelector('.product-info-main .old-price');
    const p = document.querySelector('.product-info-main .old-price .price');
    const cs = p ? getComputedStyle(p) : null;
    return {
      stylesheets: cssChecks,
      oldPriceVar: op ? getComputedStyle(op).getPropertyValue('--price-font-size') : null,
      oldPriceFont: cs ? cs.fontSize : null,
      oldPriceLh: cs ? cs.lineHeight : null,
      oldPriceColor: cs ? cs.color : null,
    };
  });

  console.log(JSON.stringify(out, null, 2));
  await browser.close();
})();
