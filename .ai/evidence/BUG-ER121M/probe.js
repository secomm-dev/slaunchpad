/**
 * BUG-ER121M (SLP-199 follow-up) — alignment of the discount section while the
 * required-entry .mage-error is visible.
 * States probed per viewport: fresh (no error) -> submit empty (error) -> [after runs] applied/cancel.
 * Run: cd .ai/evidence/BUG-ER121M && NODE_PATH=/tmp/pw-cal/node_modules node probe.js
 * Env: SUFFIX=before|after3, WITH_COUPON=1 to add the applied/cancel regression states.
 */
const { chromium } = require('playwright');
const BASE = 'http://slaunchpad.localhost';
const SUFFIX = process.env.SUFFIX || 'before';
const WITH_COUPON = !!process.env.WITH_COUPON;

async function measure(page) {
    return page.evaluate(() => {
        const pick = (el, props) => {
            if (!el) return null;
            const cs = getComputedStyle(el);
            const r = el.getBoundingClientRect();
            const out = { rect: { top: +r.top.toFixed(1), bottom: +r.bottom.toFixed(1), h: +r.height.toFixed(1), w: +r.width.toFixed(1) } };
            for (const p of props) out[p] = cs[p];
            return out;
        };
        const section = document.querySelector('.opc-payment-additional.discount-code');
        const inner = section && section.querySelector('.payment-option-inner');
        const control = section && section.querySelector('.control.input-field');
        const input = section && section.querySelector('#discount-code');
        const toolbar = section && section.querySelector('.actions-toolbar');
        const btn = section && section.querySelector('.actions-toolbar .action');
        // NOTE: the input itself gets class .mage-error (mage/validation addClass),
        // so pick the generated message element (div), not the first .mage-error match.
        const err = section && (section.querySelector('.control div.mage-error') || section.querySelector('div.mage-error'));
        return {
            state: {
                errorVisible: !!err && err.offsetHeight > 0,
                errorText: err ? err.textContent.trim() : null,
                inputValue: input ? input.value : null,
                inputDisabled: input ? input.classList.contains('disabled') : null,
                btnClass: btn ? btn.className : null,
            },
            inner: pick(inner, ['alignItems', 'flexWrap']),
            control: pick(control, ['height']),
            input: pick(input, ['height']),
            toolbar: pick(toolbar, ['height']),
            btn: pick(btn, ['height']),
            error: err ? pick(err, ['marginTop', 'marginBottom', 'fontSize', 'lineHeight']) : null,
            misalign: (input && btn) ? {
                dTop: +(btn.getBoundingClientRect().top - input.getBoundingClientRect().top).toFixed(1),
                dBottom: +(btn.getBoundingClientRect().bottom - input.getBoundingClientRect().bottom).toFixed(1),
            } : null,
        };
    });
}

async function report(page, ctx, label) {
    const res = await measure(page);
    console.log('=== ' + label + ' ===');
    console.log(JSON.stringify(res, null, 1));
    const sec = page.locator('.opc-payment-additional.discount-code');
    await sec.scrollIntoViewIfNeeded().catch(() => {});
    await sec.screenshot({ path: `discount-${label}-${SUFFIX}.png` });
    return res;
}

async function safeClick(page, locator, tries = 3) {
    for (let i = 1; i <= tries; i++) {
        try {
            await locator.click({ timeout: 15000 });
            return;
        } catch (e) {
            if (i === tries) throw e;
            console.log(`click retry ${i}: ${e.message.split('\n')[0]}`);
            await page.waitForTimeout(3000);
        }
    }
}

async function submitEmpty(page) {
    // click Apply on an empty input -> mage/validation -> required-entry .mage-error
    await page.locator('#discount-code').fill('');
    const apply = page.locator('.opc-payment-additional.discount-code .action-apply');
    await apply.click();
    await page.waitForSelector('.opc-payment-additional.discount-code .mage-error', { timeout: 10000 })
        .catch(async () => {
            // fallback: submit via Enter
            await page.locator('#discount-code').press('Enter');
            await page.waitForSelector('.opc-payment-additional.discount-code .mage-error', { timeout: 10000 });
        });
    await page.waitForTimeout(800);
}

async function runViewport(browser, vp, tag) {
    const ctx = await browser.newContext({ viewport: vp });
    const page = await ctx.newPage();
    await page.goto(BASE + '/joust-duffle-bag.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.locator('#product-addtocart-button').first().click();
    await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    await page.goto(BASE + '/onestepcheckout/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForSelector('.opc-payment-additional.discount-code', { timeout: 30000 });
    await page.waitForTimeout(4000);

    await report(page, ctx, `${tag}-fresh`);

    await submitEmpty(page);
    await report(page, ctx, `${tag}-error`);
    // move the picked-element risk out of the picture: log ALL mage-error nodes
    await page.evaluate(() => {
        const nodes = [...document.querySelectorAll('.opc-payment-additional.discount-code .mage-error')];
        console.log('mage-error nodes:', JSON.stringify(nodes.map(n => ({
            tag: n.tagName, cls: n.className, gen: n.getAttribute('generated'),
            h: +n.getBoundingClientRect().height.toFixed(1),
            text: (n.tagName === 'INPUT' ? '(input class only)' : n.textContent.trim()),
        }))));
    });

    if (WITH_COUPON) {
        // applied state regression (BUG-2MK37V AC-002/003) then cancel
        await page.locator('#discount-code').fill('FREESHIP');
        await safeClick(page, page.locator('.opc-payment-additional.discount-code .action-apply'));
        await page.waitForSelector('.opc-payment-additional.discount-code .action-cancel', { timeout: 30000 });
        await page.waitForTimeout(2500);
        await report(page, ctx, `${tag}-applied`);
        await safeClick(page, page.locator('.opc-payment-additional.discount-code .action-cancel'));
        await page.waitForSelector('.opc-payment-additional.discount-code .action-apply', { timeout: 30000 });
        await page.waitForTimeout(2500);
        await report(page, ctx, `${tag}-canceled`);
        // required-error state again after cancel (main AC re-check)
        await submitEmpty(page);
        await report(page, ctx, `${tag}-error2`);
    }
    await ctx.close();
}

(async () => {
    // one viewport per process: the checkout page + this WSL box's low free RAM
    // (~1.2GB available) crashed a second sequential context — keep peak small.
    const VP = (process.env.VP || '1280') === '375' ? { width: 375, height: 812 } : { width: 1280, height: 900 };
    const browser = await chromium.launch({
        headless: true,
        args: ['--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1', '--disable-dev-shm-usage', '--disable-gpu'],
    });
    await runViewport(browser, VP, VP.width === 375 ? 'vi-375' : 'vi-1280');
    await browser.close();
})().catch(e => { console.error('FAIL', e.message); process.exit(1); });
