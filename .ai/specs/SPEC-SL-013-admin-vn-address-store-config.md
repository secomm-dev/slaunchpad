# Feature Spec — Admin VN 2-level address dropdown trên Store Information + Shipping Origin config (SL-013)

Specification ID: SPEC-SL-013
Feature ID: NONE
Specification Level: FULL

<!-- Generated for Secomm Launchpad · Stack: Magento 2.4.8-p5 + Hyvä 3.x -->
<!-- Spec cho ticket SL-013 (merged: Store Information + Shipping Origin). Parent: FEAT-007. Mode B · Tier-1 (admin config; reversible). -->
<!-- Decisions: DEC-025 (global mechanism + country adapter; sub_city generic 3rd level) · DEC-020 (ward = city level, name-string persistence) · DEC-019 (generic Magento-default; VN → VietNamAddress) · DEC-17 (VN capability lives in VietNamAddress). -->
<!-- Note: hai surface này là `system.xml` config form (render bởi `Magento_Config`), KHÔNG phải `ui_component` (SL-011) hay block-form `Magento_Sales` (SL-012); cơ chế inject = `frontend_model` trên field `city` của core config. -->

> **Project**: Secomm Launchpad · **Stack**: Magento 2.4.8-p5 + Hyvä 3.x (Tailwind v4 + Magewire 1.13)
> **Risk tier**: Tier-1 — admin config surface (AGENTS.md §9; PDF/print origin + carrier/TableRate origin — reversible). Reassess → **A** nếu chạm config schema (không dự định).

## Feature Overview

**Feature name**: Apply VN 2-level address dropdown (region → city/ward) trên admin Store Information + Shipping Origin config form; persist ward vào `core_config_data`

**Ticket reference**: [SL-013](../tickets/SL-013-apply-address-dropdown-admin-store-information.md) · Parent [FEAT-007](../records/features/FEAT-007.md) · extends CMP-ADDR [FEAT-001](../records/features/FEAT-001.md)

**Feature type**: New admin config surface (apply mechanism đã pin ở DEC-025 lên config form)

**Priority**: P3 (Medium — một trong bốn surface admin của FEAT-007; Tier-1)

**Mode / Risk**: B · Tier-1 (admin config; không chạm customer/order/PII; reversible). Reassess → A nếu approach đòi đổi config schema (không trong scope).

## Scope note

FEAT-007 pin architecture cho mọi admin address surface. Spec (mini-spec — Mode B) này chỉ bao phủ **SL-013**: hai config surface admin — **Store Information** (`Stores → Configuration → General → Store Information`, path `general/store_information`) và **Shipping Origin** (`Stores → Configuration → Sales → Shipping Settings → Origin`, path `shipping/origin`). Các surface khác có spec riêng: customer address (SL-011), order create/edit (SL-012), MSI Source (SL-014).

---

## User Stories

- **US-001**: As a merchant admin, tôi muốn khi chọn VN trên Store Information / Shipping Origin, field City thành ward dropdown phụ thuộc Province (Tỉnh/Thành), để lưu ward chính xác thay vì gõ tay sai/thiếu ward → PDF/print origin và carrier/TableRate origin đúng.
- **US-002**: As a developer/maintainer, tôi muốn cơ chế inject dropdown vào config field `city` là generic, country-agnostic (`Secomm_AddressDropdown`) còn behaviour/label/validate VN cô lập trong `Secomm_VietNamAddress`, để tuân DEC-019/025 và reusable cho surface khác.
- **US-003**: As a TL/SRE, tôi muốn không phá Magento config render/cache, không phá region updater mặc định, và config field khác + PDF + TableRate hiện hành nguyên vẹn, để regression rủi ro thấp.

---

## Acceptance Criteria

> Mỗi AC = một test case. AC-1..AC-7 bám theo ticket SL-013.

- [ ] **AC-1 (Store Information — VN):** Trên form `Stores → Configuration → General → Store Information`, chọn country = VN → cascade region (Tỉnh/Thành) → `city` (Phường/Xã = ward) render: ward dropdown phụ thuộc region; ward options theo `region_id` (reuse `GetListCity(area=adminhtml)`); chọn ward → điền ward name vào value carrier `city` field. Store Information **không render `sub_city`** cho VN (2-level, DEC-025).
- [ ] **AC-2 (Shipping Origin — VN):** Trên form `Stores → Configuration → Sales → Shipping Settings → Origin` (path `shipping/origin`), tương tự AC-1.
- [ ] **AC-3 (Persist + pre-select — cả 2 path):** Save `region_id` + `city`(ward name) vào `core_config_data` cho cả `general/store_information` + `shipping/origin`; mở lại form → region + `city`(ward) **pre-select đúng** từ giá trị đã lưu; hydrate không reset ward.
- [ ] **AC-4 (Non-VN — no VN leak):** Non-VN country → Magento default (region updater mặc định + `city` text input; `sub_city` chỉ hiện nếu country có data 3 cấp — DEC-025); không có behaviour/label VN rò rỉ; không ép ward dropdown.
- [ ] **AC-5 (PDF/print origin):** PDF (invoice/shipment/creditmemo) + "Print Order" header hiển thị region + ward đúng lấy từ Store Information (`general/store_information`). Verify ward không bị mất/truncate.
- [ ] **AC-6 (TableRate/carrier origin):** Shipping Origin (`shipping/origin`) ward đúng được carrier/TableRate nhận làm origin. Verify TableRate rate (nếu condition dùng `city`) + carrier default origin dùng ward đúng.
- [ ] **AC-7 (Regression):** Các config field khác trong 2 form + các section khác, frontend, PDF hiện hành, TableRate rate hiện hành, và các admin surface khác nguyên vẹn; config save/cache không break.

---

## Technical Notes

### Cơ chế config form (khác SL-011/SL-012)

Cả 2 surface là **`system.xml` config form** render bởi `Magento_Config` (`Magento\Config\Block\System\Config\Form`), **không phải** `ui_component` (SL-011 customer modal) hay block-form `Magento_Sales` (SL-012 order). Cấu trúc field của cả 2 nhóm giống hệt (verified 2026-08-07):

| Field | `general/store_information` (Magento_Backend) | `shipping/origin` (Magento_Shipping) |
|---|---|---|
| `country_id` | `select`, source `Magento\Directory\Model\Config\Source\Country` | `select`, source Country, `frontend_class` `countries` |
| `region_id` | `text` (region updater render select theo country) | `text` (region updater) |
| `postcode` | `text` | `text` |
| `city` | `text` ← **target**: inject dependent ward dropdown | `text` ← **target** |
| `street_line1/2` | `text` | `text` |

→ **Approach injection** = override field `city` của core config qua `system.xml` của `Secomm_AddressDropdown` với `frontend_model` (Magento merge attribute `frontend_model` vào field core khi cùng path). `frontend_model` render: native `<input>` (giữ làm **value carrier** — mang đúng config field name `groups[...][fields][city][value]` để Magento persist) + `<select>` ward (`city_select`) + `<select>` `sub_city` generic + `data-mage-init` config wire vào cascade JS generic (mirror precedent SL-012 `Renderer/City.php` + `city.phtml`).

### Reuse generic mechanism (DEC-025)

- **Data layer (reuse, read-only):** GraphQL `GetListCity(input: {region_id, area: "adminhtml"})` + `GetListSubCity` ([`GetListCityGraphql.php`](../../app/code/Secomm/AddressDropdown/Model/Resolver/GetListCityGraphql.php)) + `CityLocaleCollection` / `CityCollection` / `CityRepository`. **Không duplicate master data.**
- **Cascade JS (generic, country-agnostic):** precedent [`address-city.js`](../../app/code/Secomm/AddressDropdown/view/adminhtml/web/js/order/address-city.js) (SL-012) dùng suffix-selector `[id$="_country_id"]` / `[id$="_region_id"]` / `[id$="_region"]` + hydration watcher (poll `country|region_id` ~5min cho Prototype `regionUpdater` async) + GraphQL `GetListCity(area=adminhtml)` + stale guard. **Field-id config form cũng kết thúc `_country_id`/`_region_id`/`_city`** → generic JS tương thích config form theo thiết kế → ưu tiên **reuse / factor** thay vì clone.
- **`sub_city` = generic 3rd level** (DEC-025, không deprecate): country non-VN có data 3 cấp → `sub_city` hiện (data-driven); VN 2 cấp → không render `sub_city` cho VN.

### VN adapter (DEC-019)

`Secomm_VietNamAddress` owns (cho config surface):
- Khi `country == 'VN'` → `city` = ward; label ward = "Phường/Xã" (i18n VietNamAddress, đã có từ SL-011); 2-level → không render `sub_city`.
- **Server-side validate** trên config save (path `general/store_information` + `shipping/origin`, `country==VN`): ward ∈ province qua `CityLocaleCollection` filter `(region_id, default_name)`; invalid → neutralise (clear `city` trong payload) + log warning masked; catch non-fatal, **không crash save**. Mirror [`Plugin/Cart/ValidateVietNamWard.php`](../../app/code/Secomm/VietNamAddress/Plugin/Cart/ValidateVietNamWard.php) + [`Plugin/Customer/Address/ValidateVietNamWard.php`](../../app/code/Secomm/VietNamAddress/Plugin/Customer/Address/ValidateVietNamWard.php) (SL-011).

> **Note (data-driven ownership):** như SL-011 deviation đã kết luận, generic mechanism có thể **tự** xử lý VN 2-level tự nhiên (GetListSubCity trả empty cho VN → `sub_city` tự hide). VN-owned logic thực sự cần = server-side validate. Label explicit VN ("Phường/Xã") = optional VN client overlay; decide trong plan.

### Persist

- `core_config_data.value` cho `{path}/city` = ward name string (VN — tech-debt DEC-020 name-string, path A = future); `region_id` đã persist native. **Không** schema migration, **không** cross-wire store.
- Value carrier `<input>` giữ đúng config field name → Magento config save đọc → persist tự nhiên; `<select>` ward cập nhật `input.val(ward)` on change (như order `address-city.js`).

### Display downstream

- **Store Information → PDF/print**: invoice/shipment/creditmemo/order print đọc `general/store_information/*` qua address formatter Magento → ward trong `city` được render native (verify không truncate). Không cần format change (verify AC-5).
- **Shipping Origin → carrier/TableRate**: carrier `collectRates` + TableRate đọc `shipping/origin/*` → ward trong `city` (verify condition-by-city nếu relevant — AC-6).

### Files / Areas Affected

- **NEW/extend** `Secomm_AddressDropdown/etc/adminhtml/system.xml` — add `frontend_model` cho `general/store_information/city` + `shipping/origin/city` (merge vào field core).
- **NEW** `Secomm_AddressDropdown/Block/Adminhtml/System/Config/Field/City.php` — `frontend_model` (extends `Magento\Config\Block\System\Config\Form\Field`): render native city input (value carrier) + `city_select` + `sub_city` + `data-mage-init` (mirror order Renderer/City.php).
- **NEW** `Secomm_AddressDropdown/view/adminhtml/templates/system/config/field/city.phtml` — markup inject (mirror order `city.phtml`).
- **NEW hoặc reuse** cascade JS generic — ưu tiên factor `address-city.js` (order) thành shared lib dùng chung order + config; nếu field-id convention lệch → thin config variant `web/js/config/address-city.js`.
- `Secomm_AddressDropdown/Model/Resolver/GetListCityGraphql.php` (+ `GetListSubCity`) + `CityLocaleCollection` — reuse read-only (KHÔNG sửa).
- **NEW** `Secomm_VietNamAddress/etc/adminhtml/events.xml` (hoặc `di.xml` plugin) — register config-save validate (scope 2 path + VN gate).
- **NEW** `Secomm_VietNamAddress/Observer/Adminhtml/Config/ValidateVietNamWard.php` (hoặc plugin `Magento\Config\Model\Config::save`) — validate ward ∈ province khi VN; neutralise + log + non-fatal (mirror SL-011 customer plugin).
- `Secomm_VietNamAddress/i18n/vi_VN.csv` + `en_US.csv` — "Phường/Xã" / "Ward/Commune" (đã có từ SL-011 — verify).
- **KHÔNG affect**: storefront `view/frontend/*`, customer/order address admin surface (SL-011/012), MSI (SL-014), `db_schema`, GraphQL resolvers (reuse read-only).

---

## Dependencies

| Dependency | Type | Status | Notes |
|------------|------|--------|-------|
| [DEC-025](../records/decisions/DEC-025.md) (global mechanism + adapter; sub_city generic) | decision | accepted | pin architecture cho SL-011..014 |
| [DEC-020](../records/decisions/DEC-020.md) (ward = city level; name-string persistence) | decision | accepted | ward persist name string = tech-debt hiện hành |
| [DEC-019](../records/decisions/DEC-019.md) + DEC-17 (generic vs VN boundary) | decision | accepted | generic config field override; VN validate/label ở VietNamAddress |
| [FEAT-007](../records/features/FEAT-007.md) | feature | proposed | Parent architecture cho SL-011..014 |
| [SL-011](../tickets/SL-011-apply-address-dropdown-admin-customer-form.md) | ticket | proposed/in-progress | Share `CityLocaleCollection` + validate pattern (`ValidateVietNamWard`) + i18n label |
| [SL-012](../tickets/SL-012-apply-address-dropdown-admin-order-form.md) | ticket | in-progress | Share generic cascade JS pattern (`address-city.js` suffix-selector) + Renderer/City.php precedent |
| [SL-014](../tickets/SL-014-apply-address-dropdown-admin-msi-source.md) | ticket | proposed | Share ward source/label logic |
| [FEAT-006](../records/features/FEAT-006.md) GHTK | feature | proposed | Shipping Origin = carrier origin/pickup parity (không block) |

---

## Risks & Unknowns

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Inject `frontend_model` vào **core config field** (`.../city`) phá render/cache config | M | M | Override qua merge `system.xml` (Magento-idiomatic, không override template nặng); giữ native `<input>` value carrier + config field name chuẩn; QC save + reload + cache clean |
| **Region updater mặc định** (Prototype `regionUpdater`) xung đột cascade JS | M | M | Reuse hydration watcher `(country\|region_id)` của `address-city.js` (SL-012 đã proven cho admin region async); QC country switch + region change |
| PDF origin address sai/truncate ward → tài liệu gửi khách sai | L | H | AC-5 QC PDF/print origin (invoice/shipment/creditmemo/order print) region + ward đầy đủ |
| Shipping Origin ward sai → TableRate rate sai (nếu condition dùng `city`) | M | M | AC-6 verify TableRate rate dùng ward đúng khi condition = city |
| Server-side validate hook sai config save path → không bắt ward sai / chặn save hợp lệ | M | M | Scope 2 path chính xác + gate VN + neutralise (không throw) + non-fatal catch; mirror SL-011 plugin; QC ward sai → neutralise + log, save ok |
| Factor/reuse generic JS giữa order + config → xung đột field-id/đouble-bind | L | M | Verify field-id suffix convention; nếu lệch → thin config variant riêng (không share state) |
| Tier-1 nhưng chạm PDF (gửi khách) + carrier origin — sai ảnh hưởng vận hành | L | M | TL review + QC end-to-end (config save → PDF → carrier) |

---

## Out of Scope

- Các admin surface khác: customer address (SL-011), order create/edit (SL-012), MSI Source (SL-014).
- Storefront generic cleanup / VN leak storefront (SL-007).
- ward_id / `city_id` canonical persistence (path A, DEC-020 long-term follow-up) — ward vẫn persist name string trong scope này.
- Config schema migration / field mới (chỉ override `frontend_model` trên field `city` đã có).
- GraphQL resolver changes (reuse `GetListCity`/`GetListSubCity` read-only).
- Payment/checkout/VNPAY/table-rate **logic** changes (chỉ verify origin ward downstream, không sửa carrier algorithm).

---

## Test Notes

- **Store Information (VN):** country=VN → cascade province→ward; ward options theo region; save → `core_config_data` `{path}/city` = ward; reload → pre-select đúng; không render `sub_city`.
- **Shipping Origin (VN):** tương tự (path `shipping/origin`).
- **Cả 2 surface (non-VN):** native `city` text; region updater mặc định; `sub_city` chỉ khi country có data 3 cấp; không leak VN.
- **Server-side validate:** ward không thuộc province (篡改 payload qua POST config save) → neutralise `city` + log warning masked; save không crash; ward hợp lệ → giữ; non-VN → skip; fault → log non-fatal.
- **PDF/print:** invoice/shipment/creditmemo/order print hiển thị region + ward từ Store Information đầy đủ.
- **TableRate/carrier:** origin ward đúng; verify rate khi condition = city.
- **Regression:** các config field khác trong 2 form + section khác; config save/cache; PDF hiện hành; TableRate hiện hành; storefront; admin surface khác (customer/order) nguyên vẹn.
- Evidence → `.ai/runtime/evidence/FEAT-007/` (SL-013). [ASSUMPTION: evidence path theo convention FEAT-007; confirm nếu project dùng path khác.]

---

## Open Questions

> Mode B → approach/scope unknowns chốt trong implementation plan (ticket DoD: "Mini-spec/approach note (Mode B) — Q1 chốt"). Level-2 (plan) → TL sign-off trước code.

- **Q1 (approach — chốt trong plan):** Override core config field `city` qua `system.xml` `frontend_model` (Magento-idiomatic, merge attribute) **hay** plugin vào `Magento\Config\Block\System\Config\Form` render? → **khuyến nghị frontend_model** (cleanest, low-risk, mirror SL-012 Renderer precedent). Chốt plan.
- **Q2 (ward source cho config — chốt trong plan):** Static `source_model` hay dynamic GraphQL? → Static `source_model` **không filter được theo region** (config source load 1 lần); → **khuyến nghị reuse GraphQL `GetListCity(area=adminhtml)` + generic cascade JS** (như SL-012); cho server validate reuse `CityLocaleCollection` read-only. Chốt plan.
- **Q3 (label ownership):** Label "Phường/Xã" cho VN = generic renders từ data (implicit) hay VN explicit client overlay (như SL-011 deviation để mở)? → decide plan (ưu tiên implicit data-driven nếu đủ; explicit VN overlay nếu TL muốn ownership rõ).
