// SLP-291 (TASK-KMJV5Q) — E2E: admin UI config save → storefront footer legal links.
const { chromium } = require('playwright');
const fs = require('fs');

const cred = fs.readFileSync('/tmp/hp-admin-cred.txt', 'utf8').match(/PW=(.*)/)[1].trim();
const BASE = 'http://slaunchpad.localhost';

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1', '--disable-dev-shm-usage', '--disable-gpu'] });
  const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
  const page = await ctx.newPage();
  const pageErrors = [];
  page.on('pageerror', (e) => pageErrors.push(String(e).slice(0, 300)));

  // Storefront baseline probe (5 footer sections + accordion script)
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  const storefront = await page.evaluate(() => ({
    newsletter: !!document.querySelector('.footer-section-newsletter'),
    links: !!document.querySelector('.footer-section-links'),
    social: !!document.querySelector('.footer-section-social'),
    trust: !!document.querySelector('.footer-section-trust'),
    copyrightBar: !!document.querySelector('nav[aria-label] a[href*="terms-and-conditions"], nav[aria-label] a[href*="about-us"], nav[aria-label] a'),
    accordion: !!document.querySelector('script') && document.body.innerHTML.includes('initFooterLinksAccordion'),
  }));
  console.log('STOREFRONT SECTIONS:', JSON.stringify(storefront));
  await page.screenshot({ path: '/tmp/pw-cal/slp291-storefront.png', fullPage: false });

  // Admin login
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForSelector('input[name="login[username]"]', { timeout: 30000 });
  await page.fill('input[name="login[username]"]', 'claudetmp');
  await page.fill('input[name="login[password]"]', cred);
  await page.click('.action-login');
  await page.waitForSelector('.page-header', { timeout: 60000 });
  console.log('ADMIN LOGIN OK');

  // Configuration: Stores menu → Configuration → Launchpad > Footer section
  await page.hover('#menu-magento-backend-stores');
  await page.click('#menu-magento-backend-stores a[href*="system_config/index"]');
  await page.waitForSelector('a[href*="section/launchpad_footer"], #launchpad_footer', { timeout: 30000 });
  await page.click('a[href*="section/launchpad_footer"], #launchpad_footer');
  await page.waitForSelector('#launchpad_footer_legal_links_terms_label', { timeout: 30000 });
  const fields = await page.evaluate(() =>
    ['terms_label', 'terms_url', 'privacy_label', 'privacy_url']
      .map((f) => `#launchpad_footer_legal_links_${f}`)
      .map((sel) => ({ sel, present: !!document.querySelector(sel) }))
  );
  console.log('ADMIN FIELDS:', JSON.stringify(fields));
  await page.screenshot({ path: '/tmp/pw-cal/slp291-admin-section.png', fullPage: true });

  // Fill at Default Config scope + save
  await page.fill('#launchpad_footer_legal_links_terms_label', 'E2E UI Terms');
  await page.fill('#launchpad_footer_legal_links_terms_url', 'privacy-policy-cookie-restriction-mode');
  await page.click('#save');
  await page.waitForSelector('.message-success', { timeout: 30000 });
  console.log('ADMIN SAVE OK');
  await page.screenshot({ path: '/tmp/pw-cal/slp291-admin-saved.png' });

  // Storefront reflects the admin save
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  const after = await page.evaluate(() => {
    const a = Array.from(document.querySelectorAll('nav[aria-label] a'));
    const terms = a.find((x) => /terms/i.test(x.textContent) || /privacy-policy/i.test(x.href));
    return a.map((x) => ({ label: x.textContent.trim(), href: x.getAttribute('href') }));
  });
  console.log('STOREFRONT AFTER ADMIN SAVE:', JSON.stringify(after));
  const termsHit = after.some((x) => x.label === 'E2E UI Terms' && /privacy-policy-cookie-restriction-mode\/?$/.test(x.href));
  console.log('AC-003 E2E (admin UI save -> storefront):', termsHit);
  await page.screenshot({ path: '/tmp/pw-cal/slp291-storefront-after-save.png', fullPage: true });

  // Restore: clear both fields, save again, storefront back to fallback
  await page.hover('#menu-magento-backend-stores');
  await page.click('#menu-magento-backend-stores a[href*="system_config/index"]');
  await page.waitForSelector('a[href*="section/launchpad_footer"], #launchpad_footer', { timeout: 30000 });
  await page.click('a[href*="section/launchpad_footer"], #launchpad_footer');
  await page.waitForSelector('#launchpad_footer_legal_links_terms_label', { timeout: 30000 });
  await page.fill('#launchpad_footer_legal_links_terms_label', '');
  await page.fill('#launchpad_footer_legal_links_terms_url', '');
  await page.click('#save');
  await page.waitForSelector('.message-success', { timeout: 30000 });

  const res = await page.request.get(BASE + '/');
  const html = await res.text();
  console.log('FALLBACK RESTORED (no E2E UI Terms):', !html.includes('E2E UI Terms'));
  console.log('PAGEERRORS:', JSON.stringify(pageErrors));

  await browser.close();
})().catch((e) => { console.error('FAILED:', e.message); process.exit(1); });
