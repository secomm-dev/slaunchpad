/* Runtime check BUG-5S2Z25 — forgotpassword form validation (vi store) */
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({
    args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
  });
  const page = await browser.newPage();
  const consoleErrors = [];
  page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text().slice(0, 200)); });
  page.on('pageerror', e => consoleErrors.push('PAGEERROR: ' + String(e).slice(0, 200)));

  const url = 'http://slaunchpad.localhost/customer/account/forgotpassword/';
  await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });

  const form = page.locator('#user_forgotpassword');
  await form.waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForTimeout(1200); // alpine init

  const results = {};

  // 1. novalidate set tại runtime bởi hyva.formValidation
  results.novalidate = await form.evaluate(el => el.hasAttribute('novalidate'));

  // 2. captcha container còn trong DOM (regression)
  results.captchaContainer = await page.locator('#grecaptcha-container-Customerforgotpassword').count();

  // 3. submit email rỗng → message VI, không navigate
  await page.click('#user_forgotpassword button[type="submit"]');
  await page.waitForTimeout(1200);
  results.urlAfterEmptySubmit = page.url();
  const emptyMsg = await page.locator('#user_forgotpassword ul.messages li, #user_forgotpassword .messages li').allInnerTexts();
  results.emptySubmitMessages = emptyMsg;

  // 4. submit email sai format → message VI email
  await page.fill('#email_address', 'khong-hop-le');
  await page.click('#user_forgotpassword button[type="submit"]');
  await page.waitForTimeout(1200);
  results.urlAfterInvalidSubmit = page.url();
  const invalidMsg = await page.locator('#user_forgotpassword ul.messages li, #user_forgotpassword .messages li').allInnerTexts();
  results.invalidSubmitMessages = invalidMsg;

  // 5. field-error class state
  results.fieldErrorClass = await page.locator('#user_forgotpassword .field-error').count();

  results.consoleErrors = consoleErrors;
  console.log(JSON.stringify(results, null, 2));
  await browser.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
