// SLP-271 (BUG-3XJSQG) verify — node verify-split.cjs [tag]  (Chrome 150 via Playwright)
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const CHROME = '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome';
const URL = 'http://slaunchpad.localhost/';
const DIR = __dirname;
const tag = process.argv[2] || 'after';
const out = []; let fail = 0;
const check = (name, ok, info) => { out.push(`${ok ? 'PASS' : 'FAIL'} ${name}${info ? ' — ' + info : ''}`); if (!ok) fail++; };
const near = (a, b, t = 1) => Math.abs(a - b) <= t;

const measure = (page) => page.evaluate(async () => {
  const img0 = document.querySelector('img[src*="living-reimagined.webp"]');
  const row = img0.closest('[data-content-type=row]');
  row.scrollIntoView(); await new Promise((r) => setTimeout(r, 1200));
  const R = (el) => { const b = el.getBoundingClientRect(); return { x: Math.round(b.left), y: Math.round(b.top + scrollY), r: Math.round(b.right), b: Math.round(b.bottom + scrollY), w: Math.round(b.width), h: Math.round(b.height) }; };
  const img = [...row.querySelectorAll('figure img')].find((i) => i.offsetParent);
  const size = (sel) => { const el = document.querySelector(sel); return el ? R(el) : null; };
  return {
    vw: document.documentElement.clientWidth, sw: document.documentElement.scrollWidth,
    row: R(row), fig: R(row.querySelector('figure')), img: R(img), fit: getComputedStyle(img).objectFit,
    h2: R(row.querySelector('h2')), p: R(row.querySelector('[data-content-type=text]')),
    btn: R(row.querySelector('[data-content-type=buttons] a')),
    promo: size('.lp-promo-card'), newsletter: size('[data-content-type=row][data-appearance="full-width"]:not(:has([data-content-type=column-group]))'),
    figs: [...document.querySelectorAll('[data-content-type=image]')].filter((f) => getComputedStyle(f).position === 'relative' && [...f.querySelectorAll('img')].every((i) => getComputedStyle(i).objectFit === 'cover')).length,
  };
});

(async () => {
  const browser = await chromium.launch({ executablePath: CHROME });
  const errors = []; const res = {};
  for (const [w, h, mob] of [[375, 812, true], [1024, 900, false], [1440, 1000, false], [1920, 1100, false]]) {
    const page = await browser.newPage({ viewport: { width: w, height: h }, isMobile: mob, hasTouch: mob });
    page.on('pageerror', (e) => errors.push(`${w}: ${e.message}`));
    await page.goto(URL, { waitUntil: 'load', timeout: 60000 });
    const m = res[w] = await measure(page);
    out.push(`# ${w}: ${JSON.stringify(m)}`);
    await page.locator('[data-content-type=row]:has(img[src*="living-reimagined.webp"])').first().screenshot({ path: `${DIR}/split-${w}-${tag}.png` });
    await page.close();
    check(`${w}: no h-scroll`, m.sw <= m.vw, `${m.sw}/${m.vw}`);
    check(`${w}: img object-fit cover, fills figure`, m.fit === 'cover' && m.img.w === m.fig.w && m.img.h === m.fig.h, `img ${m.img.w}x${m.img.h} fig ${m.fig.w}x${m.fig.h}`);
    check(`${w}: only split figure styled (cover)`, m.figs === 1, `${m.figs}`);
    if (w >= 1024) {
      check(`${w}: text x = img right + 32`, near(m.h2.x, m.img.r + 32), `${m.h2.x} vs ${m.img.r}+32`);
      check(`${w}: text right = section right - 40`, near(m.p.r, m.row.r - 40), `${m.p.r} vs ${m.row.r}-40`);
      check(`${w}: button bottom = section bottom`, near(m.btn.b, m.row.b), `${m.btn.b} vs ${m.row.b}`);
      check(`${w}: img bottom = section bottom (no gap)`, near(m.img.b, m.row.b), `${m.img.b} vs ${m.row.b}`);
      if (w >= 1440) check(`${w}: media ratio 940:620`, near(m.img.h, m.img.w * 620 / 940, 2), `${m.img.w}x${m.img.h}`);
      check(`${w}: img starts at x=0`, m.img.x === 0);
    } else {
      check(`${w}: img 375x535`, m.img.w === m.vw && m.img.h === 535, `${m.img.w}x${m.img.h}`);
      check(`${w}: heading = img bottom + 16`, near(m.h2.y, m.img.b + 16), `${m.h2.y} vs ${m.img.b}+16`);
      check(`${w}: text x = 8`, m.h2.x === 8, `${m.h2.x}`);
    }
    if (m.promo) check(`${w}: promo card unchanged size`, (w < 1024 ? m.promo.w === 320 && m.promo.h === 400 : m.promo.w === 360 && m.promo.h === 502), `${m.promo.w}x${m.promo.h}`);
  }
  check('0 pageerror', errors.length === 0, errors.join(' | '));
  await browser.close();
  out.push(fail ? `RESULT: ${fail} FAIL` : 'RESULT: ALL PASS');
  require('fs').writeFileSync(`${DIR}/verify-split.txt`, out.join('\n') + '\n');
  console.log(out.join('\n'));
})();
