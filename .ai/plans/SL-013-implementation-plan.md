# Kế hoạch triển khai: SL-013 — Apply VN 2-level address dropdown trên admin Store Information + Shipping Origin config

| Field | Value |
|---|---|
| Specification | specs/SPEC-SL-013-admin-vn-address-store-config.md |

> Mode B · **Chỉ plan, chưa viết code** (Hard Gate: No Code Without Plan; §7.1).
> Tier-1 (admin config surface; PDF/print origin + carrier/TableRate origin — reversible). Reassess → A nếu approach đòi đổi config schema (không dự định).
> Parent: [FEAT-007](../records/features/FEAT-007.md). Ticket: [SL-013](../tickets/SL-013-apply-address-dropdown-admin-store-information.md). Spec: [admin-vn-address-store-config](../specs/SPEC-SL-013-admin-vn-address-store-config.md).

## Metadata

| Field | Value |
|-------|-------|
| Ticket | [SL-013](../tickets/SL-013-apply-address-dropdown-admin-store-information.md) |
| Spec | [admin-vn-address-store-config](../specs/SPEC-SL-013-admin-vn-address-store-config.md) |
| Feature | [FEAT-007](../records/features/FEAT-007.md) |
| Author | AI draft |
| Reviewer (TL) | user — **pending plan approval** (Level-2) |
| Workflow Mode | B |
| Date | 2026-08-07 |
| Decisions | DEC-025 (accepted) · DEC-020 (accepted) · DEC-019 · DEC-17 |
| Validation level | **L3** (config save → downstream PDF/print origin + carrier/TableRate origin — verify system-wide) |

## 1. Hướng tiếp cận

**Tách generic mechanism (`Secomm_AddressDropdown`) vs country adapter (`Secomm_VietNamAddress`) — DEC-019/025.** Inject dependent ward dropdown vào field `city` của core config qua `frontend_model` (Magento-idiomatic), reuse generic data + cascade JS precedent của SL-012 (`address-city.js` suffix-selector — vốn đã tương thích config form), và validate VN trên config save mirroring SL-011 precedent.

- **Generic `Secomm_AddressDropdown`** owns: override field `general/store_information/city` + `shipping/origin/city` qua `system.xml` `frontend_model`; render native `<input>` (value carrier — mang config field name chuẩn) + `<select>` ward (`city_select`) + `<select>` `sub_city` + `data-mage-init` wire cascade JS; cascade country-agnostic data-driven (region → city → sub_city); reuse GraphQL `GetListCity(area=adminhtml)` + `GetListSubCity` + `CityLocaleCollection` (read-only). **KHÔNG** chứa `country == 'VN'`/label VN.
- **`Secomm_VietNamAddress`** owns: VN server-side validate trên config save (scope 2 path, `country==VN` → ward ∈ province, neutralise + log, non-fatal) — mirror [`Plugin/Customer/Address/ValidateVietNamWard`](../../app/code/Secomm/VietNamAddress/Plugin/Customer/Address/ValidateVietNamWard.php) (SL-011); label "Phường/Xã" reuse i18n đã có (SL-011). Non-VN → generic nguyên vẹn.
- **Persist**: `core_config_data.value` cho `{path}/city` = ward name string (tech-debt DEC-020); value carrier `<input>` giữ config field name → Magento persist native. Không schema migration, không cross-wire.

**Lý do chọn:** `frontend_model` override = Magento-idiomatic (merge attribute qua `system.xml`, không override template nặng) → ít rủi ro render/cache nhất so với plugin block form; reuse generic `address-city.js` (suffix-selector `[id$="_region_id"]` vốn khớp field-id config) → tránh clone/double-bind; validate mirror 2 precedent đã proven (cart SL-003 + customer SL-011).

### Open questions RESOLVED (trong plan này — Level-2, chờ TL duyệt)

- ~~**Q1 (approach injection):**~~ **RESOLVED → override field `city` qua `system.xml` `frontend_model`** trong `Secomm_AddressDropdown` (merge attribute vào field core cùng path `general/store_information/city` + `shipping/origin/city`). *Alternatives đã loại:* plugin vào `Magento\Config\Block\System\Config\Form` (hacky, fragile trên upgrade); override template config (blast radius lớn). `frontend_model` giữ native `<input>` value carrier + inject thêm select + `data-mage-init`.
- ~~**Q2 (ward source cho config):**~~ **RESOLVED → dynamic GraphQL `GetListCity(area=adminhtml)` + generic cascade JS, KHÔNG static source_model.** Static `source_model` (`getAllOptions()`) load 1 lần không filter theo region được → không phù hợp dependent dropdown. Server-side validate reuse `CityLocaleCollection` (filter `region_id` + `default_name`) read-only (như SL-011). *Phía config field vẫn có thể khai báo `source_model` placeholder nếu Magento require (field type select), nhưng options thật do JS nạp.*
- ~~**Q3 (label ownership):**~~ **RESOLVED → data-driven implicit** (generic renders label từ data; VN 2-level → `sub_city` tự hide vì GetListSubCity trả empty cho VN — như SL-011 deviation đã chốt). Nếu TL muốn explicit VN ownership client-side (vd. đổi label `city` → "Phường/Xã" chỉ khi VN), add thin VN mixin sau. Priority: server-side validate (VN-owned logic thực sự cần) + i18n label sẵn có.

## 2. Files affected

| File | Change type | Lý do / AC |
|------|-------------|-------|
| `Secomm/AddressDropdown/etc/adminhtml/system.xml` | extend | add `frontend_model` (block City) cho `general/store_information/city` + `shipping/origin/city` (merge attribute, không đổi type/label core) (AC-1/2) |
| **NEW** `Secomm/AddressDropdown/Block/Adminhtml/System/Config/Field/City.php` | new | `frontend_model` extends `Magento\Config\Block\System\Config\Form\Field`; `_getElementHtml()` render native `<input>` (value carrier, giữ config field name) + `city_select` + `sub_city` + `data-mage-init` (mirror order `Block/Adminhtml/Order/Address/Renderer/City.php` SL-012) (AC-1/2/4) |
| **NEW** `Secomm/AddressDropdown/view/adminhtml/templates/system/config/field/city.phtml` | new | markup inject (city input wrap + city select wrap + sub-city wrap — mirror order `templates/order/address/renderer/city.phtml`) (AC-1/2/4) |
| `Secomm/AddressDropdown/view/adminhtml/web/js/order/address-city.js` (hoặc **NEW** `web/js/config/address-city.js`) | factor/new | **Ưu tiên factor** generic `address-city.js` thành shared lib (order + config); nếu field-id convention/handle lệch → thin config variant (suffix-selector, GraphQL `GetListCity(area=adminhtml)`, hydration watcher `(country\|region_id)`, stale guard, toggle input/select) (AC-1/2/3/4) |
| `Secomm/AddressDropdown/view/adminhtml/requirejs-config.js` | verify/edit | đăng ký path/mixin nếu factor JS ra lib riêng (đảm bảo không vỡ order binding SL-012) |
| `Secomm/AddressDropdown/Model/Resolver/GetListCityGraphql.php` (+ `GetListSubCity`) + `CityLocaleCollection` | **KHÔNG sửa** (reuse read-only) | data layer generic — DEC-019 |
| **NEW** `Secomm/VietNamAddress/etc/adminhtml/events.xml` (hoặc extend `di.xml`) | new | register observer `model_config_data_save_before` (scope 2 path) HOẶC plugin `Magento\Config\Model\Config::save` cho validate VN (AC-3/validate) |
| **NEW** `Secomm/VietNamAddress/Observer/Adminhtml/Config/ValidateVietNamWard.php` (hoặc `Plugin/Adminhtml/Config/ValidateVietNamWard.php`) | new | gate `country==VN` + path ∈ {`general/store_information`, `shipping/origin`}; ward ∈ province qua `CityLocaleCollection` filter `(region_id, default_name)`; invalid → neutralise `city` + log warning masked; catch `\Exception` non-fatal (mirror SL-011 plugin) (AC-3/validate) |
| `Secomm/VietNamAddress/etc/module.xml` | verify | sequence load sau `Secomm_AddressDropdown` (để VN validate/label apply sau generic — đã có từ SL-011, verify) |
| `Secomm/VietNamAddress/i18n/vi_VN.csv` + `en_US.csv` | verify/edit | "Phường/Xã"/"Ward/Commune" (đã có từ SL-011 — verify; add nếu thiếu cho config context) |
| **KHÔNG sửa** storefront `view/frontend/*`, customer/order admin surface (SL-011/012), MSI (SL-014), `db_schema.xml`, GraphQL resolvers, carrier/TableRate algorithm | — | DEC-019; reuse read-only; downstream verify-only (AC-5/6/7) |

## 3. Steps (độc lập reviewable, theo thứ tự)

1. **Audit baseline + entry points** — risk: low — deps: none
   - Xác nhận 2 handle config: `Stores → Configuration → General → Store Information` (path `general/store_information`) + `Sales → Shipping Settings → Origin` (`shipping/origin`). Verify field-id pattern trên DOM (suffix `_country_id`/`_region_id`/`_city`) + config field name `groups[...][fields][city][value]`.
   - Verify cơ chế region updater trong config (Prototype `regionUpdater`? confirm async hydration path) → quyết định reuse watcher `address-city.js`.
   - Verify downstream: Store Information → PDF/print origin đọc qua formatter nào; Shipping Origin → carrier `collectRates`/TableRate đọc `city` thế nào.
   - Output: baseline note + selector map + field-id/name map. Không code change.
2. **Generic config frontend_model + phtml (AddressDropdown)** — risk: medium — deps: 1
   - `system.xml`: add `<frontend_model>Secomm\AddressDropdown\Block\Adminhtml\System\Config\Field\City</frontend_model>` cho 2 field `city` (giữ type/label/source core nguyên; verify merge attribute không conflict khi `setup:upgrade` + cache clean).
   - `City.php` block: `_getElementHtml()` render native `<input>` (value carrier — giữ `$element->getName()`/`$element->getHtmlId()` + value đã lưu) + `<select>` `city_select` + `<select>` `sub_city` + `data-mage-init` JSON (`cityInputId`, `citySelectId`, `subCitySelectId`, `currentCity`, `currentSubCity`). Mirror `Block/Adminhtml/Order/Address/Renderer/City.php` (SL-012).
   - `city.phtml`: markup 3 wrap (`.secomm-config-city-input/.select/.sub-city`) — mirror order `city.phtml` (SL-012).
   - verify: 2 form render input + select + sub_city; value carrier mang đúng config field name; save text bình thường (chưa cascade); không vỡ field khác.
3. **Cascade JS — factor generic hoặc config variant** — risk: medium — deps: 2
   - **Ưu tiên factor** `address-city.js` (order) thành shared generic lib (param config input/select id); đảm bảo order binding SL-012 không vỡ.
   - Nếu field-id/handle lệch → **NEW** `web/js/config/address-city.js` (suffix-selector `[id$="_country_id"]`/`[id$="_region_id"]`/`[id$="_region"]`, GraphQL `GetListCity(area=adminhtml)`, hydration watcher `(country|region_id)` cho region async, stale guard `_cityReqId`, toggle input/select, set `city` input = ward on change, `escapeGraphQlValue`). Mirror `address-city.js` (SL-012) sát nhất.
   - `requirejs-config.js` đăng ký nếu cần.
   - verify: VN → cascade province→ward, ward fill vào value carrier, `sub_city` hide (data 2-level); non-VN → native text + region updater + `sub_city` chỉ khi 3-level; reload pre-select ward đúng.
4. **Server-side validate (VietNamAddress) — config save** — risk: medium — deps: none (parallel-safe với 2/3)
   - Observer `model_config_data_save_before` (hoặc plugin `Magento\Config\Model\Config::save`): scope section/group ∈ {`general/store_information`, `shipping/origin`} + `country==VN`; ward ∈ province qua `CityLocaleCollectionFactory` filter `(region_id, default_name)`; invalid → unset `city` trong payload + log warning masked; catch `\Exception` → log error, không rethrow (non-fatal). Reuse logic `ValidateVietNamWard::beforeSave` (SL-011).
   - `etc/adminhtml/events.xml` (hoặc `di.xml`) register.
   - verify: ward sai province (篡改 POST) → neutralised + logged, save ok; ward hợp lệ → giữ; non-VN → skip; path khác → skip; fault → log.
5. **i18n + module sequence** — risk: low — deps: 3
   - Verify "Phường/Xã"/"Ward/Commune" trong `vi_VN.csv`/`en_US.csv` VietNamAddress (SL-011 đã add); verify `module.xml` sequence `VietNamAddress` after `AddressDropdown`.
   - verify: `setup:upgrade` + cache clean không lỗi; label render vi/en đúng.
6. **Downstream verify: PDF/print + TableRate/carrier** — risk: low — deps: 2,3
   - PDF/print origin (invoice/shipment/creditmemo/order print) hiển thị region + ward từ Store Information; không truncate.
   - Carrier/TableRate origin nhận ward từ Shipping Origin; verify rate khi condition = city.
   - Nếu downstream gap (ward bị drop/truncate) → patch đúng formatter/carrier-origin-read layer, không duplicate logic. (Verify-first; không sửa nếu đã đúng.)
7. **Tests + QC + evidence (L3)** — risk: low — deps: 2,3,4,5,6
   - Unit/integration: validate observer (valid/invalid/empty/non-VN/wrong-path/fault); JS gate logic (factor/variant). QC matrix (Store Info + Shipping Origin: VN 2-level cascade + save + pre-select, non-VN text + 3-level sub_city, validate neutralise, PDF/print origin, TableRate origin, regression config field khác + storefront + admin surface khác).
   - evidence → `.ai/runtime/evidence/FEAT-007/` (SL-013).

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Override `frontend_model` trên core config field → break render/cache config | high | merge attribute qua `system.xml` (không override template nặng); giữ native `<input>` value carrier + config field name chuẩn; QC cả 2 form + section khác sau cache clean |
| Region updater mặc định xung đột cascade JS → region/city lệch | medium | reuse hydration watcher `(country\|region_id)` của `address-city.js` (proven SL-012 cho admin region async); QC country switch + region change + reload |
| Factor `address-city.js` (order ↔ config share) → double-bind/vỡ order SL-012 | high | ưu tiên factor cẩn thận + verify order binding SL-012 nguyên vẹn; nếu rủi ro cao → thin config variant riêng (không share state) |
| PDF origin ward sai/truncate → tài liệu gửi khách sai | high | AC-5 QC PDF/print origin đầy đủ; patch đúng formatter nếu gap |
| Shipping Origin ward sai → TableRate rate sai | medium | AC-6 verify rate khi condition = city; không sửa carrier algorithm |
| Validate observer chặn save hợp lệ (false positive) / hook sai path | high | neutralise (clear city) không throw; chỉ gate khi ward ≠ ∅ + region + path đúng; log masked; QC ward hợp lệ giữ nguyên |
| Config value carrier mất config field name → ward không persist | high | giữ `$element->getName()` cho input; QC save → reload pre-select |

## 5. Test approach

- **Unit/Integration**: validate observer/plugin (valid ward kept / invalid ward neutralised + logged / empty skipped / non-VN skipped / wrong-path skipped / exception non-fatal logged); JS gate logic (factor/variant: VN → ward dropdown + hide sub_city; non-VN → native + sub_city khi 3-level).
- **QC (L3)**: Store Information VN → cascade province→ward, save `core_config_data {path}/city` = ward, reload pre-select đúng, no sub_city; Shipping Origin VN → tương tự; non-VN → native city text + region updater + sub_city khi 3-level; validate篡改 → neutralise + log + save ok; PDF/print origin region + ward đầy đủ; TableRate origin ward đúng.
- **Regression**: config field khác trong 2 form + section khác; config save/cache clean; PDF hiện hành; TableRate rate hiện hành; storefront; admin surface khác (customer SL-011, order SL-012) nguyên vẹn.
- **High-risk**: config value carrier field name (persist); PDF downstream (gửi khách); generic JS factor (order SL-012 không vỡ).

## 6. Out of scope

- Các admin surface khác: customer address (SL-011), order create/edit (SL-012), MSI Source (SL-014).
- Storefront generic/VN cleanup (SL-007) — coordinate, không overlap.
- ward_id / `city_id` canonical persistence (path A, DEC-020 long-term) — ward persist name string.
- Config schema migration / field mới; GraphQL resolver changes (reuse read-only).
- Carrier/TableRate **algorithm** changes (chỉ verify origin ward downstream); payment/checkout/VNPAY.

## 7. Open questions / Escalation

- Q1/Q2/Q3 **RESOLVED** trong §1 (`frontend_model` override / dynamic GraphQL + reuse JS / data-driven label implicit). Chờ TL duyệt approach.
- **Escalation (Level-2 — plan):** Tier-1 nhưng chạm PDF (gửi khách) + carrier/TableRate origin → **plan chờ TL approval trước code**. Validation **L3** (config save → downstream PDF + carrier system-wide).
- Coordinate với **SL-012** (generic `address-city.js` factor) — nếu SL-012 đang in-progress, chốt factor point chung để tránh conflict.

## 8. Status (post-implementation, pre-TL-review — 2026-08-07)

Plan approved (user as TL); implemented Steps 1–7. Evidence: [`.ai/runtime/evidence/FEAT-007/SL-013.md`](../runtime/evidence/FEAT-007/SL-013.md). **Pending QC L3 + TL code review.**

| Step | Status |
|------|--------|
| 1 Audit baseline + config save flow + entry points | **done** — confirmed system.xml field shape (2 surfaces), `Config::save()` reads `getGroups()` (line 180), `Config extends DataObject` (magic getGroups/setGroups/getSection) |
| 2 Generic config frontend_model + phtml | **done** — `Block/Adminhtml/System/Config/Field/City.php` + `templates/system/config/field/city.phtml` |
| 3 Dedicated config cascade JS | **done** — `web/js/config/address-city.js` (self-contained variant, see deviation below) |
| 4 VN server-side validate (config save) | **done** — `Plugin/Adminhtml/Config/ValidateVietNamWard.php` (`beforeSave` on `Config::save`), registered in `etc/adminhtml/di.xml` |
| 5 system.xml override + module sequence + i18n | **done** — `system.xml` (2 field frontend_model), `module.xml` (+Magento_Backend/Shipping), i18n verified |
| 6 Downstream verify (PDF/TableRate) | **static-verified** — ward persists to native config keys; standard consumers read them; no code change. Real render verify = QC |
| 7 Tests + pre-review + evidence | **done** — 7/7 unit tests pass; lint OK; `setup:upgrade` EXIT 0; merged-structure verified; pre-review §8.3 complete |

### Implementation deviation (2026-08-07)

**Step 3 — dedicated config JS instead of factoring SL-012's `address-city.js` (justified).**
The plan offered two options: factor the generic order `address-city.js` (SL-012) into a shared
lib, or a self-contained config variant (fallback). Audit found SL-012's order mechanism files
(`Renderer/City.php`, order `city.phtml`, `web/js/order/address-city.js`) are **not present** in
the working tree — SL-012 is in-progress and its JS state is volatile. To avoid coupling SL-013 to
that flux, the **self-contained config variant** (`web/js/config/address-city.js`) was chosen: it
replicates the proven generic cascade (suffix-selectors `[id$="_country_id"]`/`[id$="_region_id"]`,
GraphQL `GetListCity(area=adminhtml)`, hydration watcher `(country|region_id)`, stale guards,
toggle input/select) scoped to the config renderer instance, and adds GraphQL value escaping
(`escapeGraphQlValue`). No shared state with SL-012 → no regression risk to SL-012's binding.

### Open item for QC

- Confirm the **config region field region-updater** behaviour (hydration watcher covers async;
  if config region is a plain text field without updater for VN, verify province is still
  selectable — the cascade depends on a numeric `region_id`).

## 9. Review + Fix (2026-08-07 — QC báo "City chưa chuyển dropdown"; console lỗi JSON.parse)

Hai bug runtime phát hiện khi test admin config form:

**Bug 1 (critical — JS không init, `JSON.parse` throw):** template dùng attribute **double-quote**
`data-mage-init="..."` nhưng `json_encode(..., JSON_HEX_QUOT …)` **chỉ escape `"` bên trong value**,
không escape `"` cấu trúc JSON → `"` cấu trúc cắt đứt attribute → browser đọc attribute = `{` →
`mage/apply/main.js:66 JSON.parse("{")` throw "end of data at column 2". **Fix:** chuyển sang
**single-quote attribute** `data-mage-init='...'` (canonical Magento — `"` cấu trúc raw an toàn
trong single-quote); giữ `JSON_HEX_APOS` để phòng ward name chứa dấu `'`.

**Bug 2 (critical — cascade KHÔNG BAO GIỜ load ward dù JS chạy):** config form có region updater
riêng (`module-config/.../system/config/js.phtml`) thay region_id node qua
`parentNode.innerHTML = select.outerHTML` (line 251) khi chọn country → **destroy + recreate node**.
JS cũ cache `$regionId` jQuery ref → sau khi region updater chạy, ref trỏ **detached node** →
hydration watcher đọc `.val()` = cũ/rỗng → không detect province change → wards không load → city
không thành dropdown. (Region_id option `value` = số — province id, nên GraphQL sẽ chạy nếu đọc
đúng node.) **Fix:** (a) re-query `country`/`region_id` **fresh** mỗi lần đọc (không cache);
(b) bind change handler qua **event delegation trên form** (`$form.on('change', '[id$="_region_id"]', …)`)
chịu node replacement; watcher re-query fresh → detect province change đúng.

Verify post-fix: JS syntax OK (`node --check`) · phtml parse OK · `data-mage-init` render thành
single-quote JSON hợp lệ (json_decode round-trip ok) · cache cleaned (block_html/config/full_page).
**Chờ QC L3** retest: chọn VN → region updater render province dropdown → chọn province →
ward dropdown load + pre-select (edit) + non-VN fallback + validate neutralise.

