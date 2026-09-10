const { chromium } = require('playwright');

const BASE = 'http://slaunchpad.localhost';
const OUT = __dirname;
const results = [];
const report = (id, pass, detail) => {
    results.push({ id, pass, detail });
    console.log(`${pass ? 'PASS' : 'FAIL'} ${id} ${detail}`);
};

(async () => {
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1'],
    });
    const ctx = await browser.newContext({ viewport: { width: 375, height: 812 } });
    const consoleErrors = [];
    ctx.on('page', p => {
        p.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
        p.on('pageerror', e => consoleErrors.push('PAGEERROR: ' + e.message));
    });

    const login = async (page) => {
        await page.goto(BASE + '/customer/account/login/', { waitUntil: 'networkidle' });
        await page.fill('#email', 'qc-social@example.com');
        await page.fill('#pass', 'QcSocial123!');
        await page.click('button[name="send"]');
        await page.waitForURL(/customer\/account/);
    };

    const getButtons = (page) => page.evaluate(() => {
        const anchors = [...document.querySelectorAll('.block-dashboard-social-login a')];
        const ghosts = [...document.querySelectorAll('.block-dashboard-social-login button')];
        return {
            anchors: anchors.map(a => {
                const r = a.getBoundingClientRect();
                return {
                    text: a.textContent.trim(),
                    w: Math.round(r.width),
                    h: Math.round(r.height),
                    truncated: a.scrollWidth > a.clientWidth + 1,
                    fitsViewport: r.left >= 0 && r.right <= window.innerWidth + 0.5,
                    ghost: ghosts.includes(a),
                };
            }),
            ghostCount: ghosts.length,
        };
    });

    const page = await ctx.newPage({ viewport: { width: 375, height: 812 } });

    // ---- vi store, connected (google row re-inserted by setup) ----
    await login(page);
    await page.goto(BASE + '/customer/account/', { waitUntil: 'networkidle' });
    const block = page.locator('.block-dashboard-social-login');
    await block.scrollIntoViewIfNeeded();
    await page.waitForTimeout(400);

    let m = await getButtons(page);
    const googleV = m.anchors.find(a => /ngắt kết nối/i.test(a.text));
    report('AC-001a', !!googleV && m.anchors.length === 2 && m.ghostCount === 0,
        `buttons=${JSON.stringify(m.anchors.map(a => a.text))} ghost=${m.ghostCount}`);
    report('AC-001b', !!googleV && !googleV.truncated && googleV.fitsViewport,
        `google w=${googleV && googleV.w} truncated=${googleV && googleV.truncated} fits=${googleV && googleV.fitsViewport}`);
    report('AC-001c', m.anchors.every(a => a.h >= 40),
        `tap-heights=${JSON.stringify(m.anchors.map(a => a.h))}`);
    await block.screenshot({ path: OUT + '/verify-mobile-375-vi-connected.png' });

    // ---- AC-004: en store (connected row re-added by setup before run) ----
    await page.goto(BASE + '/customer/account/?___store=launchpad_en&___from_store=default', { waitUntil: 'networkidle' });
    const blockEn = page.locator('.block-dashboard-social-login');
    await blockEn.scrollIntoViewIfNeeded();
    await page.waitForTimeout(400);
    const mEn = await getButtons(page);
    const titleEn = (await blockEn.locator('.block-title').textContent()).trim();
    const labelEn = mEn.anchors.map(a => a.text).join(' | ');
    // en store shows google connected again (setup re-run) -> "Disconnect" identity
    report('AC-004a', /Activate Social Login Connect/.test(titleEn) && /Disconnect/.test(labelEn) && !/Ngắt/.test(labelEn),
        `title="${titleEn}" labels="${labelEn}"`);
    report('AC-004b', mEn.anchors.every(a => !a.truncated && a.fitsViewport),
        `en-truncated=${JSON.stringify(mEn.anchors.map(a => a.truncated))}`);
    await blockEn.screenshot({ path: OUT + '/verify-desktop-en-connected.png' });

    // ---- AC-003: connected -> dialog -> cancel -> confirm -> disconnected ----
    await page.goto(BASE + '/customer/account/?___store=default&___from_store=launchpad_en', { waitUntil: 'networkidle' });
    await page.locator('.block-dashboard-social-login a', { hasText: /ngắt kết nối/i }).first().click();
    const dialogText = page.locator('text=Bạn có chắc chắn muốn hủy kết nối');
    await dialogText.first().waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
    const dialogShown = await dialogText.first().isVisible().catch(() => false);
    report('AC-003a', dialogShown, `confirm-dialog-visible=${dialogShown}`);
    await page.waitForTimeout(600); // let the modal transition settle before the screenshot
    await page.screenshot({ path: OUT + '/verify-mobile-375-vi-dialog.png', clip: { x: 0, y: 0, width: 375, height: 812 } });

    if (dialogShown) {
        await page.locator('button', { hasText: 'Hủy' }).first().click();
        await page.waitForTimeout(400);
        let still = await getButtons(page);
        const stillConnected = still.anchors.some(a => /ngắt kết nối/i.test(a.text));
        report('AC-003b', stillConnected, 'cancel-keeps-connection');

        await page.locator('.block-dashboard-social-login a', { hasText: /ngắt kết nối/i }).first().click();
        await dialogText.first().waitFor({ state: 'visible', timeout: 5000 });
        await page.locator('button', { hasText: 'Xác nhận' }).first().click();
        let after = null;
        for (let i = 0; i < 30; i++) {
            await page.waitForLoadState('networkidle').catch(() => {});
            await page.waitForTimeout(500);
            after = await getButtons(page).catch(() => null);
            if (after && after.anchors.some(a => /^Google$/i.test(a.text))) break;
        }
        const labelsAfter = after ? after.anchors.map(a => a.text) : ['evaluate-failed'];
        report('AC-003c', !!after && after.anchors.some(a => /^Google$/i.test(a.text)),
            `after-confirm=${JSON.stringify(labelsAfter)}`);
    }

    // ---- unconnected -> popup OAuth ----
    let popupUrl = null;
    ctx.once('page', p => { popupUrl = p.url(); });
    await page.getByRole('link', { name: /^Google$/i }).first().click();
    const popup = await ctx.waitForEvent('page', { timeout: 8000 }).catch(() => null);
    popupUrl = popup ? popup.url() : popupUrl;
    report('AC-003d', !!popup && /(sociallogin\/google\/login|accounts\.google\.com)/.test(popupUrl),
        `popup=${popupUrl ? popupUrl.slice(0, 120) : 'none'}`);
    if (popup) await popup.close();

    // ---- AC-002: desktop 1280 vi (separate context for the 1280 viewport) ----
    const page2 = await browser.newContext({ viewport: { width: 1280, height: 800 } }).then(c => c.newPage());
    await login(page2);
    await page2.goto(BASE + '/customer/account/', { waitUntil: 'networkidle' });
    const block2 = page2.locator('.block-dashboard-social-login');
    await block2.scrollIntoViewIfNeeded();
    await page2.waitForTimeout(400);
    const mD = await getButtons(page2);
    report('AC-002a', mD.anchors.length === 2 && mD.anchors.every(a => !a.truncated && a.fitsViewport && !a.ghost),
        `desktop=${JSON.stringify(mD.anchors.map(a => ({ t: a.text, w: a.w, tr: a.truncated })))}`);
    await block2.screenshot({ path: OUT + '/verify-desktop-1280-vi.png' });

    report('CONSOLE', consoleErrors.length === 0,
        consoleErrors.length ? JSON.stringify(consoleErrors.slice(0, 3)) : 'clean');

    await browser.close();
    const failed = results.filter(r => !r.pass);
    console.log(`\nSUMMARY: ${results.length - failed.length}/${results.length} PASS`);
    process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('FATAL', e.message); process.exit(2); });
