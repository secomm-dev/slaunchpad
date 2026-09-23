/* SLP-259 / TASK-E1YAHT — verify title vs button style differentiation in SocialLogin popup.
 * Asserts: title wrapper bg transparent, h2 color = ink token, button keeps config color.
 * Also switches to create view and re-checks, plus a 375px mobile shot.
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = 'http://slaunchpad.localhost';
const OUT = '/tmp/pw-cal/slp259-out';
fs.mkdirSync(OUT, { recursive: true });
const results = [];
const log = (k, v) => { results.push(k + ' = ' + v); console.log(k, '=', v); };

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const page = await browser.newPage({ viewport: { width: 1440, height: 940 } });
    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded', timeout: 60000 });

    // Open the login popup: try the header sign-in link first, fallback to window.openMyDialog()
    const opened = await page.evaluate(() => {
        const links = Array.from(document.querySelectorAll('a'))
            .filter(a => /openMyDialog|onClick\(/.test(a.getAttribute('onclick') || ''));
        if (links.length) { links[0].click(); return 'link:' + (links[0].textContent || '').trim().slice(0, 30); }
        if (typeof window.openMyDialog === 'function') { window.openMyDialog(); return 'openMyDialog()'; }
        return null;
    });
    log('OPENED-VIA', opened);
    await page.waitForTimeout(900); // vendor appear animation 500ms
    log('POPUP-VISIBLE', await page.evaluate(() => {
        const p = document.querySelector('#social-login-popup');
        return !!p && p.offsetParent !== null ? 'yes' : 'no';
    }));

    const grabStyles = () => page.evaluate(() => {
        const cs = (el, p) => el ? getComputedStyle(el).getPropertyValue(p) : 'N/A';
        const wrap = document.querySelector('.social-login.block-container:not([style*="display: none"]) .social-login-title');
        const h2 = wrap ? wrap.querySelector('h2') : null;
        const btn = document.querySelector('#bnt-social-login-authentication');
        return {
            view: (document.querySelector('.social-login.block-container:not([style*="display: none"])') || {}).className || 'N/A',
            titleWrapBg: cs(wrap, 'background-color'),
            titleWrapPadding: cs(wrap, 'padding'),
            h2Color: cs(h2, 'color'),
            h2Font: cs(h2, 'font-size') + ' / w' + cs(h2, 'font-weight'),
            btnBg: cs(btn, 'background-color'),
        };
    });

    const loginStyles = await grabStyles();
    log('LOGIN-VIEW', JSON.stringify(loginStyles));
    await page.screenshot({ path: OUT + '/popup-login-1440.png' });

    // Switch to create view (createBtn() is a vendor global)
    await page.evaluate(() => typeof createBtn === 'function' && createBtn());
    await page.waitForTimeout(300);
    const createStyles = await grabStyles();
    log('CREATE-VIEW', JSON.stringify(createStyles));
    await page.screenshot({ path: OUT + '/popup-create-1440.png' });

    // Back to login, mobile viewport
    await page.evaluate(() => typeof showLogin === 'function' && showLogin());
    await page.setViewportSize({ width: 375, height: 812 });
    await page.waitForTimeout(400);
    await page.screenshot({ path: OUT + '/popup-login-375.png' });

    // Assertions
    const ink = 'rgb(15, 23, 42)';
    log('ASSERT titleWrapBg transparent (login)', loginStyles.titleWrapBg === 'rgba(0, 0, 0, 0)' ? 'PASS' : 'FAIL');
    log('ASSERT h2Color ink (login)', loginStyles.h2Color === ink ? 'PASS' : 'FAIL');
    log('ASSERT h2Color ink (create)', createStyles.h2Color === ink ? 'PASS' : 'FAIL');
    log('ASSERT titleWrapBg transparent (create)', createStyles.titleWrapBg === 'rgba(0, 0, 0, 0)' ? 'PASS' : 'FAIL');
    log('ASSERT btnBg != transparent (button keeps config color)', loginStyles.btnBg !== 'rgba(0, 0, 0, 0)' && loginStyles.btnBg !== 'N/A' ? 'PASS (' + loginStyles.btnBg + ')' : 'FAIL');
    log('ASSERT btn != title bg (differentiation)', loginStyles.btnBg !== loginStyles.titleWrapBg ? 'PASS' : 'FAIL');

    fs.writeFileSync(OUT + '/results.txt', results.join('\n') + '\n');
    await browser.close();
    console.log('DONE');
})().catch(e => { console.error('PROBE-ERROR', e.message); process.exit(1); });
