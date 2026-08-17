# Implementation Plan: SL-011 — Admin VN 2-level address dropdown (customer-address form)

> Mode A · **Plan only — chưa viết code** (Hard Gate: No Code Without Plan; §7.1).
> Tier-2 (customer/PII — §9 L398, §12 AddressDropdown L421) → escalate SA/TL; code review trước/sau.
> Parent: [FEAT-007](../records/features/FEAT-007.md). Ticket: [SL-011](../tickets/SL-011-apply-address-dropdown-admin-customer-form.md). Spec: [admin-vn-address-customer-form](../specs/admin-vn-address-customer-form.md) — **approved 2026-08-03 (user acting as SA/TL)**.

## Metadata

| Field | Value |
|-------|-------|
| Ticket | [SL-011](../tickets/SL-011-apply-address-dropdown-admin-customer-form.md) |
| Spec | [admin-vn-address-customer-form](../specs/admin-vn-address-customer-form.md) — **approved** |
| Feature | [FEAT-007](../records/features/FEAT-007.md) |
| Author | AI draft |
| Reviewer (TL) | user (acting as SA/TL) — **pending plan approval** |
| Workflow Mode | A |
| Date | 2026-08-03 |
| Decisions | DEC-025 (accepted) · DEC-020 (accepted) · DEC-019 · DEC-17 |
| Validation level | **L3** (customer/PII + address data — §8.6) |

## 1. Approach

**Tách generic mechanism (AddressDropdown) vs country adapter (VietNamAddress) — DEC-019/025.** Mirror frontend cart precedent (SL-003 `shipping.phtml` + `ValidateVietNamWard`): VN behaviour gated `country == 'VN'`, ward = native `city`, 2-level (không `sub_city`), server-side validate ward ∈ province.

- **Generic `Secomm_AddressDropdown`** giữ: data-driven cascade `region → city → sub_city` (country-agnostic), GraphQL `GetListCity`/`GetListSubCity` (với `area: "adminhtml"`), `Selector\City`/`Source\City`/`Selector\SubCity` option models. **Sửa**: (a) gap **Add-new modal** (selector hiện chỉ trỏ Edit modal), (b) `sub_city` data-driven thay vì force-required, (c) gỡ mọi giả định/label VN-specific. KHÔNG còn `country == 'VN'` hay "Phường/Xã" trong generic.
- **`Secomm_VietNamAddress`** (NEW admin adapter): khi `country == 'VN'` → overlay label `city_select` = "Phường/Xã", **hide `sub_city`** (2-level DEC-020/025), reuse data `GetListCity(region_id)` (KHÔNG duplicate, DEC-019), ward persist native `city`. Non-VN → generic mechanism nguyên vẹn.
- **Server-side validate** (mirror `ValidateVietNamWard`): plugin `beforeSave` trên `Magento\Customer\Api\AddressRepositoryInterface` — country==VN → check ward ∈ region qua `CityLocaleCollection` filter `(region_id, default_name)`; invalid → neutralise `city` (clear) + log warning masked; catch non-fatal. Không crash save.
- **Persist**: `customer_address_entity.city` (ward name string, VN — tech-debt DEC-020, path A = future); `sub_city` giữ generic cho country non-VN 3-level. Không cross-wire store, không đổi `db_schema`.

**Lý do chọn:** tách generic/adapter đúng DEC-019/025 (SL-007 align ở storefront); reuse data read-only; validate mirror precedent đã proven; sửa gap Add-new (bug hiện tại) trong cùng pass.

### Open questions RESOLVED (trong plan này — SA/TL user)

- ~~**Q1 (adapter mechanism):**~~ **RESOLVED → Option B**: generalize `provider-mixin.js` (AddressDropdown) country-agnostic + **NEW JS form-modifier/mixin trong `VietNamAddress`** overlay VN behaviour (label, hide sub_city, reuse GetListCity) khi `country==VN`. Lý do: mirror frontend split (VN = gated layer over generic data); giữ generic cascade nguyên vẹn + reusable; tránh xung đột merge ui_component XML; dynamic country-gating cần JS (XML không đủ). Module load order: `VietNamAddress` sau `AddressDropdown` (verify `module.xml` sequence). *Alternative đã loại: ui_component XML override (không dynamic-gate được)*, *single VN-owned cascade (sẽ leak ngược generic vào VN module)*.
- ~~**Q2 (label i18n):**~~ **RESOLVED → "Phường/Xã"** qua `Secomm_VietNamAddress` i18n (`vi_VN.csv` = "Phường/Xã", `en_US.csv` = "Ward/Commune" — mirror frontend `__('Ward/Commune')`). Generic giữ "City".
- ~~**Q3 (validate hook):**~~ **RESOLVED → plugin `beforeSave` trên `Magento\Customer\Api\AddressRepositoryInterface`** (khác cart `beforeCollectRates` — customer save path khác). Gate country==VN; neutralise + log; non-fatal catch. Mirror logic `ValidateVietNamWard::beforeCollectRates`.

## 2. Files affected

| File | Change type | Lý do / AC |
|------|-------------|-------|
| `Secomm/AddressDropdown/view/adminhtml/ui_component/customer_address_form.xml` | edit (minor) | giữ `city_select` + `sub_city` generic; gỡ label/assumption VN nếu có (AC-4) |
| `Secomm/AddressDropdown/view/adminhtml/web/js/form/provider-mixin.js` | edit | generalize: **fix Add-new modal selector gap** (bind cả create + update modal, không hard-code container class); `sub_city` data-driven (gỡ force-required unconditional); country-agnostic (AC-2/4/5) |
| `Secomm/AddressDropdown/etc/adminhtml/requirejs-config.js` (nếu có) | verify/edit | đảm bảo generic mixin đăng ký đúng (không vỡ sau khi tách) |
| **NEW** `Secomm/VietNamAddress/view/adminhtml/web/js/form/vn-address-form.js` (mixin/modifier) | new | overlay VN: `country==VN` → label `city_select` "Phường/Xã", hide `sub_city`, reuse `GetListCity(region_id)` load wards, persist native `city`; non-VN no-op (AC-2/3/4/7) |
| **NEW** `Secomm/VietNamAddress/view/adminhtml/requirejs-config.js` | new | đăng ký VN form mixin (apply sau generic — module sequence) |
| **NEW** `Secomm/VietNamAddress/Plugin/Customer/Address/ValidateVietNamWard.php` | new | `beforeSave` trên `AddressRepositoryInterface`; mirror `Plugin/Cart/ValidateVietNamWard` logic (neutralise + log masked + non-fatal) (AC-6) |
| **NEW** `Secomm/VietNamAddress/etc/adminhtml/di.xml` | new | register validate plugin (type `Magento\Customer\Api\AddressRepositoryInterface`, method `save`) |
| `Secomm/VietNamAddress/etc/module.xml` | verify/edit | sequence: load **sau** `Secomm_AddressDropdown` (để VN mixin/fieldata apply sau generic) |
| `Secomm/VietNamAddress/i18n/vi_VN.csv` + `en_US.csv` | new/edit | "Phường/Xã" / "Ward/Commune" (AC-4/Q2) |
| **KHÔNG sửa** `Secomm_AddressDropdown/view/frontend/*`, GraphQL resolvers, `db_schema.xml`, `Selector\City`/`SubCity` source models | — | generic country-agnostic (DEC-019); reuse read-only; `sub_city` column giữ |

## 3. Steps (độc lập reviewable, theo thứ tự)

1. **Audit note (AC-1) + baseline** — risk: low — deps: none
   - Ghi audit note (đã có trong spec §Technical Notes): VN logic hiện trong AddressDropdown, gap Add-new, sub_city force-required, chưa validate. Pin baseline để verify migration.
   - verify: note committed vào evidence.
2. **Generalize generic `provider-mixin.js` (AddressDropdown)** — risk: medium — deps: 1
   - **Fix Add-new gap**: thay selector hard-code `.customer_form_areas_address_address_customer_address_update_modal` bằng cơ chế match cả create + update modal (theo presence của `select[name="city_select"]` trong scope form, không phụ thuộc container class cụ thể). Test cả 2 modal.
   - `sub_city`: data-driven — chỉ show/required khi country có data 3 cấp (dựa response `GetListSubCity`); gỡ `setSubCityRequired` unconditional.
   - Country-agnostic: đảm bảo cascade chạy cho mọi country, fallback native `city` text khi không có data; không label/assume VN.
   - verify: Add modal + Edit modal đều wire cascade; non-VN fallback text input; sub_city chỉ hiện khi có data 3 cấp.
3. **VN admin adapter JS (VietNamAddress)** — risk: medium — deps: 2
   - `vn-address-form.js` overlay: khi `country==VN` → đổi label `city_select` thành "Phường/Xã"; hide `sub_city` (2-level); wards load reuse `GetListCity(region_id)` (generic data); ward persist native `city` (`input[name="city"]`). Non-VN → no-op (generic nguyên vẹn). Mirror frontend `isVietnam()` gate + out-of-order guard (`_wardReqId`) cho stale region response.
   - `requirejs-config.js` đăng ký mixin (apply sau generic).
   - verify: VN → ward dropdown + label Phường/Xã + no sub_city; non-VN → generic; Edit pre-select ward đúng.
4. **Server-side validate plugin (VietNamAddress)** — risk: medium — deps: none (parallel-safe với 2/3)
   - `Plugin/Customer/Address/ValidateVietNamWard.php` `beforeSave(AddressRepositoryInterface, AddressInterface)`: gate `country == 'VN'`; bỏ qua khi region/ward rỗng (incomplete); collection filter `(region_id, default_name)`; invalid → `$address->setCity('')` + log warning masked; catch `\Exception` → log error, không rethrow (non-fatal). Reuse `CityLocaleCollectionFactory` (generic, read-only).
   - `etc/adminhtml/di.xml` register plugin.
   - verify: ward sai province → neutralised + logged, save không crash; ward hợp lệ → giữ; non-VN → skip; fault → log.
5. **i18n + module sequence** — risk: low — deps: 3
   - `vi_VN.csv`/`en_US.csv` "Phường/Xã"/"Ward/Commune"; verify `module.xml` sequence `VietNamAddress` after `AddressDropdown`.
   - verify: label render vi/en đúng; `setup:upgrade` không lỗi sequence.
6. **Tests + QC + evidence (L3)** — risk: low — deps: 2,3,4,5
   - Integration: validate plugin (valid/invalid/empty/non-VN/fault); unit: VN mixin gate logic. QC matrix (Add/Edit VN 2-level, non-VN text, non-VN 3-level sub_city, validate neutralise, display, regression storefront).
   - evidence → `.ai/runtime/evidence/FEAT-007/` (SL-011).

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Migrate break storefront (customer form/cart/OSC dùng cascade) | high | không chạm `view/frontend/*`; SL-007 coordinate; regression AC-9 QC full |
| Add-new modal fix lệch → cascade vỡ cả Edit | medium | bind theo `city_select` presence trong scope, không class hard-code; QC cả Add + Edit |
| Hai mixin (generic + VN) compose sai thứ tự/đouble-bind | medium | module sequence `VietNamAddress` after `AddressDropdown`; verify requirejs mix order; VN mixin chỉ overlay khi VN |
| Validate plugin chặn save hợp lệ (false positive) | high | neutralise (clear city) chứ không throw; chỉ gate khi ward ≠ ∅ region; log masked; QC ward hợp lệ giữ nguyên |
| `sub_city` non-VN 3-level hỏng sau generalize | medium | data-driven; AC-5 verify country 3-level có data |
| Tier-2 PII: ward persist sai/clear nhầm | high | validate chỉ neutralise khi sai province thật; QC end-to-end + TL review (Mode A) |

## 5. Test approach

- **Integration/Unit**: validate plugin (valid ward kept / invalid ward neutralised + logged / empty region+ward skipped / non-VN skipped / exception non-fatal logged); VN mixin gate logic (country VN → label+hide sub_city; non-VN no-op).
- **QC (L3)**: Admin Add (VN) cascade province→ward, persist `customer_address_entity.city`, no sub_city; Admin Edit (VN) pre-select region+ward; non-VN native city text; non-VN 3-level → sub_city hiện; validate篡改 payload → neutralise + log + save ok; display (address book/order/email/PDF) region+ward đầy đủ.
- **Regression**: storefront customer form (Luma+Hyva), cart estimate (SL-003 cascade), OSC, admin customer address CRUD (Add/Edit/Delete); `sub_city` legacy data non-VN nguyên vẹn.
- **High-risk**: customer address save path (PII); generic `provider-mixin.js` generalize (storefront reuse).

## 6. Out of scope

- Các admin surface khác: order create/edit address (SL-012), Store Information + Shipping Origin (SL-013), MSI Source (SL-014).
- Storefront generic/VN cleanup (SL-007) — coordinate, không overlap.
- ward_id / `city_id` canonical persistence (path A, DEC-020 long-term) — ward vẫn persist name string.
- DB schema migration (`sub_city` column giữ); GraphQL resolver changes (reuse read-only).

## 7. Open questions / Escalation

- Q1/Q2/Q3 **RESOLVED** trong §1 (Option B mixin / "Phường/Xã" i18n / `AddressRepositoryInterface::beforeSave` plugin).
- **Tier-2 escalation required** trước code (customer/PII). → **Plan chờ TL approval.**

### Implementation deviation (2026-08-03 — revealed by validation)

**VN admin JS mixin (Step 3 / file `vn-address-form.js` + requirejs-config.js) — OMITTED (justified).**
Đọc code generic thực tế: `provider-mixin.js` hiện **đã data-driven + country-agnostic** (không có literal `country=='VN'`, không label VN). Cho VN, generic cascade load wards qua `GetListCity` (generic data theo DEC-025) + tự hide `sub_city` (GetListSubCity trả empty cho data VN 2-level). Theo DEC-025, ward-data mechanism LÀ generic — vậy VN client-side behaviour đã đúng bởi generic mechanism. Thêm VN JS mixin redundant = rủi ro two-mixin composition không lợi. **VN-owned logic thực sự cần = server-side validate plugin (AC-6) — đã add.** Vậy AC-4 thoả: generic sạch (không VN leak); VN rule (validate) ở VietNamAddress. *Nếu TL muốn explicit client-side VN ownership (vd. label en_US "Ward/Commune" cho VN), add VN mixin sau.*

**`customer_address_form.xml` — không cần edit** (label "City"/"Sub City" đã generic, không assumption VN; `sub_city` required-entry=false đã đúng). Plan ghi "edit (minor)" → thực tế no-op.

### Status (post-implementation, pre-TL-review)

| Step | Status |
|------|--------|
| 1 Audit note | done (spec §Technical Notes) |
| 2 Generalize `provider-mixin.js` (Add-new gate + sub_city data-driven) | **done** — gate trên `city_select` presence (Add+Edit); gỡ `setSubCityRequired`; bind country-change trong gate |
| 3 VN admin JS mixin | **omitted** (deviation trên) |
| 4 Server-side validate plugin + `etc/adminhtml/di.xml` | **done** — `beforeSave` trên `AddressRepositoryInterface`, mirror cart precedent |
| 5 i18n + module sequence | done — vi_VN/en_US đã có "Ward/Commune"; `module.xml` sequence `VietNamAddress→AddressDropdown` đã có |
| 6 Tests + QC + evidence | **pending** — chờ TL review → QC L3 |

Pre-review (§8.3): PHP lint OK · JS syntax OK · AddressInterface/RegionInterface methods verified · no ObjectManager · parameterized collection filter (no SQL injection) · no dangling refs · error paths có catch/log.

### Follow-up fix (2026-08-04 — Step 2 hardening, root cause of "city dropdown không load data")

**Triệu chứng báo cáo:** admin customer-address form — city dropdown hiện nhưng không load ward (VN). **Đã verify backend 100% OK** (DB 34 tỉnh/3313 ward; GraphQL `GetListCity(region_id=1157,area=adminhtml)` HTTP 200 → 126 ward; module enabled; mixin đăng ký đúng vào `Magento_Ui/js/form/provider`). **Root cause = frontend gating** trong `provider-mixin.js`:

- Form address load qua **AJAX** (`Magento_Customer/js/form/components/insert-form` → `destroyInserted`+re-render mỗi lần mở). Tại tick `setInterval(500)` mà `select[name="city_select"]` xuất hiện, `region_id` chưa chắc hydrate xong → guard `if (initialRegionId)` skip `_loadCities`; Magento set region programmatically không dispatch `change` → mixin không recovery → dropdown kẹt placeholder.
- Selector global `[name="region_id"]`/`select[name="city_select"]` không scope theo form (multi-modal / re-render rủi ro bind sai).

**Fix (Option A — hardening, blast radius nhỏ, giữ generic mechanism theo DEC-025):**
1. **Root-scope**: `_resolveRoot()` = closest `fieldset` (fallback form/document) chứa region_id; mọi element/handler resolve trong root → fix multi-modal.
2. **Bỏ race `if (initialRegionId)`**: `_loadCitiesWhenReady()` poll `region_id` ≤3s tới khi có giá trị rồi `_loadCities` 1 lần (Edit pre-select path); Add-new vẫn dựa region-change handler.
3. **Guard re-entry**: `$root.data('secommCascadeBound')`; `destroy()` clear 2 timer (insertForm tear-down) → không double-bind/orphaned interval.
4. **(kèm) GraphQL escape** `escapeGraphQlValue` cho `region_id`/`default_name` (mirror SL-012 `address-cascade.js`; rule "escape all output").
5. **Edit pre-select robust**: saved city đọc từ `self.data.city` (provider data) thay vì chỉ DOM input.
6. **Stale guard** `_cityReqId` drop city response của region cũ.

JS parse-check OK (node harness: factory → enhancer → extend config, đủ 9 method). Developer mode → serve từ source, không cần `setup:di:compile`/static-deploy; chỉ hard-refresh browser. **Chờ QC L3** (Add/Edit VN 2-level, non-VN text, pre-select ward) + TL review (Tier-2).

- Follow-up (ngoài scope): review `sub_city` chỉ khi country non-VN cần 3-level (hiện chưa có); ward_id path A ticket riêng; frontend customer-address ward validate (nếu muốn defence-in-depth — hiện adminhtml-only).
