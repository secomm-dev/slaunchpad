const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1300 } })).newPage();
  await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForSelector('.lp-hero-slider nav[data-pager] .snap-marker', { state: 'attached', timeout: 30000 });
  await page.waitForTimeout(300);
  const series = await page.evaluate(() => new Promise((res) => {
    const samples = [];
    const t0 = performance.now();
    const iv = setInterval(() => {
      const m = document.querySelector('.lp-hero-slider nav[data-pager] .snap-marker[aria-current="true"]');
      if (!m) return;
      const tr = getComputedStyle(m, '::after').transform;
      const scaleX = tr === 'none' ? null : parseFloat(tr.slice(7));
      const track = document.querySelector('.lp-hero-slider [data-track]');
      samples.push({ t: Math.round(performance.now() - t0), scale: scaleX === null ? null : Math.round(scaleX * 100) / 100, slide: Math.round(track.scrollLeft / track.clientWidth) + 1 });
      if (performance.now() - t0 > 8600) { clearInterval(iv); res(samples); }
    }, 430);
  }));
  const progresses = series.some((s, i) => i > 0 && s.scale > series[i - 1].scale && series[i - 1].scale !== null);
  console.log(JSON.stringify({ progresses, samples: series.filter((_, i) => i % 2 === 0) }));
  await browser.close();
})();
