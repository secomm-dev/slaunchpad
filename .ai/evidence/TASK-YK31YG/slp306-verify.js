// SLP-306 — footer newsletter validation misalignment verify.
// Fix: form lg:items-center -> lg:items-start (subscribe.phtml).
// Asserts: with an invalid email, button.top === input.top (desktop row) and the
// error ul sits below the input; simulates the pre-fix items-center to prove cause.
const { chromium } = require('playwright');

const OUT = '/tmp/pw-cal';
const results = [];
const ok = (name, cond, extra = '') => {
  results.push(`${cond ? 'PASS' : 'FAIL'} ${name}${extra ? ' — ' + extra : ''}`);
  return cond;
};

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1', '--disable-dev-shm-usage', '--disable-gpu'] });

  // Geometry helper: first form found (footer one via closest footer.page-footer)
  const measure = () => {
    const forms = Array.from(document.querySelectorAll('form#homepage-newsletter-form'));
    return forms.map((form) => {
      const inFooter = !!form.closest('footer.page-footer, .footer-section-newsletter');
      const input = form.querySelector('input[name="email"]');
      const btn = form.querySelector('button[type="submit"]');
      const field = input && input.closest('.field.field-reserved');
      const ul = field && field.querySelector('ul.messages');
      const ir = input && input.getBoundingClientRect();
      const br = btn && btn.getBoundingClientRect();
      const ur = ul && ul.getBoundingClientRect();
      return {
        inFooter,
        formClass: form.className.includes('items-start') ? 'items-start' : (form.className.includes('items-center') ? 'items-center' : 'none'),
        inputTop: ir ? Math.round(ir.top) : null,
        btnTop: br ? Math.round(br.top) : null,
        delta: ir && br ? Math.round(br.top - ir.top) : null,
        fieldError: field && field.classList.contains('field-error'),
        hasUl: !!ul,
        ulBelowInput: ir && ur ? ur.top >= ir.bottom - 2 : null,
        ulText: ul ? ul.textContent.trim().slice(0, 80) : null,
      };
    });
  };

  const submitInvalid = async (page) => {
    await page.evaluate(() => {
      document.querySelectorAll('form#homepage-newsletter-form input[name="email"]').forEach((i) => {
        i.value = 'aaaaa';
        i.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    await page.waitForTimeout(400);
  };

  const runCase = async (label, { viewport, enCookie }) => {
    const ctx = await browser.newContext({ viewport });
    const page = await ctx.newPage();
    await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    if (enCookie) {
      await page.evaluate(() => { document.cookie = 'store=launchpad_en; path=/'; });
      await page.goto('http://slaunchpad.localhost/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    }
    await page.waitForSelector('form#homepage-newsletter-form', { timeout: 15000 });

    // AFTER (fix live): submit invalid email
    await submitInvalid(page);
    const after = await page.evaluate(measure);
    const footerAfter = after.find((f) => f.inFooter) || after[0];
    const isRow = viewport.width >= 1024;

    if (isRow) {
      ok(`${label}: button aligns with input when error shows (delta=0)`, footerAfter.delta === 0, `delta=${footerAfter.delta}px form=${footerAfter.formClass}`);
    } else {
      ok(`${label}: stacked — button below field (delta>=0)`, footerAfter.delta > 0, `delta=${footerAfter.delta}px`);
    }
    ok(`${label}: .field-error + messages ul rendered`, footerAfter.fieldError && footerAfter.hasUl, `msg="${footerAfter.ulText}"`);
    ok(`${label}: error ul sits below input`, footerAfter.ulBelowInput === true);

    // BEFORE simulation: swap to items-center (still in compiled CSS), re-validate
    await page.evaluate(() => {
      document.querySelectorAll('form#homepage-newsletter-form').forEach((f) => {
        f.classList.remove('lg:items-start');
        f.classList.add('lg:items-center');
      });
    });
    await submitInvalid(page);
    const before = await page.evaluate(measure);
    const footerBefore = before.find((f) => f.inFooter) || before[0];
    if (isRow) {
      ok(`${label}: sim items-center reproduces the drop (delta>0)`, footerBefore.delta > 0, `delta=${footerBefore.delta}px`);
    }

    // Screenshot of the live (fixed) state — reload to restore real classes
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForSelector('form#homepage-newsletter-form', { timeout: 15000 });
    await submitInvalid(page);
    const tag = label.replace(/[^a-z0-9-]+/gi, '-').toLowerCase();
    await page.evaluate(() => {
      const el = document.querySelector('footer.page-footer .footer-section-newsletter') || document.querySelector('footer.page-footer');
      el && el.scrollIntoView({ block: 'start' });
    });
    await page.waitForTimeout(300);
    const clip = await page.evaluate(() => {
      const el = document.querySelector('footer.page-footer .footer-section-newsletter') || document.querySelector('footer.page-footer');
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { x: 0, y: Math.max(0, r.top), width: r.width, height: Math.min(r.height, 500) };
    });
    if (clip) {
      await page.screenshot({ path: `${OUT}/slp306-${tag}.png`, clip });
    }
    // horizontal overflow check (mobile)
    if (!isRow) {
      const sw = await page.evaluate(() => document.documentElement.scrollWidth);
      ok(`${label}: no horizontal overflow`, sw <= viewport.width, `scrollWidth=${sw} vp=${viewport.width}`);
    }
    await ctx.close();
  };

  await runCase('vi-desktop-1440', { viewport: { width: 1440, height: 900 }, enCookie: false })
    .catch((e) => results.push('ERROR vi-desktop: ' + String(e).slice(0, 120)));
  await runCase('vi-mobile-375', { viewport: { width: 375, height: 812 }, enCookie: false })
    .catch((e) => results.push('ERROR vi-mobile: ' + String(e).slice(0, 120)));
  await runCase('en-desktop-1440', { viewport: { width: 1440, height: 900 }, enCookie: true })
    .catch((e) => results.push('ERROR en-desktop: ' + String(e).slice(0, 120)));

  await browser.close().catch(() => {});
  console.log(results.join('\n'));
})().catch((e) => { console.error('PROBE-ERROR', e); process.exit(1); });
