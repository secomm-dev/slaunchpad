/**
 * TASK-8TXS2P Round 6 (after) — social-login modal on OSC:
 * toolbar gaps (login/create/forgot) + div.mage-error messages (VI?) +
 * overlap check at 1280 / 745 / 375.
 * Run: NODE_PATH=/tmp/pw-cal/node_modules node probe-round6b.js <tag>
 */
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = 'http://slaunchpad.localhost';
const OUT = '/tmp/slp203';
const tag = process.argv[2] || 'after';

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'] });
    const report = {};
    const passLog = [];
    const log = (name, pass, detail = '') => { passLog.push(`${pass ? 'PASS' : 'FAIL'} | ${name}${detail ? ' | ' + detail : ''}`); console.log(`${pass ? 'PASS' : 'FAIL'} | ${name}${detail ? ' | ' + detail : ''}`); };

    for (const vp of [{ w: 1280, h: 900, tag: '1280' }, { w: 745, h: 900, tag: '745' }, { w: 375, h: 812, tag: '375' }]) {
        const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h } });
        const page = await ctx.newPage();
        await page.goto(`${BASE}/atlas-pouf.html`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.locator('#product-addtocart-button').first().click();
        await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
        await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForTimeout(5000);
        await page.click('.osc-authentication-wrapper a');
        await page.waitForTimeout(2000);

        // DOM-click: modal content scrolls internally and buttons can sit
        // outside the browser viewport on small screens — Playwright's
        // actionability click times out there, a DOM click still fires the
        // jQuery handlers.
        const domClick = sel => page.evaluate(s => { const el = document.querySelector(s); if (!el) throw new Error('no ' + s); el.closest('.modal-content') && (el.closest('.modal-content').scrollTop = 0); el.click(); }, sel);

        const measure = vn => page.evaluate(viewName => {
            const box = el => { if (!el || !el.offsetWidth) return null; const b = el.getBoundingClientRect(); return { x: +b.x.toFixed(1), right: +b.right.toFixed(1), y: +b.y.toFixed(1), bottom: +b.bottom.toFixed(1), w: +b.width.toFixed(1), h: +b.height.toFixed(1) }; };
            const res = { view: viewName };
            const primarySel = { login: '#social-login-popup .authentication #bnt-social-login-authentication', create: '#button-create-social', forgot: '#bnt-social-login-forgot' }[viewName];
            const btn = document.querySelector(primarySel);
            res.primary = box(btn);
            const tb = btn && btn.closest('.actions-toolbar');
            const sec = tb && tb.querySelector('.secondary a.action');
            res.secondary = box(sec);
            if (res.primary && res.secondary) res.gap = +(res.secondary.x - res.primary.right).toFixed(1);
            return res;
        }, vn);

        const errsIn = scopeSel => page.evaluate(scopeSel => {
            const scope = document.querySelector(scopeSel);
            if (!scope) return null;
            return [...scope.querySelectorAll('div.mage-error')].map(err => {
                const b = err.getBoundingClientRect();
                const field = err.closest('.field');
                let next = field && field.nextElementSibling, nextTop = null, nextLabel = null;
                while (next) {
                    if (next.offsetWidth > 0) { const l = next.querySelector('label.label') || next; nextTop = +l.getBoundingClientRect().top.toFixed(1); nextLabel = (l.textContent || '').trim().slice(0, 26); break; }
                    next = next.nextElementSibling;
                }
                return { text: err.textContent.trim().slice(0, 90), bottom: +b.bottom.toFixed(1), nextTop, nextLabel, overlaps: nextTop !== null && b.bottom > nextTop };
            });
        }, scopeSel);

        // login
        report[vp.tag] = {};
        report[vp.tag].login = await measure('login');
        // forgot
        await domClick('#social-login-popup .authentication a.action.remind');
        await page.waitForTimeout(800);
        report[vp.tag].forgot = await measure('forgot');
        await page.fill('#email_address_forgot', 'ew');
        await domClick('#bnt-social-login-forgot');
        await page.waitForTimeout(900);
        report[vp.tag].forgotErrors = await errsIn('.social-login.block-container.forgot');
        await page.screenshot({ path: `${OUT}/r6-${tag}-${vp.tag}-forgot.png` });
        // create
        await domClick('#social-login-popup .forgot a.action.back');
        await page.waitForTimeout(600);
        await domClick('#social-login-popup .authentication a.action.create');
        await page.waitForTimeout(800);
        report[vp.tag].create = await measure('create');
        const fill = async (sel, v) => { const el = page.locator(sel); if (await el.count()) await el.fill(v).catch(() => {}); };
        await fill('#social-form-create input[name="lastname"]', 'n');
        await fill('#social-form-create input[name="email"]', 'nn');
        await fill('#social-form-create input[name="password"]', '...');
        await fill('#social-form-create input[name="password_confirmation"]', '......');
        await domClick('#button-create-social');
        await page.waitForTimeout(1000);
        report[vp.tag].createErrors = await errsIn('.social-login.block-container.create');
        await page.screenshot({ path: `${OUT}/r6-${tag}-${vp.tag}-create.png` });

        // assertions
        const r = report[vp.tag];
        const loginGapOk = (() => {
            const p = r.login.primary, s = r.login.secondary;
            if (!p || !s) return false;
            const sameRow = s.y < p.bottom && s.bottom > p.y;                 // vertical overlap = same row
            if (sameRow) return s.x - p.right >= 12;                          // beside the button: horizontal gap
            return s.y - p.bottom >= 8;                                       // wrapped below (luma <768px): vertical gap
        })();
        log(`${vp.tag} login link gap ok`, loginGapOk, `gap=${r.login.gap} primary=${JSON.stringify(r.login.primary)} secondary=${JSON.stringify(r.login.secondary)}`);
        const socialStacked = await page.evaluate(() => {
            const btns = [...document.querySelectorAll('#mp-popup-social-content a.btn-social')].filter(b => b.offsetWidth);
            const tops = new Set(btns.map(b => Math.round(b.getBoundingClientRect().top)));
            return btns.length > 0 && tops.size === btns.length;
        });
        log(`${vp.tag} social buttons still stacked`, socialStacked);
        log(`${vp.tag} forgot gap >= 12`, r.forgot.gap !== null && r.forgot.gap >= 12, `gap=${r.forgot.gap}`);
        log(`${vp.tag} create gap >= 12`, r.create.gap !== null && r.create.gap >= 12, `gap=${r.create.gap}`);
        const fe = (r.forgotErrors || []).filter(e => e.text);
        log(`${vp.tag} forgot email msg VI`, fe.some(e => e.text.startsWith('Vui lòng nhập địa chỉ email hợp lệ')), JSON.stringify(fe.map(e => e.text)));
        const ce = (r.createErrors || []).filter(e => e.text);
        log(`${vp.tag} create msgs VI (email+pwd+confirm)`, ce.length >= 3 && ce.every(e => !/^Please enter/.test(e.text)), JSON.stringify(ce.map(e => e.text)));
        log(`${vp.tag} create no error overlaps next label`, (r.createErrors || []).every(e => !e.overlaps), JSON.stringify((r.createErrors || []).filter(e => e.overlaps)));
        log(`${vp.tag} forgot no error overlaps`, (r.forgotErrors || []).every(e => !e.overlaps));
        await ctx.close();
    }
    await browser.close();
    fs.writeFileSync(`${OUT}/r6-${tag}-report.json`, JSON.stringify(report, null, 2));
    fs.writeFileSync(`${OUT}/r6-${tag}-results.log`, passLog.join('\n') + '\n');
    const fails = passLog.filter(l => l.startsWith('FAIL')).length;
    console.log('---');
    console.log(`SUMMARY: ${passLog.length - fails}/${passLog.length} PASS`);
    process.exit(fails ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR:', e); process.exit(1); });
