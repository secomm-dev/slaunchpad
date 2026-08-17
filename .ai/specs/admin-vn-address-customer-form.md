# Feature Spec — Admin VN 2-level address dropdown trên customer-address form (SL-011)

<!-- Generated for Secomm Launchpad · Stack: Magento 2.4.8-p5 + Hyvä 3.x -->
<!-- Spec cho ticket SL-011 (verify + migrate). Parent: FEAT-007. Mode A · Tier-2 (customer/PII). -->
<!-- Decisions: DEC-025 (accepted — global mechanism + country adapter; sub_city generic 3rd level) · DEC-020 (ward = city level, name-string persistence tech-debt) · DEC-019 (generic Magento-default; VN → VietNamAddress) · DEC-17 (VN capability lives in VietNamAddress). -->
<!-- Audit 2026-08-03: customer_address_form.xml + provider-mixin.js hiện nằm trong GENERIC Secomm_AddressDropdown → vi phạm DEC-019. Cascade không country-gate (implicit-VN qua data presence); selector chỉ trỏ Edit modal → Add-new gap; sub_city force-required; ward persist name string; chưa có server-side validate trên customer address save. -->

> **Project**: Secomm Launchpad · **Stack**: Magento 2.4.8-p5 + Hyvä 3.x (Tailwind v4 + Magewire 1.13)
> **Risk tier**: Tier-2 — customer/PII (AGENTS.md §9 L398, §12 AddressDropdown L421) → Mode A, escalate TL.

## Feature Overview

**Feature name**: Migrate admin customer-address VN cascade → `Secomm_VietNamAddress` + generalize generic `Secomm_AddressDropdown` mechanism (DEC-019/025 compliance)

**Ticket reference**: [SL-011](../tickets/SL-011-apply-address-dropdown-admin-customer-form.md) · Parent [FEAT-007](../records/features/FEAT-007.md) · extends CMP-ADDR [FEAT-001](../records/features/FEAT-001.md)

**Feature type**: Refactor + Enhancement (verify hiện trạng + migrate VN logic ra module riêng)

**Priority**: P2 (Medium — một trong bốn surface admin của FEAT-007)

**Mode / Risk**: A · Tier-2 (chạm customer/PII; migrate logic ra module generic→adapter)

## Scope note

FEAT-007 (full feature spec) đã tồn tại và pin architecture (DEC-025). Spec này là **mini-spec cho surface SL-011** duy nhất — admin **customer-address** form (modal Add + Edit). Các surface khác (order SL-012, store-info SL-013, MSI SL-014) có mini-spec riêng. Storefront generic cleanup = SL-007 (cùng boundary, coordinate, không overlap).

---

## User Stories

- **US-001**: As a merchant admin, I want VN customer address (Add + Edit) hiển thị cascade province → ward dropdown với ward đúng theo province, persist ward vào `customer_address_entity.city`, để nhập chính xác thay vì gõ tay sai/thiếu ward.
- **US-002**: As a developer/maintainer, I want VN behaviour (ward-as-city, 2-level, label "Phường/Xã", hide `sub_city`, server-side validate) cô lập trong `Secomm_VietNamAddress` và generic cascade trong `Secomm_AddressDropdown` country-agnostic, để module reusable đúng DEC-019/025 (không leak VN vào generic như hiện tại).
- **US-003**: As a TL/SRE, I want migration preserve behavior chính xác + không break storefront (customer form Luma+Hyva, cart estimate, OSC) + giữ `sub_city` generic cho country non-VN 3-level, để regression rủi ro thấp.

---

## Acceptance Criteria

> Mỗi AC = một test case. AC-1 là audit baseline; AC-2..AC-9 là deliverable. Chi tiết lần theo ticket SL-011 AC-1..AC-9.

- [ ] **AC-1 (Audit baseline):** Audit hiện trạng admin customer-address cascade — ghi nhận: (a) cascade + `city_select`/`sub_city` fields + `provider-mixin.js` đang nằm trong **generic** `Secomm_AddressDropdown` (vi phạm DEC-019); (b) cascade **không country-gate** — implicit-VN qua data presence (`GetListCity`/`GetListSubCity` trả empty cho non-VN → fallback native `city` text input); (c) selector `ADDRESS_EDIT_FORM` chỉ trỏ **Edit** modal (`..._customer_address_update_modal`) → **form Add-new chưa cover**; (d) `sub_city` force-required bất kể country; (e) ward persist dạng **name string** vào native `city` (tech-debt DEC-020, ward_id path A = future); (f) **chưa có** server-side validate ward ∈ province trên customer address save.
- [ ] **AC-2 (Add new — VN):** Add new customer address, chọn country=VN → cascade country→region(province)→`city`(ward) render đúng; ward options theo `region_id` (reuse `GetListCity`); chọn ward → persist vào `customer_address_entity.city`; VN **không render `sub_city`** (2-level, DEC-025).
- [ ] **AC-3 (Edit existing — VN):** Edit existing customer address VN → region + `city`(ward) **pre-select đúng** giá trị đã lưu; cascade re-hydrate không reset ward.
- [ ] **AC-4 (Migrate VN → VietNamAddress — DEC-019/025):** `Secomm_AddressDropdown` **không còn** `country == 'VN'`/ward-specific behaviour/label VN trên admin customer form; VN gate + ward-data + label "Phường/Xã" + hide-`sub_city`-for-VN move sang `Secomm_VietNamAddress` adapter; generic cascade còn lại country-agnostic, data-driven.
- [ ] **AC-5 (`sub_city` generic giữ nguyên):** Non-VN country có data 3 cấp vẫn render `sub_city`; VN (2 cấp) không render `sub_city` (data-driven, DEC-025). Field/infra `sub_city` + DB column không xóa.
- [ ] **AC-6 (Server-side validate — mirror `ValidateVietNamWard`):** Save customer address VN → validate ward ∈ province (qua `CityLocaleCollection` filter `(region_id, default_name)`); ward invalid → **neutralise + log warning (masked)**, **không crash save**; validation fault bị catch + log (non-fatal). [ASSUMPTION: hook vào customer address save path — xem Open Question Q1/Q3 cho target chính xác.]
- [ ] **AC-7 (Non-VN — no VN leak):** Non-VN country → generic Magento default (`city` text input native; `sub_city` chỉ khi country có data 3 cấp); không có behaviour/label VN rò rỉ.
- [ ] **AC-8 (Display/format — VN):** Address display (address book, order address, email/PDF) hiển thị region + ward (VN) đúng — không mất/truncated ward sau migrate.
- [ ] **AC-9 (Regression):** Storefront customer form (Luma + Hyva) + cart estimate (SL-003) + OSC + admin customer address CRUD (Add/Edit/Delete) nguyên vẹn; migrate không break storefront; `sub_city` legacy data non-VN không break.

---

## Technical Notes

### Audit baseline (AC-1) — code thực tế (verified 2026-08-03)

- [`customer_address_form.xml`](../../app/code/Secomm/AddressDropdown/view/adminhtml/ui_component/customer_address_form.xml) (generic `Secomm_AddressDropdown`): khai báo `city_select` (select, options `Selector\City`) + `sub_city` (select, options `Selector\SubCity`). Field generic OK theo DEC-025 (generic owns data-driven fields) — nhưng **labelling/gating VN phải move ra adapter**.
- [`provider-mixin.js`](../../app/code/Secomm/AddressDropdown/view/adminhtml/web/js/form/provider-mixin.js) (generic): drives cascade.
  - **Selector hard-code**: `ADDRESS_EDIT_FORM = '.customer_form_areas_address_address_customer_address_update_modal'` — chỉ trỏ **Edit** modal. Form **Add-new** (modal tạo address mới) có selector khác → **chưa được wire** → AC-2 (Add) hiện không hoạt động trọn vẹn. (Confirm bằng QC.)
  - **Không country-gate**: cascade chạy cho mọi country; dựa `GetListCity`/`GetListSubCity` trả empty cho non-VN để fallback native `city` text → implicit-VN leak (DEC-019).
  - `fetch('/graphql')` trực tiếp (endpoint hard-code) + jQuery + Knockout provider extension (admin stack — OK cho admin, **không** storefront).
  - `setSubCityRequired` force-add class `required` cho `sub_city` bất kể country (VN/2-level không nên có sub_city).
  - Ward persist dạng **name string** vào native `city` input (`CITY_INPUT.val(selectedCityId)` với `selectedCityId = city.default_name`). Align DEC-020 tech-debt (ward_id = `city_id` canonical, path A = future).

### Migration target

- **VN behaviour → `Secomm_VietNamAddress`** (admin customer-address adapter — NEW): khi `country == 'VN'`, `city_select` load wards theo `region_id` (reuse `CityLocaleCollection`/`GetListCity` data — **không duplicate**, DEC-019); label ward = "Phường/Xã" (i18n VietNamAddress); **2-level → không render `sub_city`**; server-side validate ward ∈ province.
- **Generic `Secomm_AddressDropdown`** (còn lại): cascade `region → city → sub_city` country-agnostic, data-driven; `GetListCity`/`GetListSubCity` resolvers + `Selector\City`/`Source\City` + `Selector\SubCity` (generic data + options); helper inject dropdown; persist vào `customer_address_entity` (store native của form).
- **Persist**: `customer_address_entity.city` (ward name string, VN); `sub_city` persist cho country non-VN 3-level (legacy data không break). Không cross-wire store.
- **Precedent** (mirror): frontend cart [`shipping.phtml`](../../app/code/Secomm/VietNamAddress/view/frontend/templates/hyva/php-cart/shipping.phtml) (ward dropdown gated `country==VN`) + [`ValidateVietNamWard.php`](../../app/code/Secomm/VietNamAddress/Plugin/Cart/ValidateVietNamWard.php) (`beforeCollectRates`, neutralise invalid ward + log, non-fatal catch).

### Files / Areas Affected

- `Secomm_AddressDropdown/view/adminhtml/ui_component/customer_address_form.xml` — generalize (gỡ VN-specific labelling/gating; giữ field generic).
- `Secomm_AddressDropdown/view/adminhtml/web/js/form/provider-mixin.js` — gỡ VN-specific; country-agnostic cascade; fix **Add-new modal selector gap**.
- **NEW** `Secomm_VietNamAddress/` — admin customer-address VN adapter (render ward dropdown khi VN + label i18n + hide sub_city + validate).
- `Secomm_AddressDropdown/Model/Customer/Address/Config/Selector/City.php` (+ `SubCity`) — generic wards/sub-cities by region — reuse.
- **KHÔNG affect**: storefront `view/frontend/*`, data layer (`db_schema` — `sub_city` column giữ), GraphQL resolvers (reuse read-only).

---

## Dependencies

| Dependency | Type | Status | Notes |
|------------|------|--------|-------|
| [DEC-025](../records/decisions/DEC-025.md) (global mechanism + adapter; sub_city generic) | decision | accepted | pin architecture cho SL-011..014 |
| [DEC-020](../records/decisions/DEC-020.md) (ward = city level; name-string persistence) | decision | accepted | ward persist name string = tech-debt hiện hành |
| [DEC-019](../records/decisions/DEC-019.md) + DEC-17 (generic vs VN boundary) | decision | accepted | SL-011 = migrate VN ra VietNamAddress |
| [SL-007](../tickets/SL-007-generalize-addressdropdown-remove-vn-leak.md) (storefront generic cleanup) | ticket | proposed | cùng boundary generic/VN — coordinate, không overlap |
| SL-012 / SL-013 / SL-014 (sibling admin surfaces) | ticket | proposed | share `Selector\City` + ward data |
| FEAT-005 / SL-003 (frontend cart precedent) | feature | proposed | `ValidateVietNamWard` + `shipping.phtml` — mirror pattern |

---

## Risks & Unknowns

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| **Add-new modal gap** — selector `ADDRESS_EDIT_FORM` chỉ trỏ Edit modal; form Add-new chưa wire | H | H | AC-2 cover Add; widen/generalize selector hoặc bind theo form context thay vì class hard-code; QC cả Add lẫn Edit |
| Migrate break storefront (customer form/cart/OSC đang dùng cascade) | M | H | không chạm `view/frontend/*`; SL-007 coordinate; regression AC-9 QC full |
| Provider-mixin migration sai → cascade/pre-select lệch | M | M | preserve behavior chính xác; QC Add+Edit pre-select; mirror cart pattern |
| Server-side validate hook sai path (customer address save ≠ cart `beforeCollectRates`) | M | M | xác định target (plugin `Magento\Customer\Model\Address` save / observer `customer_address_save_before`) — Open Q1/Q3 SA |
| `sub_city` non-VN 3-level hỏng sau migrate | L | M | AC-5 verify country non-VN 3-level; data-driven, không xóa infra |
| Tier-2 customer/PII — data ward sai persist | L | H | QC end-to-end + TL review (Mode A) |

---

## Out of Scope

- Các admin surface khác: order create/edit address (→ SL-012), Store Information + Shipping Origin (→ SL-013), MSI Source (→ SL-014).
- Storefront generic cleanup / VN leak storefront (→ SL-007).
- ward_id / `city_id` canonical persistence (path A, DEC-020 long-term follow-up) — ward vẫn persist name string trong scope này.
- DB schema migration (`sub_city` column + infra giữ nguyên).
- GraphQL resolver thay đổi (reuse `GetListCity`/`GetListSubCity` read-only).

---

## Open Questions

> Mode A → architecture/scope unknowns escalate SA/TL (AGENTS.md §11 Tier-2). Không quyết thay.

- **Q1 (SA — blocker cho plan):** Cơ chế VN adapter cho admin customer form — (a) `view/adminhtml/ui_component/customer_address_form.xml` override trong `Secomm_VietNamAddress` (module load order `module.xml` sequence), (b) JS provider-mixin riêng trong VietNamAddress gắn VN gate lên cascade generic, hay (c) layout `customer_address_form.xml` update? Ưu tiên option giữ generic cascade nguyên vẹn + ít xung đột merge ui_component.
- **Q2 (SA):** Label ward = "Phường/Xã" qua `Secomm_VietNamAddress` i18n (`vi_VN.csv` + `en_US.csv`)? Confirm source label + fallback generic "City".
- **Q3 (SA/TL):** Server-side validate target cho customer address save — plugin `Magento\Customer\Model\Address::beforeSave` / observer `customer_address_save_before` / admin controller post? Cart precedent dùng `beforeCollectRates`; customer path khác → confirm hook.

---

## Test Notes

- **Admin Add (VN):** country=VN → cascade province→ward; ward options theo region; persist `customer_address_entity.city` = ward; **không** render `sub_city`.
- **Admin Edit (VN):** pre-select region + ward đúng; không reset ward khi hydrate.
- **Admin Add/Edit (non-VN):** native `city` text input; `sub_city` chỉ hiện nếu country có data 3 cấp; không leak VN label/behaviour.
- **Server-side validate:** ward không thuộc province (篡改 payload) → neutralise + log warning masked; save không crash; fault bị catch + log.
- **Display:** address book / order address / email / PDF hiển thị region + ward VN đầy đủ.
- **Regression:** storefront customer form (Luma + Hyva), cart estimate (SL-003 cascade), OSC, admin customer address CRUD (Add/Edit/Delete); `sub_city` legacy data non-VN nguyên vẹn.
- Evidence → `.ai/runtime/evidence/FEAT-007/` (SL-011). [ASSUMPTION: evidence path theo convention FEAT-007; confirm nếu project dùng path khác.]
