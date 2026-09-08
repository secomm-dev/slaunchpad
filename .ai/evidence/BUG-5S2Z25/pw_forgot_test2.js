/* BUG-5S2Z25 — EN store + captcha-fail path */
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({
    args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
  });

  // --- EN store ---
  const page = await browser.newPage();
  await page.goto('http://slaunchpad.localhost/customer/account/forgotpassword/?___store=launchpad_en', { waitUntil: 'networkidle', timeout: 45000 });
  await page.locator('#user_forgotpassword').waitFor({ timeout: 15000 });
  await page.waitForTimeout(1000);
  await page.click('#user_forgotpassword button[type="submit"]');
  await page.waitForTimeout(1200);
  const enMsg = await page.locator('#user_forgotpassword ul.messages li, #user_forgotpassword .messages li').allInnerTexts();
  console.log('EN empty-submit messages:', JSON.stringify(enMsg), '| url unchanged:', page.url().includes('/forgotpassword/?'));
  await page.close();

  // --- captcha-fail path (vi store, sitekey test 123123 → token không bao giờ có) ---
  const page2 = await browser.newPage();
  const errors = [];
  page2.on('pageerror', e => errors.push(String(e).slice(0, 120)));
  await page2.goto('http://slaunchpad.localhost/customer/account/forgotpassword/', { waitUntil: 'networkidle', timeout: 45000 });
  await page2.locator('#user_forgotpassword').waitFor({ timeout: 15000 });
  await page2.waitForTimeout(2500); // cho recaptcha script init
  await page2.fill('#email_address', 'test@example.com');
  await page2.click('#user_forgotpassword button[type="submit"]');
  await page2.waitForTimeout(1500);
  const flash = await page2.locator('.messages .message, [role="alert"], .message.error').allInnerTexts();
  const btnDisabled = await page2.locator('#user_forgotpassword button[type="submit"]').evaluate(el => el.disabled);
  console.log('captcha-fail: url unchanged:', page2.url().includes('forgotpassword/?') || !page2.url().includes('forgotpasswordpost'));
  console.log('captcha-fail flash messages:', JSON.stringify(flash));
  console.log('captcha-fail submit disabled:', btnDisabled);
  console.log('pageerrors:', JSON.stringify(errors));
  await browser.close();
})().catch(e => { console.error('FATAL', e); process.exit(1); });
