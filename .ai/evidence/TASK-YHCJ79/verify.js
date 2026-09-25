/**
 * TASK-YHCJ79 — show/hide password toggle: Mageplaza popups (Hyvä) + OSC checkout (luma).
 * Usage: node verify.js [vi|en]
 * Needs Playwright at /tmp/pw-cal + Chrome 150 (see memory: local-playwright).
 */
const { chromium } = require('/tmp/pw-cal/node_modules/playwright');
const BASE = 'http://slaunchpad.localhost';
const OUT = __dirname;
const lang = process.argv[2] || 'vi';
const STORE_QS = lang === 'en' ? '?___store=launchpad_en' : '';
// expected labels follow the rendered locale (<html lang>): locally the en store
// view inherits vi_VN, so it renders the vi strings
const LABEL_SETS = {
    vi: { show: 'Hiện mật khẩu', hide: 'Ẩn mật khẩu' },
    en: { show: 'Show Password', hide: 'Hide Password' }
};
let LABELS = LABEL_SETS.vi;
const results = [];
const log = (name, pass, detail = '') => {
    const l = `${pass ? 'PASS' : 'FAIL'} | ${lang} | ${name}${detail ? ' | ' + detail : ''}`;
    results.push(l);
    console.log(l);
};

/** Geometry + state of one password input and its toggle (in page). */
const probe = id => {
    const input = document.getElementById(id);
    if (!input) return { exists: false };
    const button = document.querySelector(`[role="button"].lp-password-toggle[aria-controls="${id}"]`);
    const ir = input.getBoundingClientRect();
    const visible = ir.width > 0 && ir.height > 0 && input.checkVisibility({ visibilityProperty: true, opacityProperty: true });
    const out = { exists: true, visible, type: input.type, hasToggle: !!button };
    if (!button) return out;
    const br = button.getBoundingClientRect();
    const cs = getComputedStyle(input);
    Object.assign(out, {
        centerDelta: Math.abs((br.top + br.height / 2) - (ir.top + ir.height / 2)),
        heightDelta: Math.abs(br.height - ir.height),
        insideRight: br.right <= ir.right + 0.5 && br.left >= ir.left,
        paddingRight: parseFloat(cs.paddingRight),
        buttonWidth: br.width,
        ariaPressed: button.getAttribute('aria-pressed'),
        ariaLabel: button.getAttribute('aria-label'),
        ariaControls: button.getAttribute('aria-controls'),
        svg: !!button.querySelector('svg'),
        tag: button.tagName.toLowerCase(),
        bg: getComputedStyle(button).backgroundColor,
        borderW: getComputedStyle(button).borderLeftWidth,
        tabIndex: button.tabIndex,
        onTop: document.elementFromPoint(br.left + br.width / 2, br.top + br.height / 2) === button
            || button.contains(document.elementFromPoint(br.left + br.width / 2, br.top + br.height / 2))
    });
    return out;
};

/** Assert geometry, then click → shown, click → hidden. */
async function checkField(page, ctxName, id) {
    const pageLang = await page.evaluate(() => document.documentElement.lang.slice(0, 2));
    LABELS = LABEL_SETS[pageLang] || LABEL_SETS.en;
    const name = `${ctxName} #${id} [lang=${pageLang}]`;
    await page.evaluate(i => {
        const el = document.getElementById(i);
        if (el) el.scrollIntoView({ block: 'center' });
    }, id);
    await page.waitForTimeout(200);
    const before = await page.evaluate(probe, id);
    if (!before.exists || !before.hasToggle) {
        log(name, false, JSON.stringify(before));
        return;
    }
    if (!before.visible) {
        log(name + ' (attached, not visible)', true, `type=${before.type}`);
        return;
    }
    const geomOk = before.centerDelta <= 1 && before.heightDelta <= 1 && before.insideRight
        && before.paddingRight >= before.buttonWidth - 1 && before.svg && before.onTop
        && before.bg === 'rgba(0, 0, 0, 0)' && before.borderW === '0px' && before.tabIndex === 0;
    const hiddenOk = before.type === 'password' && before.ariaPressed === 'false'
        && before.ariaLabel === LABELS.show && before.ariaControls === id;
    await page.click(`[role="button"].lp-password-toggle[aria-controls="${id}"]`);
    const shown = await page.evaluate(probe, id);
    const shownOk = shown.type === 'text' && shown.ariaPressed === 'true' && shown.ariaLabel === LABELS.hide;
    await page.click(`[role="button"].lp-password-toggle[aria-controls="${id}"]`);
    const again = await page.evaluate(probe, id);
    // keyboard: focus the toggle, Enter shows, Space hides
    await page.focus(`[role="button"].lp-password-toggle[aria-controls="${id}"]`);
    await page.keyboard.press('Enter');
    const kbShown = (await page.evaluate(probe, id)).type;
    await page.keyboard.press(' ');
    const kbHidden = (await page.evaluate(probe, id)).type;
    const againOk = again.type === 'password' && again.ariaPressed === 'false' && kbShown === 'text' && kbHidden === 'password';
    log(name, geomOk && hiddenOk && shownOk && againOk,
        `onTop=${before.onTop} insideRight=${before.insideRight} centerΔ=${before.centerDelta.toFixed(1)} hΔ=${before.heightDelta.toFixed(1)} padR=${before.paddingRight} btnW=${before.buttonWidth} label="${before.ariaLabel}"→"${shown.ariaLabel}" type ${before.type}→${shown.type}→${again.type} kb=${kbShown}/${kbHidden} bg=${before.bg} border=${before.borderW}`);
}

async function newPage(browser, width, errors) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 } });
    const page = await ctx.newPage();
    page.on('console', m => m.type() === 'error' && errors.push(m.text()));
    page.on('pageerror', e => errors.push(String(e)));
    return page;
}

async function hyva(browser, width) {
    const errors = [];
    const page = await newPage(browser, width, errors);
    const tag = `hyva ${width}`;
    await page.goto(`${BASE}/${STORE_QS}`, { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.click('#customer-menu');
    await page.click('[id="customer.header.sign.in.link"]');
    await page.locator('#social_login_pass').waitFor({ state: 'visible', timeout: 10000 });
    await page.waitForTimeout(600);
    await checkField(page, `${tag} popup login`, 'social_login_pass');
    // validation message after an empty submit must not push the toggle off the input
    await page.fill('#social_login_email', 'slp.qa@example.com');
    await page.fill('#social_login_pass', '');
    await page.click('#bnt-social-login-authentication');
    await page.waitForTimeout(800);
    const v = await page.evaluate(probe, 'social_login_pass');
    const hasMsg = await page.evaluate(() => !!document.querySelector('#social-form-login .field.password .messages, #social-form-login .field-error'));
    log(`${tag} popup login validation`, hasMsg && v.centerDelta <= 1 && v.paddingRight >= v.buttonWidth - 1 && v.insideRight,
        `message=${hasMsg} centerΔ=${(v.centerDelta ?? NaN).toFixed(1)} padR=${v.paddingRight} insideRight=${v.insideRight}`);
    await page.screenshot({ path: `${OUT}/${lang}-${width}-hyva-login.png` });

    await page.evaluate(() => createBtn());
    await page.locator('#password-social').waitFor({ state: 'visible', timeout: 10000 });
    await page.waitForTimeout(400);
    await checkField(page, `${tag} popup create`, 'password-social');
    await checkField(page, `${tag} popup create`, 'password-confirmation-social');
    // independence: showing one leaves the other hidden
    await page.click('[role="button"].lp-password-toggle[aria-controls="password-social"]');
    const pair = await page.evaluate(() => [document.getElementById('password-social').type, document.getElementById('password-confirmation-social').type]);
    await page.click('[role="button"].lp-password-toggle[aria-controls="password-social"]');
    log(`${tag} popup create independent`, pair[0] === 'text' && pair[1] === 'password', pair.join('/'));
    await page.locator('#password-social').scrollIntoViewIfNeeded();
    await page.screenshot({ path: `${OUT}/${lang}-${width}-hyva-create.png` });

    // request-info form (only reachable through a social provider without email) — shown via DOM
    await page.evaluate(() => {
        document.querySelectorAll('#social-login-popup .social-login.block-container').forEach(e => { e.style.display = 'none'; });
        document.querySelector('.social-login.fake-email').style.display = 'block';
    });
    await page.waitForTimeout(400);
    await checkField(page, `${tag} popup request-info`, 'request-password-social');
    await checkField(page, `${tag} popup request-info`, 'request-password-confirmation');

    // Hyvä checkout authentication popup: content lives in <template x-if="open">
    await page.keyboard.press('Escape');
    await page.evaluate(() => { Alpine.$data(document.getElementById('authentication-popup')).open = true; });
    await page.locator('#form-login-password').waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(400);
    await checkField(page, `${tag} checkout-auth popup (x-if)`, 'form-login-password');
    await page.screenshot({ path: `${OUT}/${lang}-${width}-hyva-checkout-auth.png` });
    log(`${tag} console errors`, errors.length === 0, JSON.stringify(errors));
    await page.context().close();
}

async function osc(browser, width) {
    const errors = [];
    const page = await newPage(browser, width, errors);
    const tag = `osc ${width}`;
    await page.goto(`${BASE}/atlas-pouf.html${STORE_QS}`, { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    errors.length = 0; // PDP ExtraFee "Error fetching data" is pre-existing — count checkout only
    await page.goto(`${BASE}/onestepcheckout/`, { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForTimeout(8000);

    const attached = await page.evaluate(() => [...document.querySelectorAll('input[type=password], input.lp-password-toggle-input')]
        .map(i => `${i.id}:${!!document.querySelector(`[role="button"].lp-password-toggle[aria-controls="${i.id}"]`)}`));
    log(`${tag} all password inputs have a toggle`, attached.length >= 7 && attached.every(a => a.endsWith(':true')), attached.join(' '));

    // create account (billing) — the checkbox is visually replaced; click its label
    await page.locator('label[for="create-account-checkbox"]').first().click().catch(() => {});
    await page.locator('#osc-password').waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
    await checkField(page, `${tag} create-account`, 'osc-password');
    await checkField(page, `${tag} create-account`, 'osc-password-confirmation');
    // luma validation: invalid value → error after input, toggle stays on the input
    await page.fill('#osc-password', '1');
    await page.locator('#osc-password').blur();
    await page.waitForTimeout(800);
    const cv = await page.evaluate(probe, 'osc-password');
    log(`${tag} create-account validation`, cv.centerDelta <= 1 && cv.paddingRight >= cv.buttonWidth - 1 && cv.insideRight,
        `centerΔ=${(cv.centerDelta ?? NaN).toFixed(1)} padR=${cv.paddingRight} insideRight=${cv.insideRight}`);
    await page.locator('#osc-password').scrollIntoViewIfNeeded();
    await page.screenshot({ path: `${OUT}/${lang}-${width}-osc-create-account.png` });

    // #customer-password (OSC email step) never shows: OSC's email.js declares
    // isLoginVisible: false and nothing sets it — the toggle is only attached.
    await checkField(page, `${tag} email step`, 'customer-password');

    // social-login modal (luma) opened from the OSC login link
    await page.locator('.osc-authentication-wrapper a').first().click().catch(() => {});
    await page.locator('#social_login_pass').waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(800);
    await checkField(page, `${tag} social popup login`, 'social_login_pass');
    await page.screenshot({ path: `${OUT}/${lang}-${width}-osc-social-login.png` });
    await page.locator('#social-login-popup .action.create, .social-login .action.create').first().click().catch(() => {});
    await page.locator('#password-social').waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(600);
    await checkField(page, `${tag} social popup create`, 'password-social');
    await checkField(page, `${tag} social popup create`, 'password-confirmation-social');
    await page.screenshot({ path: `${OUT}/${lang}-${width}-osc-social-create.png` });
    await checkField(page, `${tag} OSC Sign In popup`, 'login-password');
    log(`${tag} console errors`, errors.length === 0, JSON.stringify(errors));
    await page.context().close();
}

(async () => {
    const browser = await chromium.launch({
        executablePath: '/home/secomm/.agent-browser/browsers/chrome-150.0.7871.24/chrome'
    });
    const run = async (name, fn) => {
        try {
            await fn();
        } catch (e) {
            log(name, false, String(e).split('\n')[0]);
        }
    };
    for (const w of [1280, 375]) {
        await run(`hyva ${w}`, () => hyva(browser, w));
        await run(`osc ${w}`, () => osc(browser, w));
    }
    await browser.close();
    const failed = results.filter(r => r.startsWith('FAIL')).length;
    console.log(`\n${results.length - failed}/${results.length} PASS`);
    process.exit(failed ? 1 : 0);
})();
