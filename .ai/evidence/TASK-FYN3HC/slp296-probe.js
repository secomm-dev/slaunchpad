// SLP-296 probe — computed style of old price on: homepage cards (flash-sale + PB carousel), PLP card, PDP.
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const out = { surfaces: {}, notes: [] };

  const measureOldPrice = () =>
    page.evaluate(() => {
      const q = (sel) => {
        const roots = Array.from(document.querySelectorAll(sel));
        for (const root of roots) {
          const op = root.querySelector('.old-price');
          if (op) {
            const p = op.querySelector('.price') || op;
            const cs = getComputedStyle(p);
            return {
              fontSize: cs.fontSize,
              lineHeight: cs.lineHeight,
              fontWeight: cs.fontWeight,
              color: cs.color,
              deco: cs.textDecorationLine,
            };
          }
        }
        return null;
      };
      return {
        flashCard: q('.lp-flash .hp-card'),
        pbCarouselCard: q('.hp-pb-products .hp-card'),
        plainCard: q('.hp-card'),
        pdp: q('.product-info-main'),
        count: document.querySelectorAll('.old-price').length,
      };
    });

  const shot = (name) => page.screenshot({ path: `/tmp/pw-cal/slp296-${name}.png`, fullPage: false });

  // 1) Homepage
  await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(3500);
  out.surfaces.home = await measureOldPrice();
  out.homePdpLink = await page.evaluate(() => {
    const cards = Array.from(document.querySelectorAll('.hp-card'));
    const withOld = cards.find((c) => c.querySelector('.old-price'));
    const a = withOld ? withOld.querySelector('a.product-item-link') : null;
    return a ? a.href : null;
  });
  await shot('home');

  // 2) PLP — follow a category link found in DOM (nav or widget); fallback: known fixture
  let plpUrl = await page.evaluate(() => {
    const a = Array.from(document.querySelectorAll('nav a[href$=".html"], a[data-lp-nav-link]')).find((x) =>
      /\.html$/.test(x.getAttribute('href'))
    );
    return a ? a.href : null;
  });
  if (!plpUrl) plpUrl = 'http://slaunchpad.localhost/living-room/living-room-seating.html';
  out.plpUrl = plpUrl;
  await page.goto(plpUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(3000);
  out.surfaces.plp = await measureOldPrice();
  out.plpPdpLink = await page.evaluate(() => {
    const cards = Array.from(document.querySelectorAll('.hp-card'));
    const withOld = cards.find((c) => c.querySelector('.old-price'));
    const a = withOld ? withOld.querySelector('a.product-item-link') : null;
    return a ? a.href : null;
  });
  await shot('plp');

  // 3) PDP — prefer a product that actually renders an old price
  const pdpUrl = out.homePdpLink || out.plpPdpLink;
  out.pdpUrl = pdpUrl;
  if (pdpUrl) {
    await page.goto(pdpUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(3000);
    out.surfaces.pdp = await measureOldPrice();
    out.pdpMarkup = await page.evaluate(() => {
      const ob = document.querySelector('.product-info-main .price-box .old-price');
      return ob ? ob.outerHTML.slice(0, 260) : null;
    });
    await shot('pdp');
  } else {
    out.notes.push('No product with old price found on home/PLP — PDP measure skipped');
  }

  console.log(JSON.stringify(out, null, 2));
  await browser.close();
})();
