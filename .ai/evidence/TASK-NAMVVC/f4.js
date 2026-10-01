const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await p.goto('http://slaunchpad.localhost/', { waitUntil: 'load' });
  await p.waitForTimeout(1500);
  const gallery = p.locator('[data-lp-card-slider]').first();
  await gallery.scrollIntoViewIfNeeded();
  await p.waitForTimeout(600);
  const track = gallery.locator('[data-track]').first();
  const before = await track.evaluate(el => el.scrollLeft);
  const tb = await track.boundingBox();
  await p.mouse.move(tb.x + tb.width * 0.7, tb.y + tb.height / 2);
  await p.mouse.down();
  for (let i = 1; i <= 10; i++) await p.mouse.move(tb.x + tb.width * 0.7 - i * 25, tb.y + tb.height / 2);
  await p.mouse.up();
  await p.waitForTimeout(800);
  const after = await track.evaluate(el => el.scrollLeft);
  console.log(`drag scrollLeft ${before} -> ${after} = ${before !== after ? 'PASS' : 'FAIL'}`);
  // click-after-drag suppression: kéo xong click ảnh KHÔNG điều hướng
  const url0 = p.url();
  await p.mouse.move(tb.x + tb.width * 0.7, tb.y + tb.height / 2);
  await p.mouse.down();
  for (let i = 1; i <= 10; i++) await p.mouse.move(tb.x + tb.width * 0.7 - i * 25, tb.y + tb.height / 2);
  await p.mouse.up();
  await p.waitForTimeout(1200);
  console.log('sau kéo+click vẫn ở homepage:', p.url() === url0 ? 'PASS' : 'FAIL ' + p.url());
  await b.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
