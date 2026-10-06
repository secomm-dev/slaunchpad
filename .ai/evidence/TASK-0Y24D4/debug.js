/** TASK-0Y24D4 debug — PLP swatch DOM state + full page errors. */
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1', '--disable-dev-shm-usage', '--disable-gpu'] });
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  page.on('pageerror', (e) => console.log('PAGEERROR:', String(e.message).slice(0, 300)));
  page.on('console', (m) => { if (m.type() === 'error') console.log('CONSOLE:', m.text().slice(0, 200)); });
  await page.goto('http://slaunchpad.localhost/living-room/living-room-seating.html', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(4000);
  const info = await page.evaluate(() => ({
    title: document.title,
    cards: document.querySelectorAll('.hp-card').length,
    swatchLabels: document.querySelectorAll('.hp-card label.swatch-option').length,
    visualLabels: document.querySelectorAll('.hp-card label.swatch-option[data-swatch-type="visual"]').length,
    checkedInputs: document.querySelectorAll('.hp-card label.swatch-option input:checked').length,
    disabledInputs: document.querySelectorAll('.hp-card label.swatch-option input:disabled').length,
    alpine: typeof window.Alpine !== 'undefined',
    itemForms: document.querySelectorAll('form.product_addtocart_form').length,
  }));
  console.log(JSON.stringify(info, null, 2));
  const labels = await page.evaluate(() =>
    [...document.querySelectorAll('.hp-card label.swatch-option')].slice(0, 12).map((l) => ({
      t: l.dataset.swatchType, text: l.textContent.trim().slice(0, 16),
      dis: !!l.querySelector('input:disabled'), chk: !!l.querySelector('input:checked'),
    }))
  );
  console.log(JSON.stringify(labels));
  await browser.close();
})().catch((e) => { console.error('ERR', e); process.exit(2); });
