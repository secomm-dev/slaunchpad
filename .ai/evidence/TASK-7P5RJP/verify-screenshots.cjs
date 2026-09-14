const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
  const shots = async (url, name, viewport, extra) => {
    const ctx = await browser.newContext({ viewport });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message.slice(0, 120)));
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(3000);
    if (extra) { try { await extra(page); } catch (e) { console.log(name, 'extra:', e.message.split('\n')[0]); } }
    await page.screenshot({ path: '/tmp/sl160/' + name + '.png', fullPage: name !== 'modal-vi' && name !== 'modal-en' });
    await ctx.close();
    console.log(name, errors.length ? 'JSERR:' + JSON.stringify(errors) : 'clean');
  };
  await shots('http://slaunchpad.localhost/customer/account/login/', 'login-vi', { width: 1280, height: 900 });
  await shots('http://slaunchpad.localhost/customer/account/login/', 'modal-vi', { width: 1280, height: 900 },
    async (p) => { await p.evaluate(() => onClick()); await p.waitForTimeout(1500); });
  await shots('http://slaunchpad.localhost/customer/account/create/', 'create-vi', { width: 1280, height: 900 });
  await shots('http://slaunchpad.localhost/customer/account/forgotpassword/', 'forgot-vi', { width: 1280, height: 900 });
  await shots('http://slaunchpad.localhost/customer/account/login/?___store=en', 'login-en', { width: 1280, height: 900 });
  await shots('http://slaunchpad.localhost/customer/account/login/?___store=en', 'modal-en', { width: 1280, height: 900 },
    async (p) => { await p.evaluate(() => onClick()); await p.waitForTimeout(1500); });
  await shots('http://slaunchpad.localhost/customer/account/login/', 'login-mobile', { width: 375, height: 812 });
  await shots('http://slaunchpad.localhost/customer/account/login/?___store=en', 'modal-mobile-en', { width: 375, height: 812 },
    async (p) => { await p.evaluate(() => onClick()); await p.waitForTimeout(1500); });
  await browser.close();
})();
