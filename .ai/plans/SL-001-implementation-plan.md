# Implementation Plan: SL-001 — Hyvä-native AddressDropdown (MODULE layer)

| Field | Value |
|---|---|
| Specification | specs/addressdropdown-hyva.md |

> ⚠️ **LEGACY (Phase 1a) — superseded by [`records/features/FEAT-001.md`](../records/features/FEAT-001.md).** Read-only; excluded from default context loading; pending equivalence validation. Note: this plan (2026-07-16) is Hyva-only and **predates DEC-9** — the canonical FEAT-001 reconciles the drift to dual-theme. Do not edit — update FEAT-001 instead.

> Mode A · **Plan only — chưa viết code** (Hard Gate 3: No Code Without Plan).
> ✅ **Approved** 2026-07-16 — gate `plan-approval` (Level 2) passed (user acting as TL/SA). ⚠️ Step 1 (resolver hardening) still gated on Q1 (Tier 2 SA) before execution.

## Metadata

| Field | Value |
|-------|-------|
| Ticket | [SL-001](../tickets/SL-001-apply-hyva-theme-addressdropdown.md) |
| Spec | [addressdropdown-hyva](../specs/addressdropdown-hyva.md) (Approved) |
| Author | AI draft |
| Reviewer (TL) | user (acting as TL/SA) — approved 2026-07-16 |
| Plan status | ✅ **Approved** — `plan-approval` gate (Level 2) passed 2026-07-16. ⚠️ Step 1 (resolver hardening) still gated on Q1 (Tier 2 SA) before execution. |
| Workflow Mode | A |
| Date | 2026-07-16 |
| Decisions | DEC-7 (Strategy B) · DEC-8 (module/Launchpad boundary) |
| Validation level | **L3** (chạm customer address data — §8.6) |

## 1. Approach

**Strategy B (DEC-7) + boundary (DEC-8):** rewrite **generic storefront surfaces** (customer address form + cart estimation) sang Hyvä-native = **Magewire 1.13 component** (stateful cascade) + **Alpine.js** (UI binding) + **Tailwind v4** (CSS-first). Backend/GraphQL/admin/default-checkout **giữ nguyên**.

**Tái dùng data layer:** GraphQL `GetListCity`/`GetListSubCity` (đã có) + core Magento GraphQL (`countries`/`regions`). Cascade flow: country (core) → region (core) → city (`GetListCity` by `region_id`) → sub_city (`GetListSubCity` by `default_name`+`region_id`).

**Research findings (state 3):**
- Customer form đã là Block override (`Edit::getTemplate()` → `Secomm_AddressDropdown::address/edit.phtml`) — **giữ** cơ chế. phtml hiện dùng **jQuery + `text/x-magento-init`** (`directoryRegionUpdater` + `directoryAddressDropdownUpdater` qua requirejs) → **break trên Hyvä** (no jQuery/RequireJS) → phải rewrite.
- Cart estimation: jQuery mixin `cart/shipping-estimation-mixin.js` nhắm Knockout `Magento_Checkout/js/view/cart/shipping-estimation` → **không chạy trên Hyvä cart** → replace Magewire. `checkout_cart_index.xml` hiện chỉ load CSS.
- Resolver `GetListCityGraphql`: `addFieldToSelect('*')`, no cache → Q1 cải thiện.
- `sub_city` là **custom attribute** (`getCustomAttribute('sub_city')`) — Magewire phải submit đúng vào form save (`getSaveUrl()`).
- Trên Hyvä, `view/frontend/requirejs-config.js` KHÔNG được load → các Knockout/mixin default-checkout **tự inert** (AC-006/AC-012 tự thỏa). Cleanup tập trung vào customer form + cart + Ahamave leak.
- **Ahamave**: `Secomm_Ahamove` **không có trong project này**; chỉ có **1 reference duy nhất** trong toàn repo (`requirejs-config.js:23`, mixin target `'Secomm_Ahamove/js/view/shipping-address/address-renderer/default'`). Consumer duy nhất của mixin tương ứng là reference này → remove = zero functional impact + sửa boundary DEC-8.

## 2. Files affected

| File | Change type | Lý do / AC |
|------|-------------|-----------|
| `Block/Customer/Address/Edit.php` | modify | Giữ `getTemplate()`; thêm ViewModel nếu cần (AC-001) |
| `view/frontend/templates/address/edit.phtml` | **rewrite** | jQuery/requirejs → Magewire+Alpine+Tailwind (AC-001, AC-006, AC-008) |
| `view/frontend/templates/address/dropdown-magewire.phtml` (mới) | new | Magewire partial cho cascade (AC-001) |
| `Magewire/AddressDropdown.php` (mới trong module) | new | Magewire component: state cascade + loadCities/loadSubCities (AC-001, AC-005, AC-011) |
| `etc/frontend/di.xml` hoặc layout | modify | Đăng ký Magewire component / ViewModel (AC-001) |
| `view/frontend/layout/customer_address_form.xml` | modify | Remove CSS head (`Secomm_AddressDropdown::css/styles.css`); gắn Magewire (AC-001, AC-008) |
| `view/frontend/layout/checkout_cart_index.xml` | modify | Thêm Magewire cascade vào `checkout.cart.shipping` (AC-005) |
| `view/frontend/web/js/view/cart/shipping-estimation-mixin.js` | **delete** | Replace bằng Magewire (AC-005, AC-006) |
| `view/frontend/web/js/address-dropdown.js` | **delete** (verify no consumer) | Cascade requirejs cũ cho customer form (AC-006) |
| `view/frontend/web/js/view/shipping-address/address-renderer/default-mixin.js` | **delete** | Orphan — consumer duy nhất = Ahamave ref (AC-010) |
| `view/frontend/requirejs-config.js` | modify | Remove map `directoryAddressDropdownUpdater` + Ahamave mixin (line 23) + cart mixin (AC-006, AC-010) |
| `view/frontend/web/template/*.html` (Knockout storefront) | giữ/xóa theo Q-DC | Default-checkout Knockout — dormant (AC-012); storefront-only thì xóa |
| `view/frontend/web/css/styles.css` | **delete** | → Tailwind (AC-008) |
| `etc/schema.graphqls` | modify | Undeprecate `GetListCity`/`GetListSubCity` + `@cache(cacheable: true)` (Q1, SA) (AC-011) |
| `Model/Resolver/GetListCityGraphql.php` + `GetListSubCityGraphql.php` | modify | Explicit fields (no `*`) + cache key (Q1) (AC-011) |
| `i18n/vi_VN.csv` + `i18n/en_US.csv` | modify | Chuỗi mới (AC-007, BR-001) |
| `app/design/frontend/Secomm/launchpad/web/tailwind/tailwind-source.css` | modify | Đảm bảo `@source` scope cho dynamic dropdown class (AC-008) |

## 3. Steps (độc lập reviewable, theo thứ tự)

1. **Resolver hardening (Q1)** — risk: low — deps: none
   - `schema.graphqls`: remove `@deprecated` + set `@cache(cacheable: true)` cho `GetListCity`/`GetListSubCity`.
   - 2 resolver: `addFieldToSelect(['city_id','region_id','default_name','label'…])` (no `*`); thêm cache key theo `region_id`/`default_name`.
   - verify: GraphQL playground query trả về + cache hit (L3 — GraphQL surface).
   - ⚠️ Tier 2 (GraphQL) — escalate SA trước step này.

2. **Magewire cascade component** — risk: medium — deps: step 1
   - Tạo `Magewire/AddressDropdown.php` (extends `\Magewirephp\Magewire\Component`): public props `countryId, regionId, city, subCity` + arrays `cities[], subCities[]`; methods `updatedRegionId()` → load cities, `updatedCity()` → load sub-cities (gọi repository/GraphQL server-side, KHÔNG client `/graphql`).
   - verify: unit/integration — cascade state đúng theo region_id → city → sub_city.

3. **Customer address form rewrite (AC-001)** — risk: medium — deps: step 2
   - Rewrite `address/edit.phtml`: thay `text/x-magento-init` (jQuery `directoryRegionUpdater` + `directoryAddressDropdownUpdater`) bằng Magewire component (`wire:model`) + Alpine; submit `region_id`, `city`, `sub_city` (custom attribute) vào `getSaveUrl()`.
   - Giữ Block `Edit::getTemplate()` override; ensure region/country dùng core Magento GraphQL (Hyvä pattern).
   - verify: mở Customer → Add/Edit Address, chọn VN → cascade Province→District→Ward, save thành công (L3 — customer data).

4. **Cart estimation rewrite (AC-005)** — risk: medium — deps: step 2
   - `checkout_cart_index.xml`: gắn Magewire cascade vào `checkout.cart.shipping`; remove CSS head.
   - Delete `cart/shipping-estimation-mixin.js` + entry requirejs.
   - verify: cart page → chọn VN address → estimate cập nhật.

5. **Cleanup & leak removal (AC-006, AC-010, AC-012)** — risk: low — deps: step 3,4
   - Delete `address-dropdown.js` (verify no consumer ngoài customer form).
   - `requirejs-config.js`: remove `directoryAddressDropdownUpdater` map + cart mixin. (Default-checkout mixins Knockout: giữ dormant theo Q-DC.)
   - **Ahamave cleanup (AC-010)** — đã verify `Secomm_Ahamove` không có trong project; **1 reference duy nhất** toàn repo = `requirejs-config.js:23` (mixin target `'Secomm_Ahamove/js/view/shipping-address/address-renderer/default'`). Remove block này = zero functional impact + sửa boundary DEC-8. Đồng thời **delete file orphan** `view/frontend/web/js/view/shipping-address/address-renderer/default-mixin.js` (consumer duy nhất = Ahamove ref vừa xóa).
   - Delete `web/css/styles.css` (→ Tailwind).
   - verify: scan storefront — migrated surfaces không load requirejs/Knockout của module; default-checkout dormant không leak; `grep -ri ahamove` trong module = 0 hit.

6. **i18n + Tailwind (AC-007, AC-008)** — risk: low — deps: step 3,4
   - Thêm chuỗi (label dropdown, placeholder) vào `vi_VN.csv` + `en_US.csv`.
   - `tailwind-source.css`: đưa dynamic class dropdown vào `@source`; **không tạo `tailwind.config.js`**.
   - verify: `npm run build` trong `web/tailwind/`; chuyển locale vi/en render đúng.

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Customer address save fail (sub_city custom attribute không submit) | high | Test save + verify `getCustomAttribute('sub_city')` persist; L3 customer data |
| Region/country cascade lệch giữa core Magento GraphQL vs Magewire state | medium | Dùng core GraphQL `countries`/`regions`; test cả tạo mới + edit existing address |
| GraphQL cache trả data sai khi region_id đổi | medium | Cache key include `region_id`+`default_name`; test cascade đổi region |
| Xóa nhầm file còn consumer (address-dropdown.js) | medium | grep consumer trước xóa |
| Tailwind purge dynamic class | medium | `@source` scope; build check |
| Admin/backend regression | low | Không động `Api/`/`Model`/`Setup`/admin; AC-009 |

> Checkout/payment regression → **KHÔNG thuộc SL-001** (OSC ở SL-002).

## 5. Test approach

- **Unit/Integration**: Magewire component cascade state (region→city→sub_city); resolver explicit-field + cache.
- **QC (L3 — customer data)**: customer address form — tạo mới + edit existing, cascade đầy đủ vi/en; cart estimation; admin CRUD + CSV import/export nguyên vẹn (AC-009).
- **Build**: `npm run build` (`web/tailwind/`); storefront scan no requirejs/Knockout leak.
- **High-risk validation**: GraphQL surface (Q1) + customer address data integrity — escalate SA/TL.

## 6. Out of scope

- Mageplaza OSC integration → **SL-002**.
- Admin area migration; backend `Api/`/`Model`/`Setup`/import logic.
- Rewrite default/Luma checkout sang Hyvä (giữ dormant, Q-DC).
- `Secomm_VietNamAddress` data set; `Secomm_Ahamave` integration (chỉ remove dead ref + orphan file).
- Multi-store scope (BR-TBD-001).

## 7. Open questions / Escalation

- **Q1 — Resolver cache/undeprecate** (step 1): SA quyết (Tier 2 GraphQL). _Default plan: undeprecate + `@cache(true)` + explicit fields._
- **Q-DC — Default-checkout Knockout code** (step 5): giữ dormant (mặc định) hay xóa hẳn storefront? SA quyết.
- **Q-MW — Magewire + native Magento address form submit**: confirm pattern submit `sub_city` custom attribute qua `getSaveUrl()` không break validation (research thêm `Magento_Customer` address save flow nếu cần).
- **Gate**: `plan-approval` (TL, Level 2) — plan phải được TL duyệt trước khi implement (workflow state 4 → 5).
