// SLP-306 sanity: valid email -> no error state (onChange validation), form count on homepage.
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1', '--disable-dev-shm-usage', '--disable-gpu'] });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForSelector('form#homepage-newsletter-form', { timeout: 15000 });
  const out = await page.evaluate(async () => {
    const forms = Array.from(document.querySelectorAll('form#homepage-newsletter-form'));
    const info = forms.map((f) => ({
      inFooter: !!f.closest('footer.page-footer, .footer-section-newsletter'),
      inMain: !!(f.closest('main') || (!f.closest('footer.page-footer') && !!f.closest('body')) && !f.closest('footer')),
      cls: f.className.slice(0, 60),
    }));
    // valid email on the footer form
    const footerForm = forms.find((f) => f.closest('footer.page-footer, .footer-section-newsletter')) || forms[0];
    const input = footerForm.querySelector('input[name="email"]');
    input.value = 'slp306-test@example.com';
    input.dispatchEvent(new Event('change', { bubbles: true }));
    await new Promise((r) => setTimeout(r, 400));
    const field = input.closest('.field');
    return {
      formCount: forms.length,
      info,
      fieldClass: field.className,
      hasError: field.classList.contains('field-error'),
      hasUl: !!field.querySelector('ul.messages'),
    };
  });
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch((e) => { console.error('ERR', e); process.exit(1); });
