/**
 * BUG-NY0M3S — probe 2: why billingRuleConfig is empty.
 * Dumps OSC guest form fields, console errors verbatim, extra-fee AJAX response;
 * attempts heuristic fill of the shipping address, then measures the block.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-extrafee2.js
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const OUT = '/tmp/extrafee-probe2.json';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
    const page = await ctx.newPage();
    const errs = [];
    const ajax = [];
    page.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
    page.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0, 300)); });
    page.on('response', async r => {
        if (r.url().includes('extrafee') || r.url().includes('extra_fee')) {
            let body = '';
            try { body = (await r.text()).slice(0, 500); } catch (e) { body = 'ERR ' + e.message; }
            ajax.push({ url: r.url().slice(0, 160), status: r.status(), body });
        }
    });

    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(6000);

    // inventory of visible form controls in the address area
    const fields = await page.evaluate(() => {
        const vis = (e) => !!(e.offsetWidth || e.offsetHeight);
        const grab = (sel) => [...document.querySelectorAll(sel)]
            .filter(vis)
            .slice(0, 40)
            .map(e => ({ id: e.id || null, name: e.getAttribute('name'), type: e.tagName.toLowerCase() }));
        return { inputs: grab('input'), selects: grab('select'), textareas: grab('textarea') };
    });

    // heuristic fill (guest default shipping form)
    const filled = await page.evaluate(() => {
        const vis = (e) => !!(e.offsetWidth || e.offsetHeight);
        const set = (el, v) => {
            if (!el) return false;
            const proto = el.tagName === 'SELECT' ? window.HTMLSelectElement.prototype : window.HTMLInputElement.prototype;
            const setter = Object.getOwnPropertyDescriptor(proto, 'value').set;
            setter.call(el, v);
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        };
        const byName = (frag) => document.querySelector(`input[name*="${frag}"]:not([type=hidden])`);
        const out = {};
        out.email = set([...document.querySelectorAll('input[type=email]')].find(vis) || document.querySelector('#customer-email'), 'qc@example.com');
        out.firstname = set(byName('firstname'), 'QC');
        out.lastname = set(byName('lastname'), 'Tester');
        out.street0 = set(document.querySelector('input[name*="street[0]"], input[name*="street.0"]'), '12 Nguyen Chi Thanh');
        out.city = set(byName('city'), 'Ha Noi');
        out.postcode = set(byName('postcode'), '100000');
        out.phone = set(byName('telephone'), '0900000001');
        return out;
    });
    await page.waitForTimeout(3000);

    // select VN hierarchical dropdowns if present (province -> city -> ward), best effort
    const picked = await page.evaluate(() => {
        const out = [];
        const selects = [...document.querySelectorAll('select')].filter(s => (s.offsetWidth || s.offsetHeight) && /region|city|district|ward|province/i.test(s.name + ' ' + s.id));
        for (const s of selects.slice(0, 3)) {
            const opts = [...s.options].map(o => ({ v: o.value, t: o.textContent.trim() }));
            const target = opts.find(o => o.t && !['', 'Please select'].includes(o.t));
            if (target) {
                const setter = Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, 'value').set;
                setter.call(s, target.v);
                s.dispatchEvent(new Event('change', { bubbles: true }));
                out.push({ id: s.id || s.name, picked: target.t });
            }
        }
        return out;
    });
    await page.waitForTimeout(8000); // AJAX cascades + extra-fee update

    const block = await page.evaluate(() => {
        const form = document.querySelector('#mp-extra-fee-billing');
        if (!form) return { present: false };
        const rect = (el) => { if (!el) return null; const r = el.getBoundingClientRect(); return { top: +r.top.toFixed(1), bottom: +r.bottom.toFixed(1), h: +r.height.toFixed(1) }; };
        const dtLabel = form.querySelector('dt > label.label');
        const firstInput = form.querySelector('dd input');
        const cs = (el, props) => { if (!el) return null; const c = getComputedStyle(el); const o = {}; props.forEach(p => o[p] = c[p]); return o; };
        return {
            present: true,
            visible: !!(form.offsetWidth || form.offsetHeight),
            innerLength: form.innerHTML.length,
            gap: (dtLabel && firstInput) ? +(firstInput.getBoundingClientRect().top - dtLabel.getBoundingClientRect().bottom).toFixed(1) : null,
            labelText: (form.querySelector('dt label.label span') || {}).textContent || null,
            rectLabel: rect(dtLabel), rectInput: rect(firstInput),
            ddMargin: cs(form.querySelector('dd'), ['marginTop', 'marginBottom']),
            dtMargin: cs(form.querySelector('dt'), ['marginTop', 'marginBottom']),
            dtLabelMargin: cs(dtLabel, ['marginBottom', 'paddingBottom', 'lineHeight']),
            wrapMargin: cs(form.querySelector('dl > div'), ['marginTop', 'marginBottom']),
            ruleMargin: cs(form.querySelector('dd .rule'), ['marginTop', 'marginBottom']),
            formDisplay: getComputedStyle(form).display,
        };
    });

    const out = { fields, filled, picked, block, ajax, errs: errs.slice(0, 10) };
    fs.writeFileSync(OUT, JSON.stringify(out, null, 2));
    console.log(JSON.stringify({ filled, picked, block, ajaxCount: ajax.length, errs: errs.slice(0, 5) }, null, 2));
    await browser.close();
})();
