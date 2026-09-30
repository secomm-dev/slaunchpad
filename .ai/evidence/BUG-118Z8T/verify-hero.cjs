// SLP-268 (BUG-118Z8T) verify — node verify-hero.cjs  (Chrome 150 via Playwright)
const { chromium, devices } = require('/tmp/pw-cal/node_modules/playwright');
const CHROME = '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome';
const URL = 'http://slaunchpad.localhost/';
const out = []; let fail = 0;
const check = (name, ok, info) => { out.push(`${ok ? 'PASS' : 'FAIL'} ${name}${info ? ' — ' + info : ''}`); if (!ok) fail++; };

const heroState = (page) => page.evaluate(() => {
  const s = document.querySelector('.lp-hero-slider');
  const ms = [...s.querySelectorAll('nav[data-pager] .snap-marker')];
  const i = ms.findIndex((m) => m.getAttribute('aria-current') === 'true');
  return { idx: i, prog: parseFloat((ms[i] && ms[i].style.getPropertyValue('--lp-dot-progress')) || '0'), paused: s.classList.contains('lp-dot-paused') };
});

// After a gesture: not paused, and the slide changes within 5s.
const resumes = async (page, label) => {
  await page.waitForTimeout(150);
  const a = await heroState(page);
  let changed = false;
  for (let t = 0; t < 20 && !changed; t++) { await page.waitForTimeout(250); changed = (await heroState(page)).idx !== a.idx; }
  check(`${label}: not paused after release`, !a.paused, JSON.stringify(a));
  check(`${label}: slide advances ≤5s`, changed);
};

const style = (page, sel) => page.evaluate((sel) => {
  const el = document.querySelector(sel); const cs = getComputedStyle(el); const b = el.getBoundingClientRect();
  return { fs: cs.fontSize, lh: cs.lineHeight, fw: cs.fontWeight, ff: cs.fontFamily.split(',')[0].replace(/"/g, ''), pt: cs.paddingTop, pl: cs.paddingLeft, r: cs.borderTopLeftRadius, bg: cs.backgroundColor, h: Math.round(b.height), w: Math.round(b.width) };
}, sel);

(async () => {
  const browser = await chromium.launch({ executablePath: CHROME });
  const errors = [];

  // ---- mobile touch (AC1, AC2) ----
  const ctx = await browser.newContext({ ...devices['iPhone 13'] });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => errors.push('375: ' + e.message));
  await page.goto(URL, { waitUntil: 'load', timeout: 60000 });
  await page.evaluate(() => document.querySelectorAll('.lp-hero-slider a').forEach((a) => a.addEventListener('click', (e) => e.preventDefault())));
  await page.waitForTimeout(1000);
  const cdp = await ctx.newCDPSession(page);
  const box = await page.locator('.lp-hero-slider').boundingBox();
  const x = box.x + box.width / 2, y = box.y + 300;
  const T = (type, pts) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: pts });
  const move = async (dx, dy, n) => { for (let k = 1; k <= n; k++) { await T('touchMove', [{ x: x + dx * k, y: y + dy * k }]); await page.waitForTimeout(16); } };

  await page.touchscreen.tap(x, y); await resumes(page, 'AC1 tap');
  await T('touchStart', [{ x, y }]); await page.waitForTimeout(1500);
  check('AC1 long-press: paused while finger down', (await heroState(page)).paused);
  await T('touchEnd', []); await resumes(page, 'AC1 long-press 1.5s');
  await T('touchStart', [{ x, y }]); await move(-20, 0, 10); await T('touchEnd', []); await resumes(page, 'AC1 horizontal swipe');
  await T('touchStart', [{ x, y }]); await move(0, -25, 10); await T('touchEnd', []); await resumes(page, 'AC1 vertical scroll from hero');
  await page.evaluate(() => scrollTo(0, 0)); await page.waitForTimeout(400);

  // AC2: WebKit-like sequence — touch pointerenter, NO pointerleave.
  await page.evaluate(() => {
    const s = document.querySelector('.lp-hero-slider');
    s.dispatchEvent(new PointerEvent('pointerenter', { pointerType: 'touch' }));
    s.dispatchEvent(new PointerEvent('pointerdown', { pointerType: 'touch', bubbles: true }));
    s.dispatchEvent(new TouchEvent('touchstart', { bubbles: true }));
    s.dispatchEvent(new PointerEvent('pointercancel', { pointerType: 'touch', bubbles: true }));
    s.dispatchEvent(new TouchEvent('touchend', { bubbles: true }));
  });
  await resumes(page, 'AC2 touch without pointerleave');
  await page.evaluate(() => document.querySelector('.lp-hero-slider').dispatchEvent(new PointerEvent('pointerenter', { pointerType: 'mouse' })));
  const hv = await heroState(page); await page.waitForTimeout(800);
  check('mouse hover still pauses', hv.paused && (await heroState(page)).prog === hv.prog);
  await page.evaluate(() => document.querySelector('.lp-hero-slider').dispatchEvent(new PointerEvent('pointerleave', { pointerType: 'mouse' })));
  await resumes(page, 'mouse leave');

  // AC4: 375 typography/button
  const B = '.lp-hero-slider [data-content-type="slide"] .pagebuilder-button-primary';
  const H = '.lp-hero-slider [data-content-type="slide"] h2';
  const P = '.lp-hero-slider [data-content-type="slide"] [data-element="content"] p';
  let b = await style(page, B), h = await style(page, H), p = await style(page, P);
  check('AC4 375 button', b.h === 48 && b.fs === '14px' && b.lh === '20px' && b.fw === '500' && b.bg === 'rgb(88, 143, 96)' && b.r === '6px' && b.ff === 'Inter', JSON.stringify(b));
  check('AC4 375 title/description', h.fs === '30px' && h.lh === '36px' && p.fs === '16px' && p.lh === '24px' && h.ff === 'Inter' && p.ff === 'Inter', JSON.stringify({ h, p }));
  await page.screenshot({ path: __dirname + '/hero-375.png' });
  await ctx.close();

  // ---- desktop (AC3) ----
  const d = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  d.on('pageerror', (e) => errors.push('1440: ' + e.message));
  await d.goto(URL, { waitUntil: 'load', timeout: 60000 });
  await d.waitForTimeout(800);
  b = await style(d, B); h = await style(d, H); p = await style(d, P);
  check('AC3 1440 button', b.h === 48 && b.fs === '16px' && b.lh === '24px' && b.fw === '500' && b.pt === '12px' && b.pl === '24px' && b.r === '6px' && b.bg === 'rgb(88, 143, 96)' && b.ff === 'Inter', JSON.stringify(b));
  check('AC3 1440 title 60/72', h.fs === '60px' && h.lh === '72px' && h.fw === '700' && h.ff === 'Inter', JSON.stringify(h));
  check('AC3 1440 description 18/28', p.fs === '18px' && p.lh === '28px' && p.ff === 'Inter', JSON.stringify(p));
  await d.screenshot({ path: __dirname + '/hero-1440.png' });
  await d.hover(B); await d.waitForTimeout(400);
  const bh = await style(d, B);
  check('AC3 1440 button hover brand-600', bh.bg === 'rgb(69, 116, 76)', bh.bg);
  const other = await d.evaluate(() => { const el = [...document.querySelectorAll('.pagebuilder-button-primary')].find((e) => !e.closest('[data-content-type="slide"]')); if (!el) return null; const cs = getComputedStyle(el); return { h: Math.round(el.getBoundingClientRect().height), fs: cs.fontSize, bg: cs.backgroundColor }; });
  out.push('INFO non-hero PB primary button (unchanged rule): ' + JSON.stringify(other));
  // ---- AC6: mouse drag (1440) ----
  const g = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  g.on('pageerror', (e) => errors.push('drag: ' + e.message));
  await g.goto(URL, { waitUntil: 'load', timeout: 60000 });
  await g.waitForTimeout(800);
  await g.evaluate(() => { document.querySelector('.lp-hero-slider').scrollIntoView(); document.querySelector('.lp-hero-slider').dataset.autoplay = 'false'; });
  const tb = await g.locator('.lp-hero-slider [data-track]').boundingBox();
  const cy = tb.y + Math.min(tb.height, 700) / 2;
  const left = () => g.evaluate(() => Math.round(document.querySelector('.lp-hero-slider [data-track]').scrollLeft));
  const W = await g.evaluate(() => document.querySelector('.lp-hero-slider [data-track]').clientWidth);
  const offs = await g.evaluate(() => [...document.querySelector('.lp-hero-slider [data-track]').children].map((c) => c.offsetLeft));
  const n = offs.length; // slides are W wide + 16px track gap → step = offs[1]
  const mdrag = async (dx, hold = false) => {
    const sx = tb.x + tb.width / 2;
    await g.mouse.move(sx, cy); await g.mouse.down();
    for (let k = 1; k <= 10; k++) { await g.mouse.move(sx + (dx * k) / 10, cy); await g.waitForTimeout(16); }
    const mid = await g.evaluate(() => ({ left: document.querySelector('.lp-hero-slider [data-track]').scrollLeft, cls: document.querySelector('.lp-hero-slider [data-track]').classList.contains('lp-hero-dragging'), snap: getComputedStyle(document.querySelector('.lp-hero-slider [data-track]')).scrollSnapType }));
    await g.mouse.up(); await g.waitForTimeout(900);
    return mid;
  };
  const url0 = g.url();
  const toStart = async () => { await g.evaluate(() => document.querySelector('.lp-hero-slider [data-track]').scrollTo({ left: 0, behavior: 'instant' })); await g.waitForTimeout(400); };
  await toStart();
  let mid = await mdrag(-400);
  let l = await left();
  check('AC6 drag left → next slide', l === offs[1], `mid=${JSON.stringify(mid)} end=${l} W=${W}`);
  check('AC6 track follows mouse, snap off while dragging', mid.cls && mid.snap === 'none' && Math.abs(mid.left - 400) < 5);
  check('AC6 drag does not open link', g.url() === url0, g.url());
  await mdrag(300); l = await left();
  check('AC6 drag right → previous slide', l === 0, 'end=' + l);
  await mdrag(-40); l = await left();
  check('AC6 short drag (< threshold) snaps back', l === 0, 'end=' + l);
  await mdrag(250); l = await left();
  check('AC6 drag right on first slide stays first', l === 0, 'end=' + l);
  for (let i = 0; i < n + 1; i++) await mdrag(-400);
  l = await left();
  check('AC6 drag past last slide clamps at last', l === offs[n - 1], 'end=' + l);
  check('AC6 snap restored after settle', await g.evaluate(() => !document.querySelector('.lp-hero-slider [data-track]').classList.contains('lp-hero-dragging') && getComputedStyle(document.querySelector('.lp-hero-slider [data-track]')).scrollSnapType.startsWith('x')));
  check('AC6 url unchanged after all drags', g.url() === url0, g.url());
  // AC7: cursor
  const cur = (sel) => g.evaluate((sel) => getComputedStyle(document.querySelector(sel)).cursor, sel);
  const SL = '.lp-hero-slider [data-track] > [data-content-type="slide"] > a';
  const SB = '.lp-hero-slider [data-track] .pagebuilder-slide-button';
  const c1 = await cur(SL), c2 = await cur(SB);
  await g.mouse.move(tb.x + tb.width / 2, cy); await g.mouse.down();
  for (let k = 1; k <= 5; k++) { await g.mouse.move(tb.x + tb.width / 2 - k * 20, cy); await g.waitForTimeout(16); }
  const c3 = await cur(SL), c4 = await cur(SB);
  await g.mouse.up(); await g.waitForTimeout(900);
  check('AC7 cursor: slide grab, CTA pointer, dragging grabbing', c1 === 'grab' && c2 === 'pointer' && c3 === 'grabbing' && c4 === 'grabbing', JSON.stringify({ slide: c1, cta: c2, dragSlide: c3, dragCta: c4 }));
  const c5 = await cur(SL);
  check('AC7 cursor back to grab after drag', c5 === 'grab', c5);
  await g.mouse.click(tb.x + tb.width / 2, cy); await g.waitForTimeout(1500);
  check('AC6 plain click still opens slide link', /\/collection/.test(g.url()), g.url());
  check('0 pageerror', errors.length === 0, errors.join(' | '));
  await browser.close();
  console.log(out.join('\n') + `\n\n${fail ? fail + ' FAIL' : 'ALL PASS'}`);
  process.exit(fail ? 1 : 0);
})();
