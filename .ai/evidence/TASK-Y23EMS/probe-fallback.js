const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext()).newPage();
  const posts = [];
  page.on('request', r => { if (r.method() === 'POST' && r.url().includes('checkout/cart/add')) posts.push(r.url()); });
  await page.goto('http://slaunchpad.localhost/joust-duffle-bag.html', { waitUntil: 'networkidle' });
  await page.click('#product-addtocart-button');
  await page.waitForTimeout(3000);
  console.log(JSON.stringify({
    postsToCartAdd: posts.length,
    ajaxSubmitCartPresent: await page.evaluate(() => typeof window.ajaxSubmitCart),
    backOnPdp: page.url().includes('joust-duffle-bag'),
  }));
  await browser.close();
})();
