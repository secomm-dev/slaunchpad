const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const page = await (await browser.newContext({ viewport: { width: 1318, height: 900 } })).newPage();
  const D = 'dialog[aria-labelledby="quickview-modal-title"]';
  await page.goto('http://slaunchpad.localhost/dining.html', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('button[aria-label^="Xem nhanh"]', { timeout: 20000 });

  // Terra Mug (child variant) — modal phải hiện đúng Terra Mug, KHÔNG phải parent Collection
  const cases = [['Terra Mug', 'Terra Mug'], ['Terra Bowl', 'Terra Bowl'], ['Ovale Dining Table', 'Ovale Dining Table']];
  let fail = 0;
  for (const [card, expected] of cases) {
    const t = page.locator(`button[aria-label^="Xem nhanh ${card}"]`).first();
    await t.evaluate(el => el.scrollIntoView({ block: 'center' }));
    await t.click();
    await page.locator(D).waitFor({ state: 'visible', timeout: 10000 });
    const ok = await page.waitForFunction((exp) => (document.querySelector('#quickview-modal-title')?.textContent || '').trim() === exp, expected, { timeout: 12000 }).then(() => true).catch(() => false);
    const title = await page.locator('#quickview-modal-title').innerText().catch(() => '?');
    console.log((ok ? 'PASS' : 'FAIL') + ` | card "${card}" → modal "${title.trim()}"`);
    if (!ok) fail++;
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
  }
  // ATC child variant (Terra Mug) hoạt động không
  const t = page.locator('button[aria-label^="Xem nhanh Terra Mug"]').first();
  await t.evaluate(el => el.scrollIntoView({ block: 'center' }));
  await t.click();
  await page.locator(D).waitFor({ state: 'visible', timeout: 10000 });
  await page.waitForFunction(() => (document.querySelector('#quickview-modal-title')?.textContent || '').trim() === 'Terra Mug', { timeout: 12000 }).catch(() => {});
  const before = await page.evaluate(() => { try { return JSON.parse(localStorage.getItem('mage-cache-storage'))?.cart?.summary_count ?? 0; } catch(e){ return 0; } });
  await page.locator(`${D} button[type="submit"]`).click();
  const drawer = await page.waitForFunction(() => document.getElementById('cart-drawer')?.hasAttribute('open') === true, { timeout: 15000 }).then(() => true).catch(() => false);
  await page.waitForTimeout(2000);
  const after = await page.evaluate(() => { try { return JSON.parse(localStorage.getItem('mage-cache-storage'))?.cart?.summary_count ?? 0; } catch(e){ return 0; } });
  console.log((drawer && after > before ? 'PASS' : 'FAIL') + ` | ATC child variant (Terra Mug) | count ${before}→${after}, drawer=${drawer}`);
  await browser.close();
  process.exit(fail || (!drawer || after <= before) ? 1 : 0);
})().catch(e => { console.error('FATAL:', e.message.slice(0, 150)); process.exit(2); });
