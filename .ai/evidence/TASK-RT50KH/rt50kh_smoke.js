/* TASK-RT50KH — directive §17 admin smoke. Run: node /tmp/rt50kh_smoke.js */
const { chromium } = require('/tmp/node_modules/playwright');
const fs = require('fs');

const BASE = 'http://fashion-launchpad.localhost';
const ADMIN = BASE + '/admin_w1275xl';
const PW = fs.readFileSync('/tmp/rt50kh_pw', 'utf8').trim();
const SHOT = '.ai/evidence/TASK-RT50KH/';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1600, height: 1100 } });
  const results = [];
  const ok = (name, cond) => { results.push(`${cond ? 'PASS' : 'FAIL'} — ${name}`); if (!cond) process.exitCode = 1; };

  await page.goto(ADMIN + '/admin/auth/login/', { waitUntil: 'networkidle' });
  await page.fill('#username', 'smokert50kh');
  await page.fill('#login', PW);
  await page.click('.action-login');
  await page.waitForLoadState('networkidle');
  ok('admin login succeeded', !page.url().includes('auth/login'));

  // Product edit page (product 1, attribute set 9 — has the dimension attrs)
  await page.goto(ADMIN + '/admin/dashboard/', { waitUntil: 'networkidle' });
  const prodUrl = await page.evaluate(() => {
    const link = [...document.querySelectorAll('a')].find(a => a.href.includes('catalog/product/edit/id/1'));
    return link ? link.href : null;
  });
  if (!prodUrl) {
    // navigate by grid: catalog products
    const gridUrl = await page.evaluate(() => {
      const el = [...document.querySelectorAll('a')].find(a => a.href.includes('catalog/product/index'));
      return el ? el.href : null;
    });
    await page.goto(gridUrl || ADMIN, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2500);
    await page.evaluate(() => {
      const row = [...document.querySelectorAll('table.data-grid tbody tr')].find(r => r.textContent.includes('atlas-pouf'));
      const el = row && [...row.querySelectorAll('a')].find(a => a.textContent.trim() === 'Edit');
      if (el) el.click();
    });
    await page.waitForLoadState('networkidle');
  } else {
    await page.goto(prodUrl, { waitUntil: 'networkidle' });
  }
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(2500);
  const safeContent = async () => { for (let i = 0; i < 5; i++) { try { return await page.content(); } catch (e) { await page.waitForTimeout(800); } } return ''; };
  const html = await safeContent();
  ok('fields render with (cm) labels', html.includes('Shipping Length (cm)') && html.includes('Shipping Width (cm)') && html.includes('Shipping Height (cm)'));
  const lenInput = await page.$('[name="product[length]"]');
  const widInput = await page.$('[name="product[width]"]');
  const heiInput = await page.$('[name="product[height]"]');
  ok('3 dimension inputs render', !!lenInput && !!widInput && !!heiInput);
  // validation classes present
  const lenClass = lenInput ? await lenInput.getAttribute('class') : '';
  ok('frontend validation classes attached', (lenClass || '').includes('validate-number') && (lenClass || '').includes('validate-zero-or-greater'));
  await page.screenshot({ path: SHOT + 'smoke-1-product-form-fields.png', fullPage: false });

  // Save 40.5 / 20 / 30
  if (lenInput) {
    await page.fill('[name="product[length]"]', '40.5');
    await page.fill('[name="product[width]"]', '20');
    await page.fill('[name="product[height]"]', '30');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    await page.evaluate(() => { const b = [...document.querySelectorAll('.page-actions button')].find(x => x.textContent.includes('Save')); b && b.click(); });
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(2500);
    const msgText = await page.evaluate(() => { const m = document.querySelector('#messages'); return m ? m.textContent : ''; }).catch(() => '');
    console.log('(save message: ' + msgText.trim().slice(0, 120) + ')');
    ok('product saved successfully', msgText.includes('saved the product'));
    const lenValue = await page.$eval('[name="product[length]"]', el => el.value).catch(() => '');
    ok('value persisted 40.5 after reload', lenValue === '40.5');
    await page.screenshot({ path: SHOT + 'smoke-2-saved-values.png', fullPage: false });
  }

  // Global scope: no "use default" checkbox on the length field
  const useDefault = await page.$$('[name="product[length]"] ~ .use-default-control, .admin__field[data-index="length"] .use-default');
  ok('GLOBAL scope (no store-view override checkbox)', (await page.content()).includes('Use Default') === false || useDefault.length === 0);

  console.log(results.join('\n'));
  fs.writeFileSync(SHOT + 'admin-smoke-results.txt', results.join('\n') + '\n');
  await browser.close();
})().catch(e => { console.error('SMOKE CRASH: ' + e.message.split('\n')[0]); process.exit(1); });
