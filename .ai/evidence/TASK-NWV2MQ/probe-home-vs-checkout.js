const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';
const OUT = process.argv[2] || __dirname;
const TAG = process.argv[3] || 'x';
const VPS = (process.argv[4] || '1280,375').split(',').map(Number);
function measure() {
  const vis = (e) => e && e.offsetWidth > 0 && e.offsetHeight > 0;
  const card = document.querySelector('#social-login-popup');
  const blk = [...document.querySelectorAll('#social-login-popup .social-login.block-container')].find(vis);
  const one = (root, sel) => root ? [...root.querySelectorAll(sel)].find(vis) : null;
  const st = (e) => { if (!e) return null; const s = getComputedStyle(e), b = e.getBoundingClientRect();
    return { t: (e.innerText || e.value || '').trim().slice(0, 30), x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height), ff: s.fontFamily.split(',')[0], fs: s.fontSize, fw: s.fontWeight, lh: s.lineHeight, c: s.color, bg: s.backgroundColor, bd: s.borderTopWidth + ' ' + s.borderTopStyle + ' ' + s.borderTopColor, bb: s.borderBottomWidth + ' ' + s.borderBottomStyle, r: s.borderRadius, p: s.padding, m: s.margin, sh: s.boxShadow.slice(0, 40), td: s.textDecorationLine }; };
  const shell = document.querySelector('.modal-popup._show .modal-inner-wrap') || document.querySelector('dialog[open] #social-login-popup');
  const close = [...document.querySelectorAll('.modal-popup._show .action-close, #social-login-popup button.mfp-close')].find(vis);
  return {
    view: blk ? blk.className : null,
    shell: st(shell), card: st(card),
    title: st(one(blk, '.social-login-title')), h2: st(one(blk, '.social-login-title h2')),
    blockTitle: st(one(blk, '.block-title span, .block-title strong')), blockTitleBox: st(one(blk, '.block-title')),
    label: st(one(blk, 'label.label')), labelSpan: st(one(blk, 'label.label span')),
    input: st(one(blk, 'input.input-text')),
    primary: st(one(blk, 'button.primary, .primary button, .action.primary')),
    links: blk ? [...blk.querySelectorAll('.actions-toolbar a')].filter(vis).map(st) : [],
    socialTitle: st(one(document.querySelector('#mp-popup-social-content'), '.block-title')),
    social: st(one(document.querySelector('#mp-popup-social-content'), 'a.btn-social, .actions-toolbar.social-btn a, .actions-toolbar.social-btn button')),
    close: st(close),
  };
}
async function openHome(browser, vp) {
  const ctx = await browser.newContext({ viewport: { width: vp, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(4000);
  return { ctx, page, open: async () => { await page.evaluate(() => onClick()); await page.waitForTimeout(1500); } };
}
async function openCheckout(browser, vp) {
  const ctx = await browser.newContext({ viewport: { width: vp, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.locator('#product-addtocart-button').first().click();
  await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
  await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(6000);
  return { ctx, page, open: async () => { await page.click('.osc-authentication-wrapper a'); await page.waitForTimeout(2000); } };
}
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome', args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const res = {};
  for (const vp of VPS) for (const [name, fn] of [['home', openHome], ['checkout', openCheckout]]) {
    if (TAG !== 'x' && name === 'home' && TAG !== 'before') continue;
    const { ctx, page, open } = await fn(browser, vp);
    await open();
    for (const view of ['login', 'create', 'forgot']) {
      if (view !== 'login') {
        await page.locator('#social-login-popup .social-login.block-container:not(.authentication) .action.back').filter({ visible: true }).first().click({ timeout: 3000 }).catch(() => {});
        await page.evaluate(() => typeof showLogin === 'function' && showLogin()).catch(() => {});
        await page.waitForTimeout(400);
        const sel = view === 'create' ? '#social-login-popup .social-login.authentication .action.create' : '#social-login-popup .social-login.authentication .action.remind';
        await page.locator(sel).first().click({ timeout: 5000 }).catch(e => console.log(name, view, 'click err', e.message.slice(0, 80)));
        await page.waitForTimeout(1200);
      }
      await page.screenshot({ path: `${OUT}/${TAG}-${name}-${view}-${vp}.png` });
      res[`${name}-${view}-${vp}`] = await page.evaluate(measure);
    }
    await ctx.close();
  }
  require('fs').writeFileSync(`${OUT}/${TAG}-metrics.json`, JSON.stringify(res, null, 1));
  await browser.close();
  console.log('done');
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
