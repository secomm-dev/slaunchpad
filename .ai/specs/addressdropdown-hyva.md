# Feature Spec — Dual-theme (Luma + Hyva) AddressDropdown — MODULE Layer (TASK-88NDV5)

> ⚠️ **LEGACY (Phase 1a) — superseded by [`records/features/FEAT-YVN39K.md`](../records/features/FEAT-YVN39K.md).** Read-only; excluded from default context loading; pending equivalence validation. Material knowledge consolidated into the canonical feature record. Do not edit — update FEAT-YVN39K instead.

<!-- Spec cho ticket TASK-88NDV5 · Mode A · Stack: Magento 2.4.8-p5 + Hyvä 3.x (+ Luma support) -->
<!-- AI draft — chờ SA/TL signoff (workflow gate `spec-approval`, Level 2). -->
<!-- SCOPE (revised per DEC-8 + DEC-9): TASK-88NDV5 = MODULE layer, theme-agnostic (Luma + Hyva), KHÔNG coupling OSC. -->
<!-- OSC integration → ticket riêng TASK-FMAN1B (Launchpad package). -->

---

## Feature Overview

**Feature name**: Dual-theme (Luma + Hyva) frontend cho `Secomm_AddressDropdown` — **MODULE layer** (generic surfaces, reusable)

**Ticket reference**: [TASK-88NDV5](../tickets/TASK-88NDV5-apply-hyva-theme-addressdropdown.md)

**Feature type**: Refactor (frontend — dual-theme)

**Priority**: P1 (High)

**Architecture decisions**:
- **DEC-7**: Strategy B — Hyvä-native (Alpine.js + `.phtml` + Tailwind v4). *(refined by DEC-9)*
- **DEC-8**: Reusability boundary — module sở hữu **generic Magento-native surfaces**; **OSC coupling bị cấm trong module** → OSC thuộc package **Launchpad** (TASK-FMAN1B).
- **DEC-9**: **Dual-theme** — module hỗ trợ BOTH Luma (native jQuery/Knockout) VÀ Hyva (native Alpine). Theme switch qua `hyva_` layout handle (zero-PHP). Không còn Hyva-only.

**Status**: ✅ **Approved** 2026-07-16 (spec-approval). Architecture refined 2026-07-17 (DEC-9 dual-theme). Implementation done for customer form + cart + cleanup; Step 1 (resolver, Q1) deferred.

**Business rules áp dụng**: BR-001 (i18n vi/en — both themes), BR-002 (module portion: customer address + cart cascade — 3 cấp VN: country→region[tỉnh]→city[phường/xã]). BR-004 (OSC) → **TASK-FMAN1B**.

---

## Scope Boundary (DEC-8 + DEC-9)

- **IN — module (reusable, theme-agnostic)**: customer address form + cart shipping estimation — **cả Luma + Hyva**. Default/Luma checkout integration giữ (Luma path, inert trên Hyva/OSC).
- **OUT → TASK-FMAN1B (Launchpad package)**: Mageplaza OSC address integration + mọi code coupling OSC.
- **Leak cleanup**: module **không** reference `Mageplaza_Osc` hay `Secomm_Ahamove` (DEC-8). Remove dead ref Ahamave (AC-010).

---

## User Stories (module layer, dual-theme)

- **US-001**: As a customer, I want VN hierarchical dropdown (region[tỉnh] → city[phường/xã]) render **đúng theo theme** — Alpine/GraphQL trên Hyva, jQuery/requirejs trên Luma — để nhập address chính xác trên mọi client stack.
- **US-002**: As a customer ở cart, I want shipping estimate hoạt động trên cả Luma (Knockout mixin) + Hyva (native php-cart).
- **US-003**: As a QC, I want cascade country→region→city (3 cấp VN) + i18n vi/en **đồng nhất trên cả 2 theme** (generic `__()` keys + `Secomm_VietNamAddress` dict).
- **US-004**: As a developer maintainer, module là **reusable generic** — deploy cho client Luma hoặc Hyva đều chạy đúng surface generic, không cần sửa code.

---

## Acceptance Criteria (module layer, dual-theme)

> Một AC = một test case. QC verify theo BR-002 (cascade) + BR-001 (i18n) trên **cả Luma + Hyva**.

- [ ] **AC-001** (Customer form — dual-theme routing): Given customer form, When theme = Hyva → render `hyva/address/edit.phtml` (Alpine + GraphQL); When theme = Luma → render `edit.phtml` (jQuery + requirejs `directoryAddressDropdownUpdater`). Switch qua `hyva_customer_address_form` handle (DEC-9). (BR-002)
- [ ] **AC-001a** (Cascade — both themes): Country=VN → region[tỉnh] → city[phường/xã] cascade hoạt động trên cả 2 theme; sub_city ẩn (3 cấp VN); address save persist đúng `city`/`sub_city`. (BR-002)
- [ ] **AC-005** (Cart estimation — dual-theme): Luma = Knockout mixin (`cart/shipping-estimation-mixin.js`) hoạt động; Hyva = native `php-cart/shipping.phtml` (region-based). Estimate cập nhật trên cả 2.
- [ ] **AC-006** (No leak trên Hyva): Trên Hyva storefront, các file Luma (requirejs/Knockout của module) **không load** (inert); trên Luma chúng active (intentional). Customer form Hyva = `hyva/address/edit.phtml` thuần Alpine.
- [ ] **AC-007** (i18n — BR-001, both themes): cả `edit.phtml` + `hyva/address/edit.phtml` dùng cùng generic `__()` keys (`City`, `State/Province`, `Sub-City`, placeholders) → `Secomm_VietNamAddress` dịch đồng nhất 2 theme (Province/City, Ward/Commune / Tỉnh/Thành phố, Phường/Xã).
- [ ] **AC-008** (Tailwind v4 — Hyva only): `hyva/address/edit.phtml` dùng Tailwind (CSS-first `@theme`/`@source`); không tạo `tailwind.config.js`. (Luma dùng styles.css classic.)
- [ ] **AC-009** (Regression — admin/backend): admin CRUD City/Region/SubCity/Country + CSV import/export nguyên vẹn.
- [ ] **AC-010** (Ahamave cleanup): dead ref `Secomm_Ahamave/...` removed khỏi `requirejs-config.js` (DEC-8) — cả 2 theme.
- [ ] **AC-011** (Performance): cascade không N+1 (cache endpoint). ⛔ **DEFERRED Q1** (resolver hardening, Tier 2 SA).
- [ ] **AC-012** (Default-checkout — Luma path): Luma `checkout_index_index.xml` (Knockout) active trên Luma default-checkout; **inert** trên Hyva/OSC (project này).
- [ ] **AC-013** (Module-enable gating): `ifconfig="address/general/enable"` — khi disable, cả 2 theme dùng default Magento form (không VN dropdown).

_(OSC AC → TASK-FMAN1B.)_

---

## Technical Notes

> DEC-7 (Hyva-native) + DEC-8 (boundary) + DEC-9 (dual-theme). Module layer, theme-agnostic.

**1. Customer address form (AC-001, AC-013)** — dual template trong module:
- `templates/address/edit.phtml` — Luma (jQuery + `text/x-magento-init` + `directoryAddressDropdownUpdater` widget via `address-dropdown.js`; i18n `__()` keys).
- `templates/hyva/address/edit.phtml` — Hyva (Alpine `initCustomerAddressEdit()` + `fetch('/graphql')` cascade; Tailwind; i18n `__()` keys).
- Routing: `customer_address_form.xml` (Luma) `setTemplate → edit.phtml` + CSS head; `hyva_customer_address_form.xml` (Hyva) `setTemplate → hyva/address/edit.phtml` — cả hai `ifconfig`. Hyva handle auto-added → wins.
- Block = core `Magento\Customer\Block\Address\Edit` (no preference, no getTemplate override — layout controls). Restore source: lsoul sibling / dangling blobs (module untracked in git).

**2. Cart estimation (AC-005)**:
- Luma: `checkout_cart_index.xml` (Knockout jsLayout) + `cart/shipping-estimation-mixin.js` (restored from lsoul).
- Hyva: native `Magento_Checkout::php-cart/shipping.phtml` (region-based; VN regions in core `directory_country_region`). No module template.

**3. Data layer (tái dùng)**:
- GraphQL `GetListCity`/`GetListSubCity` (Hyva form) + customer-data `city-data` section (Luma form widget) + core Magento GraphQL (Country/Region).
- ⚠️ Resolvers `@deprecated` + `@cache(false)` → Q1 (AC-011).

**4. Cleanup (AC-006, AC-010)**:
- Ahamave ref removed từ requirejs (DEC-8).
- Luma files restored (address-dropdown.js, cart mixin, requirejs map) — active trên Luma, inert trên Hyva.

**5. i18n (AC-007)**: cả 2 template dùng generic `__()` keys; `Secomm_VietNamAddress` dict dịch (xem spec riêng/i18n plan). Generic module CSV không define storefront label keys (no conflict).

**Affected files:**
- `view/frontend/templates/address/edit.phtml` (Luma) + `hyva/address/edit.phtml` (Hyva).
- `view/frontend/layout/customer_address_form.xml` + `hyva_customer_address_form.xml` + `checkout_cart_index.xml` + `checkout_index_index.xml`.
- `view/frontend/requirejs-config.js` (Luma map + mixins; no Ahamave).
- `view/frontend/web/js/address-dropdown.js` + `cart/shipping-estimation-mixin.js` (Luma, restored).
- i18n: `Secomm_VietNamAddress/i18n/{vi,en}*.csv` + generic `Secomm_AddressDropdown/i18n/en_US.csv` (admin only).
- **KHÔNG affect**: `view/adminhtml/*`, `Api/`/`Model`/`Setup`/import.
- **Removed** (DEC-9): `Block/Customer/Address/Edit.php` + `Helper/Theme.php` + di.xml preference (no PHP theme detection).

---

## Dependencies

| Dependency | Type | Status | Notes |
|------------|------|--------|-------|
| `Secomm_AddressDropdown` GraphQL (City/SubCity) | data | sẵn có (deprecated) | Q1 cache/undeprecate (AC-011) |
| Core Magento GraphQL (Country/Region) + `directory_country_region` | data | available | Hyva cart + both forms region |
| `Secomm_VietNamAddress` (VN data 3 cấp + i18n) | data | installed | data + VN labels dict |
| Luma restore source (lsoul sibling / dangling blobs) | infra | available | module untracked in git |
| Mageplaza OSC | — | NOT a dependency | → TASK-FMAN1B |

---

## Risks & Unknowns

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Luma path untestable trên Hyva-only project | M | M | QC trên Luma theme/client riêng |
| `hyva_` handle load-order (hyva_ wins over base) | L | M | Verified reasoning; runtime confirm QC |
| Restore từ lsoul (có thể remove) | M | L | Backup: dangling blobs (`git cat-file -p <sha>`) |
| N+1 / deprecated resolver | M | M | Q1 (deferred, Tier 2) |
| Default-checkout JS VN-hardcoding (Luma) | L | L | Follow-up align i18n |
| Tier 2 (customer data) | H | H | TL review + QC L3 cả 2 theme |

---

## Out of Scope

- **Mageplaza OSC integration** → TASK-FMAN1B (Launchpad).
- Admin migration; backend `Api`/`Model`/`Setup`/import.
- `Secomm_VietNamAddress` data set internals.
- Default-checkout Luma JS VN-hardcoding align (follow-up).
- Multi-store scope (BR-TBD-001).

---

## Test Notes

- **Hyva theme** (`Secomm/launchpad`): customer form → `hyva/address/edit.phtml` (Alpine); cart → native php-cart; no Luma JS leak.
- **Luma theme** (switch store to Luma-based theme, hoặc test trên Luma client): customer form → `edit.phtml` (jQuery cascade); cart → Knockout mixin; default checkout → Luma cascade.
- Cả 2: cascade country→region→city (phường/xã), sub_city ẩn; i18n vi/en (Province/City/Ward/Commune / Tỉnh/Thành phố/Phường/Xã); save persist.
- Regression: admin CRUD/import (AC-009).
- Static: `bin/magento setup:di:compile` + `setup:static-content:deploy en_US vi_VN`.

---

## Open Questions

1. **Q1 — Resolver cache/undeprecate** (AC-011): SA quyết (Tier 2 GraphQL). ⛔ Deferred.
2. **Multi-store (BR-TBD-001)**: theme scope `launchpad` / `launchpad_fashion`? — stakeholder.

_(OSC seam → TASK-FMAN1B.)_

---

<!-- Cross-ref: 02_BUSINESS_RULES.md (BR-001/002), 03_ARCHITECTURE_AND_INTEGRATIONS.md, 10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md, DEC-7 (Strategy B, refined), DEC-8 (module/Launchpad boundary), DEC-9 (dual-theme), TASK-FMAN1B (OSC) -->
