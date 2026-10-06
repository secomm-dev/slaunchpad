/*
 * TASK-M1J5AE probe — MSI source form: does picking a ward write the KO component value?
 * Runs headless chromium, logs console errors, uiRegistry component state, and save POST payloads.
 * Usage: NODE_PATH=/tmp/node_modules node probe-source-city.js
 */
const { chromium } = require('playwright');

const BASE = 'http://fashion-launchpad.localhost';
const ADMIN = '/admin_w1275xl';
const out = { console: [], pageErrors: [], requests: [], states: [] };

async function readCityState(page, label) {
    const state = await page.evaluate(() => new Promise((resolve) => {
        require(['uiRegistry'], function (reg) {
            const comp = reg.get('inventory_source_form.inventory_source_form.address.city');
            if (!comp) { resolve({ found: false }); return; }
            const level = comp.cityLevels && comp.cityLevels()[0];
            resolve({
                found: true,
                value: comp.value ? comp.value() : null,
                hasSchema: comp.hasSchema ? comp.hasSchema() : null,
                typeofOnLevelChange: typeof comp.onLevelChange,
                levelOptions: level && level.options ? level.options().length : null,
                levelSelected: level && level.selected ? String(level.selected()) : null,
                regionId: comp.regionId, countryId: comp.countryId
            });
        });
    }));
    out.states.push({ label, state });
    console.log('STATE[' + label + ']: ' + JSON.stringify(state));
}

(async () => {
    const browser = await chromium.launch({
        args: ['--host-resolver-rules=MAP fashion-launchpad.localhost 127.0.0.1', '--no-sandbox']
    });
    const page = await browser.newPage({ viewport: { width: 1600, height: 1000 } });

    page.on('console', (msg) => {
        if (msg.type() === 'error' || msg.type() === 'warning') {
            out.console.push(msg.type() + ': ' + msg.text().slice(0, 300));
        }
    });
    page.on('pageerror', (err) => out.pageErrors.push(String(err).slice(0, 300)));
    page.on('request', (req) => {
        if (req.method() === 'POST' && req.url().includes('source/save')) {
            out.requests.push({ url: req.url(), postData: req.postData() });
            console.log('SAVE POST captured');
        }
    });

    // login
    await page.goto(BASE + ADMIN + '/', { waitUntil: 'domcontentloaded' });
    console.log('LOGIN PAGE: ' + page.url() + ' | ' + await page.title());
    await page.fill('input[name="login[username]"]', 'claude_debug');
    await page.fill('input[name="login[password]"]', require('fs').readFileSync('/tmp/claude_debug_pass.txt', 'utf8').split('=')[1].trim());
    await page.click('button.action-login');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(3000);
    console.log('AFTER LOGIN: ' + page.url() + ' | ' + await page.title());

    // open source edit: extract keyed URLs from the menu/grid DOM (direct URLs need keys,
    // flyout submenu is unclickable headless)
    const modalText = await page.evaluate(() => {
        const m = document.querySelector('.modal-popup.modal-system-messages._show');
        const removed = [];
        if (m) { removed.push(m.textContent.trim().slice(0, 120)); m.remove(); }
        document.querySelectorAll('.modals-overlay').forEach((o) => o.remove());
        return removed.join(' | ');
    });
    if (modalText) { console.log('SYSTEM MODAL: ' + modalText); }
    const gridUrl = await page.evaluate(() => {
        const a = document.querySelector('#nav a[href*="inventory/source/index"]');
        return a ? a.href : null;
    });
    if (!gridUrl) { throw new Error('grid url not found in menu'); }
    await page.goto(gridUrl, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(3000);
    const editUrl = await page.evaluate(() => {
        const row = [...document.querySelectorAll('tbody tr')].find((t) => t.textContent.includes('Default Source'));
        const a = row ? row.querySelector('a[href*="source/edit"]') : null;
        return a ? a.href : null;
    });
    if (!editUrl) { throw new Error('edit url not found in grid'); }
    await page.goto(editUrl, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(4000);
    console.log('EDIT PAGE: ' + page.url() + ' | ' + await page.title());
    console.log('EDIT PAGE: ' + page.url() + ' | ' + await page.title());
    const formInfo = await page.evaluate(() => ({
        citySelectLevels: document.querySelectorAll('.secomm-source-city-level select').length,
        cityFieldNodes: document.querySelectorAll('[data-index="city"]').length,
        selects: document.querySelectorAll('select').length,
        bodyHasLogin: !!document.querySelector('input[name="login[username]"]')
    }));
    console.log('FORM INFO: ' + JSON.stringify(formInfo));
    await page.screenshot({ path: __dirname + '/probe-before-wait.png', fullPage: false });
    // address fieldset may be collapsed — open it (lazy render?)
    await page.evaluate(() => {
        const title = document.querySelector('[data-index="address"] .fieldset-wrapper-title');
        if (title) { title.click(); }
    });
    await page.waitForTimeout(2500);
    try {
        await page.waitForSelector('.secomm-source-city-level select', { timeout: 20000 });
    } catch (e) {
        console.log('CASCADE TIMEOUT — dumping diagnostics');
        console.log('CONSOLE: ' + JSON.stringify(out.console, null, 1).slice(0, 3000));
        console.log('PAGE ERRORS: ' + JSON.stringify(out.pageErrors, null, 1));
        const dump = await page.evaluate(() => new Promise((resolve) => {
            require(['uiRegistry'], function (reg) {
                const form = reg.get('inventory_source_form');
                const addr = reg.get('inventory_source_form.inventory_source_form.address');
                const city = reg.get('inventory_source_form.inventory_source_form.address.city');
                resolve({
                    formType: form ? typeof form : 'missing',
                    addrChildren: addr && addr.elems ? addr.elems().map((c) => c.name || c.index || '?') : (addr ? typeof addr : 'missing'),
                    cityFound: !!city,
                    cityValue: city && city.value ? city.value() : null,
                    selectCount: document.querySelectorAll('select').length,
                    addressSectionHtml: (document.querySelector('[data-index="address"]') || {}).innerHTML ? document.querySelector('[data-index="address"]').innerHTML.slice(0, 400) : 'NO ADDRESS NODE'
                });
            });
        }));
        console.log('REGISTRY DUMP: ' + JSON.stringify(dump, null, 1).slice(0, 2500));
        await page.screenshot({ path: __dirname + '/probe-timeout.png', fullPage: true });
        require('fs').writeFileSync(__dirname + '/probe-result.json', JSON.stringify(out, null, 2));
        await browser.close();
        process.exit(2);
    }
    await page.waitForTimeout(1500); // options load
    await readCityState(page, 'after-load');

    // TEST 1: save with empty city (no pick) — is it blocked client-side?
    const saveBtn = page.locator('button', { hasText: 'Save & Continue' }).first();
    if (!saveBtn) {
        const candidates = await page.$$eval('button', (bs) => bs.filter(b => /save/i.test(b.textContent)).map(b => b.textContent.trim()));
        console.log('SAVE BUTTON CANDIDATES: ' + JSON.stringify(candidates));
    }
    await saveBtn.click();
    await page.waitForTimeout(4000);
    await readCityState(page, 'after-empty-save');
    console.log('REQUESTS after empty save: ' + out.requests.length);

    // TEST 2: reload, pick a ward, read value immediately, then save
    await page.goto(editUrl, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    await page.evaluate(() => { const t = document.querySelector('[data-index="address"] .fieldset-wrapper-title'); if (t) t.click(); });
    await page.waitForTimeout(1500);
    await readCityState(page, 'reload');
    await page.selectOption('.secomm-source-city-level select', { index: 1 });
    await page.waitForTimeout(300);
    await readCityState(page, 'after-pick');
    const saveBtn2 = page.locator('button', { hasText: 'Save & Continue' }).first();
    await saveBtn2.click();
    await page.waitForTimeout(4000);
    await readCityState(page, 'after-ward-save');

    require('fs').writeFileSync(__dirname + '/probe-result.json', JSON.stringify(out, null, 2));
    console.log('DONE. requests=' + out.requests.length + ' consoleErrors=' + out.console.length + ' pageErrors=' + out.pageErrors.length);
    await browser.close();
})().catch((e) => {
    console.error('PROBE FAILED: ' + e.message);
    try { require('fs').writeFileSync(__dirname + '/probe-result.json', JSON.stringify(out, null, 2)); } catch (ignore) {}
    process.exit(1);
});
