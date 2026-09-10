/**
 * BUG-AMRBJR (SLP-187) — conditional-message verification on OSC checkout.
 * Part A (guest, vi+en): email-exists note, Place Order button label,
 *   place-order-without-payment validation message, auth-link DOM state.
 * Part B (logged-in, vi): open new-address modal, capture 'Save in address book'.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node verify-conditional.js <out.json>
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const OUT = process.argv[2] || '/tmp/osc-i18n-conditional.json';
const EMAIL = 'qc-social@example.com';
const PASS = 'QcSocial123!';

const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const storeDefs = {
        vi: { entry: BASE + '/', cookie: null },
        en: { entry: BASE + '/?___store=launchpad_en', cookie: 'launchpad_en' },
    };
    const results = { guest: [], loggedIn: [] };

    // ---------- Part A: guest ----------
    for (const k of ['vi', 'en']) {
        const def = storeDefs[k];
        const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
        if (def.cookie) await ctx.addCookies([{ name: 'store', value: def.cookie, url: BASE }]);
        const page = await ctx.newPage();
        await page.goto(def.entry, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(6000);

        // auth-link DOM state (may be hidden by config)
        const authLink = await page.evaluate(() => {
            const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
            const el = document.querySelector('.osc-authentication-wrapper');
            if (!el) return { inDom: false };
            const cs = getComputedStyle(el);
            return { inDom: true, display: cs.display, text: norm(el.textContent) };
        });

        // email exists note
        await page.evaluate((email) => {
            const vis = (e) => !!(e.offsetWidth || e.offsetHeight);
            const el = document.querySelector('#customer-email') ||
                [...document.querySelectorAll('input[type=email]')].find(vis);
            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            setter.call(el, email);
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            el.dispatchEvent(new Event('blur', { bubbles: true }));
            el.focus();
            el.blur();
        }, EMAIL);
        await page.waitForTimeout(4000);
        const emailNote = await page.evaluate(() => {
            const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
            const notes = [...document.querySelectorAll('.note, .message, [class*="account"] span')]
                .map((n) => norm(n.textContent))
                .filter((t) => t && t.length < 200);
            return notes.find((t) => /account|tài khoản/i.test(t)) || null;
        });

        // fill guest fields + cascade
        await page.evaluate(() => {
            const vis = (e) => !!(e.offsetWidth || e.offsetHeight);
            const set = (el, v) => {
                if (!el) return;
                const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                setter.call(el, v);
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            };
            const byName = (frag) => [...document.querySelectorAll('input[name*="' + frag + '"]:not([type=hidden])')].find(vis);
            set(byName('firstname'), 'QC');
            set(byName('lastname'), 'Tester');
            set(document.querySelector('input[name*="street[0]"], input[name*="street.0"]'), '12 Nguyen Chi Thanh');
            set(byName('postcode'), '100000');
            set(byName('telephone'), '0900000001');
        });
        for (let round = 0; round < 4; round++) {
            const picked = await page.evaluate(() => {
                const out = [];
                const selects = [...document.querySelectorAll('select')]
                    .filter((s) => (s.offsetWidth || s.offsetHeight) && /region|city|district|ward|province/i.test(s.name + ' ' + s.id));
                for (const s of selects) {
                    const opt = [...s.options].find((o) => o.value && o.textContent.trim() && !/vui lòng chọn|please select/i.test(o.textContent));
                    if (opt && s.value !== opt.value) {
                        const setter = Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, 'value').set;
                        setter.call(s, opt.value);
                        s.dispatchEvent(new Event('change', { bubbles: true }));
                        out.push(s.id || s.name);
                    }
                }
                return out;
            });
            if (!picked.length) break;
            await page.waitForTimeout(2500);
        }

        // place order without payment -> validation message
        const btnLabel = await page.evaluate(() => {
            const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
            const b = [...document.querySelectorAll('button')].find((x) => /place order|đặt hàng/i.test(norm(x.textContent)));
            return b ? { text: norm(b.textContent), title: b.title || null } : null;
        });
        const btn = page.locator('button.action.primary.checkout, .action.primary.checkout').first();
        if (await btn.count()) await btn.click({ timeout: 5000 }).catch(() => {});
        await page.waitForTimeout(4000);
        const placeOrderMsg = await page.evaluate(() => {
            const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
            const cands = [...document.querySelectorAll('.message.error > div, .message.error, [class*="error"] div')]
                .map((m) => norm(m.textContent))
                .filter((t) => /payment method|phương thức thanh toán/i.test(t));
            return cands[0] || null;
        });

        results.guest.push({ store: k, authLink, emailNote, btnLabel, placeOrderMsg });
        await ctx.close();
    }

    // ---------- Part B: logged-in (vi) — new-address modal ----------
    try {
        const ctx = await browser.newContext({ viewport: { width: 1504, height: 940 } });
        const page = await ctx.newPage();
        await page.goto(BASE + '/customer/account/login/', { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.fill('#email', EMAIL);
        await page.fill('#pass', PASS);
        await page.locator('button[type=submit], .action.login').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        const loggedIn = await page.evaluate(() => /customer\/account/.test(location.href) && !/login/.test(location.href));
        if (loggedIn) {
            await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
            await page.waitForTimeout(6000);
            const addNew = page.locator('button:has-text("Địa chỉ mới"), button:has-text("New Address"), .osc-add-new-address, [class*="add-new"]').first();
            if (await addNew.count()) {
                await addNew.click({ timeout: 5000 }).catch(() => {});
                await page.waitForTimeout(2500);
                const modalText = await page.evaluate(() => {
                    const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
                    const m = document.querySelector('.modal-slide, .modal-popup, [role="dialog"]');
                    return m ? norm(m.textContent).slice(0, 3000) : null;
                });
                results.loggedIn.push({
                    store: 'vi', loggedIn,
                    saveInAddressBook: modalText && modalText.includes('Save in address book')
                        ? 'EN (BROKEN)' : (modalText && modalText.includes('Lưu vào sổ địa chỉ') ? 'VI (OK)' : 'not-found'),
                    modalSnippet: modalText ? modalText.slice(0, 400) : null,
                });
            } else {
                results.loggedIn.push({ store: 'vi', loggedIn, saveInAddressBook: 'add-new button not found' });
            }
        } else {
            results.loggedIn.push({ store: 'vi', loggedIn, saveInAddressBook: 'login failed' });
        }
        await ctx.close();
    } catch (e) {
        results.loggedIn.push({ store: 'vi', error: String(e).slice(0, 200) });
    }

    await browser.close();
    fs.writeFileSync(OUT, JSON.stringify(results, null, 2));
    console.log(JSON.stringify(results, null, 2));
})();
