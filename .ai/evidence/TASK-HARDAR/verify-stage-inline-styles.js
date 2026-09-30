// TASK-HARDAR: mô phỏng PageBuilder stage-builder.js convertToInlineStyles() trên content
// (querySelector(selector) cho mọi rule <style> — null ⇒ TypeError ⇒ stage chết).
// Kèm check JSON.parse data-background-images (LL-0039).
const fs = require('fs');
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.PW_CHROME || undefined });
  const page = await browser.newPage();
  for (const f of ['home-content-before-cat-widget.txt', 'home-content-before-orphan-prune.txt', 'home-content-after-orphan-prune.txt', 'home-content-after-text-element.txt', '../../../app/code/Launchpad/CmsContent/etc/homepage-content.html']) {
    const html = fs.readFileSync(`${__dirname}/${f}`, 'utf8');
    const res = await page.evaluate((value) => {
      const doc = new DOMParser().parseFromString(value, 'text/html');
      doc.body.id = 'html-body';
      const out = { nullSelectors: [], badJson: 0, contentTypes: doc.querySelectorAll('[data-content-type]').length };
      // DOMParser docs have no CSSOM → parse via a live <style> element like the stage document does.
      Array.from(doc.getElementsByTagName('style')).forEach((styleBlock) => {
        const live = document.createElement('style');
        live.textContent = styleBlock.textContent;
        document.head.appendChild(live);
        const walk = (rules) => Array.from(rules).forEach((r) => {
          if (r.cssRules && !r.selectorText) return walk(r.cssRules);
          if (r.selectorText) r.selectorText.split(',').forEach((s) => { if (!doc.querySelector(s.trim())) out.nullSelectors.push(s.trim()); });
        });
        walk(live.sheet.cssRules);
        live.remove();
      });
      doc.querySelectorAll('[data-background-images]').forEach((el) => { try { JSON.parse(el.getAttribute('data-background-images').replace(/\\(.)/g, '$1')); } catch (e) { out.badJson++; } });
      out.nullCount = out.nullSelectors.length;
      out.nullSelectors = out.nullSelectors.slice(0, 3);
      return out;
    }, html);
    console.log(f.split('/').pop(), JSON.stringify(res));
  }
  await browser.close();
})();
