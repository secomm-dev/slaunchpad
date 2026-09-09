const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  for (const path of ['/', '/atlas-pouf.html', '/living-room.html']) {
    const page = await browser.newPage();
    const errors = [];
    page.on('console', m => m.type() === 'error' && errors.push(m.text()));
    page.on('pageerror', e => errors.push('pageerror: ' + e.message));
    await page.goto('http://slaunchpad.localhost' + path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    console.log(path, '->', errors.length === 0 ? 'console CLEAN' : errors.join(' | ').slice(0, 160));
    await page.close();
  }
  await browser.close();
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
