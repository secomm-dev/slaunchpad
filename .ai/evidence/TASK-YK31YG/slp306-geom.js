// SLP-306 micro-probe: where does the -4px come from (items-start state)?
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1', '--disable-dev-shm-usage', '--disable-gpu'] });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForSelector('form#homepage-newsletter-form', { timeout: 15000 });
  const out = await page.evaluate(() => {
    const form = Array.from(document.querySelectorAll('form#homepage-newsletter-form')).find((f) => f.closest('footer.page-footer, .footer-section-newsletter')) || document.querySelector('form#homepage-newsletter-form');
    const r = (el) => el ? { top: Math.round(el.getBoundingClientRect().top * 10) / 10, h: Math.round(el.getBoundingClientRect().height * 10) / 10 } : null;
    const m = (el) => el ? { mt: getComputedStyle(el).marginTop, mb: getComputedStyle(el).marginBottom, d: getComputedStyle(el).display, pt: getComputedStyle(el).paddingTop } : null;
    const field = form.querySelector('.field');
    const input = form.querySelector('input[name="email"]');
    const btn = form.querySelector('button[type="submit"]');
    const hidden = form.querySelector('input[type="hidden"]');
    const label = form.querySelector('label');
    return {
      formClass: form.className,
      label: { rect: r(label), style: m(label) },
      hidden: { rect: r(hidden), style: m(hidden) },
      field: { rect: r(field), style: m(field) },
      input: { rect: r(input), style: m(input) },
      btn: { rect: r(btn), style: m(btn) },
    };
  });
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch((e) => { console.error('ERR', e); process.exit(1); });
