/**
 * TASK-8TXS2P Round 6 — probe the social-login modal on OSC:
 * (a) which js-translation.json the page actually requests (SLP-225 scope question)
 * (b) actions-toolbar gap: submit button vs secondary link (login/create/forgot views)
 * (c) mage-error geometry on invalid submit (overlap with the next field?)
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-round6.js <tag>
 */
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = 'http://slaunchpad.localhost';
const OUT = '/tmp/slp203';
const tag = process.argv[2] || 'before';

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    const transFiles = [];
    page.on('response', r => { if (/js-translation/.test(r.url())) transFiles.push(r.url()); });

    // cart + checkout
    await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForTimeout(5000);

    await page.click('.osc-authentication-wrapper a');
    await page.waitForTimeout(2000);

    const rect = sel => {
        const el = document.querySelector(sel);
        if (!el || !el.offsetWidth) return null;
        const b = el.getBoundingClientRect();
        return { x: +b.x.toFixed(1), right: +b.right.toFixed(1), y: +b.y.toFixed(1), bottom: +b.bottom.toFixed(1), w: +b.width.toFixed(1), h: +b.height.toFixed(1) };
    };

    // view state dump + measurement helper inside the modal
    async function measure(viewName) {
        return await page.evaluate(({ viewName, rectSrc }) => {
            const rect = eval('(' + rectSrc + ')');
            const res = { view: viewName };
            const visible = sel => { const el = document.querySelector(sel); return el && el.offsetWidth > 0; };
            res.viewsVisible = {
                authentication: visible('.social-login.block-container.authentication'),
                create: visible('.social-login.block-container.create'),
                forgot: visible('.social-login.block-container.forgot'),
            };
            const primarySel = {
                login: '#social-login-popup .authentication #bnt-social-login-authentication',
                create: '#button-create-social',
                forgot: '#bnt-social-login-forgot',
            }[viewName];
            res.primary = rect(primarySel);
            // the secondary link in the SAME toolbar as the primary button
            const btn = document.querySelector(primarySel);
            if (btn) {
                const tb = btn.closest('.actions-toolbar');
                const sec = tb ? tb.querySelector('.secondary a.action') : null;
                res.secondary = sec && sec.offsetWidth ? rectSrc && (() => { const b = sec.getBoundingClientRect(); return { x: +b.x.toFixed(1), right: +b.right.toFixed(1), y: +b.y.toFixed(1), bottom: +b.bottom.toFixed(1), w: +b.width.toFixed(1), h: +b.height.toFixed(1) }; })() : null;
                if (res.primary && res.secondary) res.gap = +(res.secondary.x - res.primary.right).toFixed(1);
                res.toolbar = rectSrc && (() => { const b = tb.getBoundingClientRect(); return { x: +b.x.toFixed(1), w: +b.width.toFixed(1), h: +b.height.toFixed(1) }; })();
                res.secStyle = sec ? (({ marginLeft, marginRight, paddingLeft }) => ({ marginLeft, marginRight, paddingLeft }))(getComputedStyle(sec)) : null;
                res.tbDisplay = getComputedStyle(tb).display;
            }
            return res;
        }, { viewName, rectSrc: rect.toString() });
    }

    const out = { tag, transFiles: [] };

    // ---- login view ----
    const login = await measure('login');
    out.login = login;

    // ---- forgot view ----
    await page.click('#social-login-popup .authentication a.action.remind');
    await page.waitForTimeout(800);
    out.forgot = await measure('forgot');
    // invalid email submit
    await page.fill('#email_address_forgot', 'ew');
    await page.click('#bnt-social-login-forgot');
    await page.waitForTimeout(800);
    out.forgotError = await page.evaluate(rectSrc => eval('(' + rectSrc + ')') ? null : null); // placeholder
    out.forgotError = await page.evaluate(() => {
        const err = document.querySelector('#social-form-password-forget .mage-error, #social-form-password-forget + .mage-error, .social-login.block-container.forgot .mage-error');
        const input = document.querySelector('#email_address_forgot');
        if (!err) return { text: null };
        const b = err.getBoundingClientRect(), ib = input.getBoundingClientRect();
        return { text: err.textContent.trim(), position: getComputedStyle(err).position, bottom: +b.bottom.toFixed(1), inputBottom: +ib.bottom.toFixed(1), h: +b.height.toFixed(1) };
    });
    await page.screenshot({ path: `${OUT}/r6-${tag}-forgot.png` });

    // ---- create view ----
    await page.click('#social-login-popup .forgot a.action.back');
    await page.waitForTimeout(600);
    await page.click('#social-login-popup .authentication a.action.create');
    await page.waitForTimeout(800);
    out.create = await measure('create');

    // fill invalid values (mirror the user's screenshot 4): skip firstname, lastname 'n', email 'nn', pwd 3, confirm mismatch
    const f = async (sel, v) => { const el = page.locator(sel); if (await el.count()) { await el.fill(v).catch(() => {}); } };
    await f('#social-form-create input[name="lastname"]', 'n');
    await f('#social-form-create input[name="email"]', 'nn');
    await f('#social-form-create input[name="password"]', '...');
    await f('#social-form-create input[name="password_confirmation"]', '......');
    await page.click('#button-create-social');
    await page.waitForTimeout(1000);
    out.createErrors = await page.evaluate(rectSrc => {
        const rect = eval('(' + rectSrc + ')');
        const errs = [...document.querySelectorAll('.social-login.block-container.create .mage-error')];
        return errs.map(err => {
            const cs = getComputedStyle(err);
            const field = err.closest('.field');
            // next visible sibling field's label
            let next = field && field.nextElementSibling;
            let nextTop = null, nextLabel = null;
            while (next) {
                if (next.offsetWidth > 0) {
                    const lbl = next.querySelector('label.label') || next;
                    nextTop = +lbl.getBoundingClientRect().top.toFixed(1);
                    nextLabel = (lbl.textContent || '').trim().slice(0, 30);
                    break;
                }
                next = next.nextElementSibling;
            }
            const b = err.getBoundingClientRect();
            return { text: err.textContent.trim(), position: cs.position, top: +b.top.toFixed(1), bottom: +b.bottom.toFixed(1), h: +b.height.toFixed(1), fieldBottom: field ? +field.getBoundingClientRect().bottom.toFixed(1) : null, nextTop, nextLabel, overlapsNext: nextTop !== null && b.bottom > nextTop };
        });
    }, rect.toString());
    await page.screenshot({ path: `${OUT}/r6-${tag}-create.png`, fullPage: true });

    await browser.close();
    out.transFiles = transFiles;
    fs.writeFileSync(`${OUT}/r6-${tag}-probe.json`, JSON.stringify(out, null, 2));
    console.log(JSON.stringify(out, null, 2));
})().catch(e => { console.error('SCRIPT ERROR:', e); process.exit(1); });
