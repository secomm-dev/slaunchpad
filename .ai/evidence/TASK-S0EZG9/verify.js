const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
const SEL = 'div.fixed.bottom-6.right-6 button[aria-label]';

(async () => {
  const b = await chromium.launch();
  const errors = [];

  // ---------- Desktop 1440 (vi) ----------
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  p.on('pageerror', e => errors.push('desktop pageerror: ' + e.message));
  await p.goto(BASE + '/', { waitUntil: 'load' });
  await p.waitForTimeout(900);

  const btn = p.locator(SEL);
  console.log('AC-002a hidden at scrollY=0:', !(await btn.isVisible()) ? 'PASS' : 'FAIL');

  // scroll to exactly 1 viewport -> still hidden (threshold is strictly greater)
  await p.evaluate(() => window.scrollTo({ top: Math.round(window.innerHeight), behavior: 'instant' }));
  await p.waitForTimeout(350);
  console.log('AC-002d hidden at exactly 1 viewport:', !(await btn.isVisible()) ? 'PASS' : 'FAIL (visible)');

  // scroll past 1 viewport -> visible
  await p.evaluate(() => window.scrollTo({ top: Math.round(window.innerHeight * 1.5), behavior: 'instant' }));
  await p.waitForTimeout(400);
  console.log('AC-002b visible past 1 viewport:', (await btn.isVisible()) ? 'PASS' : 'FAIL');

  const box = await btn.boundingBox();
  console.log('AC-005 desktop bottom-right:', (box && box.x + box.width > 1300 && box.y + box.height > 800) ? `PASS (${Math.round(box.x)},${Math.round(box.y)})` : `FAIL ${JSON.stringify(box)}`);

  // AC-003 smooth scroll: many incremental scroll events + ends at 0
  await p.evaluate(() => { window.__bttLog = []; window.addEventListener('scroll', () => window.__bttLog.push(Math.round(window.scrollY)), { passive: true }); });
  await btn.click();
  await p.waitForTimeout(400);
  const midY = await p.evaluate(() => Math.round(window.scrollY));
  await p.waitForTimeout(1800);
  const log = await p.evaluate(() => window.__bttLog);
  const endY = await p.evaluate(() => Math.round(window.scrollY));
  console.log(`AC-003 smooth (mid=${midY}, end=${endY}, events=${log.length}):`, (endY === 0 && log.length > 3) ? 'PASS' : 'FAIL');
  await p.waitForTimeout(350);
  console.log('AC-002c hidden again at top:', !(await btn.isVisible()) ? 'PASS' : 'FAIL');

  // AC-004 icon-only
  const btnText = (await btn.innerText()).trim();
  const svgCount = await btn.locator('svg').count();
  const fill = await btn.evaluate(el => getComputedStyle(el).backgroundColor);
  console.log(`AC-004 icon-only (svg=${svgCount}, text="${btnText}", bg=${fill}):`, (svgCount === 1 && btnText === '' && fill === 'rgb(69, 116, 76)') ? 'PASS' : 'FAIL');

  // TC-6 keyboard
  await p.evaluate(() => window.scrollTo({ top: Math.round(window.innerHeight * 2), behavior: 'instant' }));
  await p.waitForTimeout(400);
  await btn.focus();
  await p.keyboard.press('Enter');
  await p.waitForTimeout(1800);
  const kbY = await p.evaluate(() => Math.round(window.scrollY));
  console.log('TC-6 keyboard Enter:', kbY === 0 ? 'PASS' : `FAIL (y=${kbY})`);

  // AC-006 overlays = native <dialog> (top layer) — structural check
  const dlgInfo = await p.evaluate(() => ({
    drawer: !!document.querySelector('dialog'), // any native dialog on page
    dialogs: Array.from(document.querySelectorAll('dialog')).map(d => d.className.slice(0, 40))
  }));
  console.log('AC-006 native <dialog> overlays present:', dlgInfo.dialogs.length >= 1 ? `PASS (dialogs=${dlgInfo.dialogs.length})` : `WARN none found ${JSON.stringify(dlgInfo)}`);

  await p.screenshot({ path: '/var/www/projects/slaunchpad/.ai/evidence/TASK-S0EZG9/desktop-scrolled.png' });

  // ---------- Mobile 375 ----------
  const pm = await (await b.newContext({
    viewport: { width: 375, height: 812 }, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1'
  })).newPage();
  pm.on('pageerror', e => errors.push('mobile pageerror: ' + e.message));
  await pm.goto(BASE + '/', { waitUntil: 'load' });
  await pm.waitForTimeout(900);
  const mbtn = pm.locator(SEL);
  console.log('AC-002 mobile hidden at top:', !(await mbtn.isVisible()) ? 'PASS' : 'FAIL');
  await pm.evaluate(() => window.scrollTo({ top: Math.round(window.innerHeight * 1.5), behavior: 'instant' }));
  await pm.waitForTimeout(400);
  const mvis = await mbtn.isVisible();
  const mbox = await mbtn.boundingBox();
  const noHScroll = await pm.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);
  console.log('AC-005 mobile visible bottom-right:', (mvis && mbox && mbox.x + mbox.width > 300 && mbox.y + mbox.height > 700) ? `PASS (${Math.round(mbox.x)},${Math.round(mbox.y)})` : `FAIL ${JSON.stringify(mbox)}`);
  console.log('AC-005 mobile no horizontal overflow:', noHScroll ? 'PASS' : 'FAIL');
  // mobile click
  await mbtn.click();
  await pm.waitForTimeout(1800);
  const mY = await pm.evaluate(() => Math.round(window.scrollY));
  console.log('AC-003 mobile click to top:', mY === 0 ? 'PASS' : `FAIL (y=${mY})`);
  await pm.screenshot({ path: '/var/www/projects/slaunchpad/.ai/evidence/TASK-S0EZG9/mobile-scrolled.png' });

  console.log('pageerrors:', errors.length === 0 ? '0 (PASS)' : errors.join(' | '));
  await b.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
