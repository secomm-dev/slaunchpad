// TASK-375K58 (SLP-267): auto slide marquee cho categories_a.
// OFF regression (payload mặc định) + ON marquee (route-inject data-lp-marquee)
// + reduced-motion fallback slider thường.
const { chromium } = require('playwright');
const HOME = 'http://slaunchpad.localhost/';
const SEL = '[data-secomm-ui-component="categories_a"]';

const sample = (page) => page.$eval(SEL, (sec) => {
  const t = sec.querySelector('[data-track]');
  return {
    scrollLeft: Math.round(t.scrollLeft * 10) / 10,
    tiles: t.children.length,
    clones: t.querySelectorAll('[aria-hidden="true"]').length,
    sliderInit: !!t.closest('[data-lp-slider], [data-lp-card-slider], [data-lp-tiles-slider]')?.dataset.lpSliderInit,
    marqueeInit: !!t.dataset.lpMarquee,
    hScroll: document.documentElement.scrollWidth > window.innerWidth + 1,
  };
});

const watchScroll = async (page, ms) => {
  const trace = await page.evaluate((duration) => new Promise((resolve) => {
    const t = document.querySelector('[data-secomm-ui-component="categories_a"] [data-track]');
    const out = [];
    let prev = t.scrollLeft;
    const t0 = performance.now();
    const id = setInterval(() => {
      const cur = t.scrollLeft;
      out.push(Math.round((cur - prev) * 10) / 10);
      prev = cur;
      if (performance.now() - t0 >= duration) {
        clearInterval(id);
        resolve(out);
      }
    }, 250);
  }), ms);
  return trace;
};

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.PW_CHROME || undefined,
    args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
  });
  const out = {};

  // ---- OFF regression: behavior TASK-HARDAR nguyên trạng ----
  {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(String(e).slice(0, 120)));
    await page.goto(HOME, { waitUntil: 'load', timeout: 60000 });
    await page.locator(SEL).scrollIntoViewIfNeeded();
    await page.waitForTimeout(800);
    const r = { marqueeAttr: await page.$eval(SEL, (s) => s.hasAttribute('data-lp-marquee')), static0: await sample(page) };
    r.idleTrace = await watchScroll(page, 1500);
    r.idleMoves = r.idleTrace.some((d) => Math.abs(d) > 2);
    const box = await page.locator(`${SEL} .lp-cat-tile`).nth(1).boundingBox();
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down();
    for (let i = 1; i <= 8; i++) await page.mouse.move(box.x + box.width / 2 - i * 25, box.y + box.height / 2);
    await page.mouse.up();
    await page.waitForTimeout(800);
    r.afterDrag = await sample(page);
    r.pageErrors = errs;
    out.off1440 = r;
    await page.close();
  }

  // ---- ON marquee (inject attribute qua route) — 375 + 1440 ----
  for (const width of [375, 1440]) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 } });
    await ctx.route('**/slaunchpad.localhost/', async (route) => {
      const res = await route.fetch({ url: 'http://127.0.0.1/', headers: { host: 'slaunchpad.localhost' } });
      const body = (await res.text()).replace(
        'class="lp-cat-slider"',
        'class="lp-cat-slider lp-cat-slider--marquee" data-lp-marquee="6"',
      );
      await route.fulfill({ status: res.status(), contentType: res.headers()['content-type'] || 'text/html', body });
    });
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(String(e).slice(0, 120)));
    await page.goto(HOME, { waitUntil: 'load', timeout: 60000 });
    await page.locator(SEL).scrollIntoViewIfNeeded();
    await page.waitForTimeout(600);
    const r = { attr: await page.$eval(SEL, (s) => s.getAttribute('data-lp-marquee')), s0: await sample(page) };
    // tốc độ: 2 mẫu cách 1s ≈ cycle/6
    r.speedTrace = await watchScroll(page, 1500);
    r.speedPxPerSec = Math.round(r.speedTrace.reduce((a, b) => a + Math.max(b, 0), 0) * 1000 / 1500);
    r.cycle = await page.$eval(SEL, (sec) => {
      const t = sec.querySelector('[data-track]');
      const tiles = Array.from(t.children).filter((c) => c.getAttribute('aria-hidden') !== 'true');
      const first = tiles[0];
      const last = tiles[tiles.length - 1];
      const gap = parseFloat(getComputedStyle(t).columnGap) || 0;
      return Math.round(last.offsetLeft + last.offsetWidth - first.offsetLeft + gap);
    });
    // wrap: đặt gần mốc cycle, sample liên tục — giá trị sau wrap phải gần 0
    const wrapTrace = await page.evaluate(() => new Promise((resolve) => {
      const t = document.querySelector('[data-secomm-ui-component="categories_a"] [data-track]');
      const tiles = Array.from(t.children).filter((c) => c.getAttribute('aria-hidden') !== 'true');
      const gap = parseFloat(getComputedStyle(t).columnGap) || 0;
      const cycle = tiles[tiles.length - 1].offsetLeft + tiles[tiles.length - 1].offsetWidth - tiles[0].offsetLeft + gap;
      t.scrollLeft = cycle - 20;
      const seen = [];
      const id = setInterval(() => {
        seen.push(Math.round(t.scrollLeft * 10) / 10);
        if (seen.length > 1 && seen[seen.length - 1] < 50) {
          clearInterval(id);
          resolve({ beforeWrap: seen.filter((v) => v >= 50).slice(-2), afterWrap: seen.slice(-2), cycle: Math.round(cycle) });
        }
        if (seen.length > 60) { clearInterval(id); resolve({ timeout: true, last: seen.slice(-3) }); }
      }, 120);
    }));
    r.wrap = wrapTrace;
    // hover pause / resume
    const box = await page.locator(`${SEL} .lp-cat-heading`).boundingBox();
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.waitForTimeout(300);
    r.hoverTrace = await watchScroll(page, 1200);
    r.pausedOnHover = r.hoverTrace.every((d) => Math.abs(d) < 3);
    await page.mouse.move(10, 10);
    await page.waitForTimeout(200);
    r.resumeTrace = await watchScroll(page, 1000);
    r.resumedAfterHover = r.resumeTrace.some((d) => d > 2);
    // click clone tile vẫn navigate
    const href = await page.evaluate(() => {
      const clones = document.querySelectorAll('[data-secomm-ui-component="categories_a"] [data-track] a[aria-hidden="true"]');
      return clones.length ? clones[0].getAttribute('href') : null;
    });
    if (href) {
      await Promise.all([
        page.waitForURL((u) => u.pathname === href, { timeout: 20000 }).catch(() => {}),
        page.evaluate((h) => {
          const a = document.querySelector(`[data-secomm-ui-component="categories_a"] [data-track] a[aria-hidden="true"][href="${h}"]`);
          a.click();
        }, href),
      ]);
      r.cloneClickOpens = new URL(page.url()).pathname === href;
    }
    r.sFinal = await page.goto(HOME, { waitUntil: 'load', timeout: 60000 }).then(() => null);
    await page.locator(SEL).scrollIntoViewIfNeeded();
    await page.waitForTimeout(400);
    r.sRecheck = await sample(page);
    r.pageErrors = errs;
    out[`on${width}`] = r;
    await ctx.close();
  }

  // ---- reduced-motion: marquee config ON nhưng fallback slider thường ----
  {
    const ctx = await browser.newContext({ viewport: { width: 375, height: 900 }, reducedMotion: 'reduce' });
    await ctx.route('**/slaunchpad.localhost/', async (route) => {
      const res = await route.fetch({ url: 'http://127.0.0.1/', headers: { host: 'slaunchpad.localhost' } });
      const body = (await res.text()).replace(
        'class="lp-cat-slider"',
        'class="lp-cat-slider lp-cat-slider--marquee" data-lp-marquee="6"',
      );
      await route.fulfill({ status: res.status(), contentType: res.headers()['content-type'] || 'text/html', body });
    });
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(String(e).slice(0, 120)));
    await page.goto(HOME, { waitUntil: 'load', timeout: 60000 });
    await page.locator(SEL).scrollIntoViewIfNeeded();
    await page.waitForTimeout(600);
    const r = {};
    r.s0 = await sample(page);
    r.idleTrace = await watchScroll(page, 1500);
    r.noMarquee = r.idleTrace.every((d) => Math.abs(d) < 2);
    r.snapSliderInited = r.s0.sliderInit;
    r.dotsVisible = await page.$eval(SEL, (s) => {
      const p = s.querySelector('[data-pager]');
      return !!p && getComputedStyle(p).display !== 'none';
    });
    r.pageErrors = errs;
    out.reducedMotion375 = r;
    await ctx.close();
  }

  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})();
