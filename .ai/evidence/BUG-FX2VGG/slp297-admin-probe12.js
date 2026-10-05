const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
(async () => {
  const b = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const ctx = await b.newContext({ viewport: { width: 1680, height: 1000 } });
  const p = await ctx.newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(String(e).slice(0, 200)));

  await p.goto(BASE + '/admin', { waitUntil: 'domcontentloaded' });
  await p.waitForSelector('#username', { timeout: 15000 });
  await p.fill('#username', 'admin');
  await p.fill('#login', 'admin123');
  await p.click('.action-login');
  await p.waitForTimeout(6000);
  for (const sel of ['aside.modal-popup._show button.action-accept', 'aside.modal-popup._show button.action-close']) {
    const el = p.locator(sel).first();
    if (await el.isVisible().catch(() => false)) { await el.click().catch(() => {}); break; }
  }
  await p.keyboard.press('Escape');
  await p.hover('#menu-magento-backend-content');
  await p.waitForTimeout(1500);
  const gridHref = await p.evaluate(() => [...document.querySelectorAll('a')].find(a => /cms\/page\/index/.test(a.href))?.href);
  await p.goto(gridHref, { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(6000);
  const editHrefs = await p.evaluate(() => [...new Set([...document.querySelectorAll('tbody tr a[href*="cms/page/edit"]')].map(a => a.getAttribute('href')))]);

  for (const href of editHrefs) {
    const pid = (href.match(/page_id\/(\d+)/) || [])[1];
    errs.length = 0;
    await p.goto(new URL(href, BASE).href, { waitUntil: 'domcontentloaded' });
    await p.waitForTimeout(4000);
    // mở rộng section Content
    const contentHeader = p.locator('div[data-index="content"] .admin__collapsible-title, .admin__field-control > div[data-index="content"]').first();
    const byText = p.getByText('Content', { exact: true }).first();
    try {
      if (await contentHeader.isVisible().catch(() => false)) await contentHeader.click();
      else await byText.click();
    } catch (e) { console.log('expand click failed:', e.message.slice(0, 80)); }
    let stageOk = true;
    try { await p.waitForSelector('.pagebuilder-canvas', { timeout: 15000 }); } catch { stageOk = false; }
    await p.waitForTimeout(5000);
    const state = await p.evaluate(() => {
      const q = s => document.querySelector(s);
      const canvas = q('.pagebuilder-canvas');
      const types = {};
      if (canvas) canvas.querySelectorAll('[data-content-type]').forEach(el => {
        const t = el.getAttribute('data-content-type'); types[t] = (types[t] || 0) + 1;
      });
      return {
        hasCanvas: !!canvas, canvasH: canvas ? Math.round(canvas.getBoundingClientRect().height) : -1,
        contentTypes: types,
        text: canvas ? canvas.innerText.replace(/\s+/g, ' ').slice(0, 160) : ''
      };
    });
    console.log(`page_id=${pid}: pageerrors=${errs.length} |`, JSON.stringify(state));
    if (errs.length) errs.slice(0, 3).forEach(e => console.log('   ERR:', e));
    await p.screenshot({ path: `/tmp/slp297-admin-expanded-p${pid}.png`, fullPage: false });
  }
  await b.close();
})().catch(e => { console.error('PROBE ERROR:', e.message); process.exit(1); });
