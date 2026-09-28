/* TASK-WY6WP5 — directive §32 browser/admin smoke. Run: node /tmp/wy6wp5_smoke.js */
const { chromium } = require('/tmp/node_modules/playwright');
const fs = require('fs');

const BASE = 'http://fashion-launchpad.localhost';
const ADMIN = BASE + '/admin_w1275xl';
const PW = fs.readFileSync('/tmp/wy6wp5_smoke_pw', 'utf8').trim();
const SHOT = '.ai/evidence/TASK-WY6WP5/';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1600, height: 1000 } });
  const results = [];
  const ok = (name, cond) => { results.push(`${cond ? 'PASS' : 'FAIL'} — ${name}`); if (!cond) process.exitCode = 1; };

  // 1. Login
  await page.goto(ADMIN + '/admin/auth/login/', { waitUntil: 'networkidle' });
  await page.fill('#username', 'smokewy6wp5');
  await page.fill('#login', PW);
  await page.click('.action-login');
  await page.waitForLoadState('networkidle');
  ok('admin login succeeded', !page.url().includes('auth/login'));
  if (page.url().includes('auth/login')) {
    await page.screenshot({ path: SHOT + 'smoke-login-blocked.png', fullPage: true });
    console.log(results.join('\n'));
    await browser.close();
    return;
  }

  // Admin routes require the URL secret key — collect keyed hrefs from the rendered menu.
  const keyedUrl = async (match) => {
    await page.goto(ADMIN + '/admin/dashboard/', { waitUntil: 'networkidle' });
    const href = await page.$eval(`a[href*="${match}"]`, el => el.getAttribute('href')).catch(() => null);
    return href ? new URL(href, BASE).href : null;
  };

  // 2. Shipping Coverage grid (menu group + Add Coverage + registered row)
  const coverageIndexUrl = await keyedUrl('secomm_shippingcore/coverage/index');
  ok('keyed menu URL found for Shipping Coverage', !!coverageIndexUrl);
  await page.goto(coverageIndexUrl || ADMIN, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  ok('menu shows "Shipping" group', (await page.content()).includes('Shipping Zones'));
  ok('menu shows "Shipping Coverage" item', (await page.content()).includes('Shipping Coverage'));
  const addBtn = await page.$('.page-actions button[title="Add Coverage"], .page-actions button:has-text("Add Coverage")');
  ok('Add Coverage button visible', !!addBtn);
  await page.screenshot({ path: SHOT + 'smoke-2-coverage-grid.png', fullPage: true });
  const gridText = await page.content();
  ok('GHN registered target row visible', gridText.includes('GHN (Giao Hàng Nhanh)'));
  ok('Type column shows Carrier', gridText.includes('Carrier'));
  ok('Not Configured status shown', gridText.includes('Not Configured'));

  // 3. Add Coverage — carrier selector visible (ui-select)
  await page.click('.page-actions button:has-text("Add Coverage")').catch(() => {});
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  ok('Applies To = Carrier shown', (await page.content()).includes('Applies To'));
  const carrierSelect = await page.$('.admin__field[data-index="target_code"] .admin__action-multiselect');
  ok('Carrier ui-select (searchable) rendered', !!carrierSelect);
  const availSel = await page.$('.admin__field[data-index="destination_scope"] select');
  ok('Availability select rendered', !!availSel);

  // pick GHN from the carrier ui-select (required before save)
  await carrierSelect.click();
  await page.waitForTimeout(500);
  const carrierOption = await page.$('.admin__field[data-index="target_code"] li:has-text("GHN")');
  ok('carrier option GHN offered', !!carrierOption);
  if (carrierOption) {
    await page.evaluate(() => {
      const li = [...document.querySelectorAll('.admin__field[data-index="target_code"] li')].find(l => l.textContent.includes('GHN'));
      (li.querySelector('.action-menu-item') || li).click();
    });
    await page.waitForTimeout(400);
  }

  // 4. Availability = Selected Zones → zone multiselect visible, pick HCM_INNER
  await page.selectOption('.admin__field[data-index="destination_scope"] select', 'SELECTED_ZONES');
  await page.waitForTimeout(700);
  const zoneVisible = await page.$('.admin__field[data-index="allowed_zone_codes"]');
  const zoneHidden = await zoneVisible.isVisible();
  ok('Zone selector visible under SELECTED_ZONES', !!zoneVisible && zoneHidden);
  await page.screenshot({ path: SHOT + 'smoke-4-add-coverage-zones.png', fullPage: true });
  // open ui-select dropdown + pick HCM_INNER
  await page.click('.admin__field[data-index="allowed_zone_codes"] .admin__action-multiselect');
  await page.waitForTimeout(500);
  const zoneOption = await page.$('.admin__field[data-index="allowed_zone_codes"] li:has-text("HCM_INNER"), .admin__field[data-index="allowed_zone_codes"] li:has-text("Nội Thành")');
  ok('zone options load (HCM_INNER/Nội Thành)', !!zoneOption);
  if (zoneOption) {
    await page.evaluate(() => {
      const lis = [...document.querySelectorAll('.admin__field[data-index="allowed_zone_codes"] li')];
      const li = lis.find(l => l.textContent.includes('HCM_INNER')) || lis.find(l => l.textContent.includes('Nội Thành'));
      (li.querySelector('.action-menu-item') || li).click();
    });
    await page.waitForTimeout(400);
  }
  // close dropdown
  await page.keyboard.press('Escape');
  // Availability = All Vietnam → zones hidden (switcher)
  await page.selectOption('.admin__field[data-index="destination_scope"] select', 'ALL');
  await page.waitForTimeout(700);
  ok('Zone selector hidden under ALL (switcherConfig)', !(await page.isVisible('.admin__field[data-index="allowed_zone_codes"]')));
  await page.screenshot({ path: SHOT + 'smoke-4b-all-hides-zones.png', fullPage: true });

  // Save SELECTED_ZONES + HCM_INNER
  await page.selectOption('.admin__field[data-index="destination_scope"] select', 'SELECTED_ZONES');
  await page.waitForTimeout(500);
  await page.click('.admin__field[data-index="allowed_zone_codes"] .admin__action-multiselect');
  await page.waitForTimeout(400);
  // The selection persists through the ALL↔SELECTED_ZONES switcher cycle (no re-click:
  // a second toggle would DESELECT). Verify the item is still marked selected.
  const stillSelected = await page.evaluate(() => {
    const lis = [...document.querySelectorAll('.admin__field[data-index="allowed_zone_codes"] li .action-menu-item')];
    const li = lis.find(l => l.textContent.includes('HCM_INNER')) || lis.find(l => l.textContent.includes('Nội Thành'));
    return !!li && li.classList.contains('_selected');
  });
  ok('zone selection persists through ALL↔SELECTED switch', stillSelected);
  await page.keyboard.press('Escape');
  await page.evaluate(() => { const b = [...document.querySelectorAll('.page-actions button')].find(x => x.textContent.includes('Save')); b && b.click(); });
  await page.waitForLoadState('networkidle');
  await page.waitForURL('**coverage/index**', { timeout: 30000 }).catch(() => {});
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  const afterSave = await page.content();
  const msgText = await page.evaluate(() => (document.querySelector('#messages, .message.message-success, .message.message-error') || {}).textContent || '');
  console.log('(save message: ' + msgText.trim().slice(0, 140) + ')');
  ok('Save succeeded message shown', msgText.includes('has been saved'));
  await page.screenshot({ path: SHOT + 'smoke-5-after-save.png', fullPage: true });

  // 5. Edit — carrier readonly + value retained (click the grid row Edit action)
  const editLink = await page.$('a:has-text("Edit")[href*="coverage"], a[href*="target_code=secomm_ghn"]');
  if (editLink) { await editLink.click(); await page.waitForLoadState('networkidle'); }
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  const editText = await page.content();
  ok('Edit page shows GHN label', editText.includes('GHN (Giao Hàng Nhanh)'));
  await page.waitForTimeout(1000);
  // Authoritative: read the live ko component value via uiRegistry.
  const zoneValue = await page.evaluate(() => {
    const r = window.require('uiRegistry');
    const f = r.get('index = allowed_zone_codes');
    return f && f.value ? JSON.stringify(f.value()) : 'component-not-found';
  });
  ok('persisted zone retained on edit (field value has HCM_INNER)', zoneValue.includes('HCM_INNER'));
  // Reset button visible on configured target
  const resetBtn = await page.$('.page-actions button:has-text("Reset to Defaults")');
  ok('Reset to Defaults button visible on configured target', !!resetBtn);

  // 6. Reset to Defaults (confirm dialog)
  if (resetBtn) {
    await resetBtn.click();
    await page.waitForTimeout(800);
    // deleteConfirm renders a jQuery modal — click its Confirm action.
    await page.evaluate(() => {
      const btn = document.querySelector('.modal-popup .action-primary.action-accept, .modal-slide .action-primary.action-accept');
      if (btn) btn.click();
    });
    await page.waitForLoadState('networkidle');
    await page.waitForURL('**coverage/index**', { timeout: 30000 }).catch(() => {});
    await page.waitForTimeout(1500);
    const msgText2 = await page.evaluate(() => (document.querySelector('#messages') || {}).textContent || '');
    console.log('(reset message: ' + msgText2.trim().slice(0, 160) + ')');
    ok('Reset success message', msgText2.includes('reset to defaults'));
    ok('grid back to Not Configured after reset', (await page.evaluate(() => document.body.innerHTML.includes('Not Configured'))));
    await page.screenshot({ path: SHOT + 'smoke-6-after-reset.png', fullPage: true });
  }

  // 7. Zone form — province/ward selectors render, no Carriers Referencing block
  const zoneIndexUrl = await keyedUrl('secomm_shippingcore/zone/index');
  await page.goto(zoneIndexUrl || ADMIN, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  await page.keyboard.press('Escape'); // dismiss any open admin-menu overlay
  await page.waitForTimeout(400);
  await page.click('.page-actions button:has-text("Add New Zone")');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  console.log('(zone form url: ' + page.url().slice(-60) + ')');
  const zoneFormText = await page.content();
  ok('Zone form: NO "Carriers Referencing This Zone" block', !zoneFormText.includes('Carriers Referencing This Zone'));
  const provSelect = await page.$('.admin__field[data-index="include_province_codes"] .admin__action-multiselect');
  ok('Province searchable multiselect rendered', !!provSelect);
  const wardField = await page.$('.admin__field[data-index="include_ward_codes"]');
  ok('Included Wards field rendered', !!wardField);
  // province search interaction
  if (provSelect) {
    await provSelect.click();
    await page.waitForTimeout(400);
    await page.fill('.admin__field[data-index="include_province_codes"] input[data-bind*="filterInputValue"]', 'Hồ');
    await page.waitForTimeout(900);
    const provOptions = await page.$$('.admin__field[data-index="include_province_codes"] li:visible');
    ok('province search filters options (type-to-search works)', provOptions.length > 0 && provOptions.length < 40);
    await page.evaluate(() => {
      const lis = [...document.querySelectorAll('.admin__field[data-index="include_province_codes"] li')];
      const li = lis.find(l => l.textContent.includes('VN-15'));
      if (li) (li.querySelector('.action-menu-item') || li).click();
    });
    await page.waitForTimeout(600);
    // close the province dropdown so it cannot intercept the ward dropdown clicks
    await page.keyboard.press('Escape');
    await page.mouse.click(1200, 300);
    await page.waitForTimeout(600);
    await page.screenshot({ path: SHOT + 'smoke-7-zone-province-search.png', fullPage: true });
    // ward cascade: select HCM province → ward options load via AJAX
    const wardSel = await page.$('.admin__field[data-index="include_ward_codes"] .admin__action-multiselect');
    if (wardSel) {
      await wardSel.click();
      await page.waitForTimeout(1500);
      const wardOptions = await page.$$('.admin__field[data-index="include_ward_codes"] li:visible');
      ok('ward options cascade-load after province selection', wardOptions.length > 0);
      await page.screenshot({ path: SHOT + 'smoke-7b-zone-ward-cascade.png', fullPage: true });
      await page.keyboard.press('Escape');
    }
  }

  console.log(results.join('\n'));
  fs.writeFileSync(SHOT + 'smoke-results.txt', results.join('\n') + '\n');
  await browser.close();
})().catch(e => { console.error('SMOKE CRASH: ' + e.message); process.exit(1); });
