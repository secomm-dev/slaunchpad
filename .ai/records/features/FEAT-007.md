---
id: FEAT-007
title: 'Admin VN 2-level address dropdown (global mechanism + VietNamAddress adapter)'
mode: A                      # spans customer/PII + order (Tier-2) across sub-tickets
risk: high
status: proposed
created: 2026-08-03
updated: 2026-08-03
ticket_ref:
  - SL-011                   # admin customer-address form (verify + migrate VN logic → VietNamAddress)
  - SL-012                   # admin order forms (create + edit order address) — Tier-2 order
  - SL-013                   # admin Store Information + Shipping Origin config (merged)
  - SL-014                   # admin MSI Source form (inventory/source_form) — MSI confirmed used
decisions:
  - DEC-8                    # reusable-module vs project boundary (accepted)
  - DEC-017                  # generic VN address capability lives in Secomm_VietNamAddress (accepted)
  - DEC-019                  # generic uses Magento-default labels; VN behavior/labels → VietNamAddress (accepted)
  - DEC-020                  # 2-level VN: ward = city level (directory_region_city.city_id) (accepted)
  - DEC-025                  # admin architecture: global data-driven mechanism + country adapter; sub_city generic 3rd level (accepted)
decision_assessment: material
decision_refs: [DEC-8, DEC-017, DEC-019, DEC-020, DEC-025]
decision_approval_summary:
  total: 5
  pending_approval: []
  approved: [DEC-8, DEC-017, DEC-019, DEC-020, DEC-025]
  rejected: []
  superseded: []
  last_synced: 2026-08-03
verified_against_commit:
components:
  - CMP-ADDR                 # Secomm_AddressDropdown — generic data + mechanism (country-agnostic, data-driven multi-level)
  - CMP-VNADDR               # Secomm_VietNamAddress — VN adapter (country==VN behaviour; 2-level)
source_areas:
  - app/code/Secomm/AddressDropdown/                                              # generic data + mechanism (reuse/extend — no VN logic)
  - app/code/Secomm/VietNamAddress/                                               # VN adapter per admin surface (NEW — mirror frontend cart)
  - app/code/Secomm/VietNamAddress/Plugin/Cart/ValidateVietNamWard.php            # precedent: server-side VN ward validation
  - app/code/Secomm/VietNamAddress/view/frontend/templates/hyva/php-cart/shipping.phtml  # precedent: VN ward dropdown gated on country==VN
changes_project_state: true
changes_architecture: true   # VN adapter extended from frontend-only to admin surfaces (DEC-025)
changes_integration: false
changes_known_limitations: true   # admin VN address capture gap closed
last_verified: 2026-08-03
supersedes: []
---

# Feature Record: Admin VN 2-level address dropdown (global mechanism + VietNamAddress adapter)

<!-- CANONICAL RECORD — multi-surface admin capability; shared architecture (DEC-025); decomposes into SL-011..014. -->
<!-- Mirrors frontend cart pattern (SL-003 / FEAT-005): VietNamAddress owns VN behaviour, reuses generic -->
<!-- AddressDropdown data; ward persisted as native Magento `city`; server-side validation. -->

## Context

VN address dropdown đã cover **storefront** (FEAT-001) + **cart estimate** (FEAT-005/SL-003 qua `Secomm_VietNamAddress`). Admin address forms — customer address, sales order address, Store Information + Shipping Origin, MSI Source — **chưa có** ward dropdown: `city` vẫn là text input → admin nhập tay sai/thiếu ward.

FEAT này apply **architecture đã pin ở DEC-025** lên mọi admin address surface.

**Risk:** SL-011/SL-012 Tier-2 (customer/PII, order) → Mode A, escalate TL. SL-013/SL-014 Tier-1.

## Architecture (DEC-025 — single design)

Tách **global mechanism** vs **country adapter** (DEC-017/019):

### `Secomm_AddressDropdown` — GENERIC, country-agnostic, data-driven multi-level
- **Data + collections**: `directory_region_city` (city/ward level), `CityLocaleCollection`/`CityCollection`, `CityRepository`, GraphQL `GetListCity(region_id)`, option/source models (`Selector\City`, `Source\City`).
- **Mechanism**: cascade `region → city → sub_city` generic; **render đúng số level mà data country đó có** ("mấy tầng sẽ là mấy tầng"); helper inject dropdown; persist vào **store riêng của form**.
- **KHÔNG** chứa `country == 'VN'`, label VN, hay behaviour country-specific (DEC-019). Form thiếu `city` → mechanism thêm field.

### `Secomm_VietNamAddress` — country adapter
- **`country == 'VN'`**: `city` = **ward** (`directory_region_city`, **2-level** DEC-020); ward options theo `region_id` (reuse data AddressDropdown — không duplicate, DEC-019); ward **persist vào native `city`**; server-side validate ward ∈ province (mirror `ValidateVietNamWard`).
- **Non-VN** → generic mechanism (native). Label ward = "Phường/Xã" (i18n VietNamAddress).
- VN = 2 cấp → adapter **không render `sub_city`** cho VN.

## Level model & sub_city (DEC-025 — KHÔNG deprecate)

- Cascade **data-driven theo country**. VN data = 2 cấp (region + city/ward) → render 2 level.
- **`sub_city` = generic 3rd level**, KHÔNG deprecate. Country non-VN có data 3 cấp → `sub_city` hiện (generic). VN không dùng → adapter không render cho VN. Field/infra `sub_city` giữ.
- Ward persist vào native `city` (giống cart SL-003). (`ward_id` canonical = path A dài hạn DEC-020 — ngoài scope.)

## Data stores (per surface)

| Surface (ticket) | Persist ward vào | Notes |
|---|---|---|
| Customer address (SL-011) | `customer_address_entity.city` | modal Add + Edit; migrate VN logic AddressDropdown→VietNamAddress |
| Sales order address (SL-012) | `sales_order_address.city` (+ `quote_address` parity) | order create billing/shipping + edit; "same as billing" copy |
| Store Information **+ Shipping Origin** (SL-013) | `core_config_data` (`general/store_information` + `shipping/origin`) | PDF/print origin + carrier/TableRate origin |
| MSI Source (SL-014) | `inventory_source` (source address) | MSI confirmed used |

## Requirements (feature-level; AC chi tiết trong sub-ticket)

- **AC-001:** mỗi admin surface — `country==VN` → native `city` thành ward dropdown (options theo region, reuse AddressDropdown data); ward persist vào native `city` của store form đó.
- **AC-002:** non-VN → generic mechanism (native `city` text, + `sub_city` nếu country có data 3 cấp); generic không leak VN (DEC-019).
- **AC-003:** server-side validate ward ∈ province cho surface có backend save (mirror `ValidateVietNamWard`); invalid → neutralise + log, không crash.
- **AC-004:** form thiếu `city` → generic mechanism thêm field trước khi adapter gắn ward data.
- **AC-005:** regression — storefront (customer form, cart estimate, OSC), admin data CRUD, existing save flows nguyên vẹn.
- **AC-006:** DEC-025 compliance (VN logic chỉ ở VietNamAddress; data reuse không duplicate; multi-level data-driven; sub_city generic).

## Sub-ticket breakdown + readiness

- **[SL-011](../../tickets/SL-011-apply-address-dropdown-admin-customer-form.md)** — admin customer-address: verify + **migrate VN logic AddressDropdown→VietNamAddress** (DEC-019 violation hiện tại); `sub_city` giữ generic. → `proposed` (Mode A, Tier-2 PII).
- **[SL-012](../../tickets/SL-012-apply-address-dropdown-admin-order-form.md)** — admin order create + address edit: apply ward dropdown (block-form `Magento_Sales`, approach TBD mini-spec). → `proposed` (Mode A, Tier-2 order+PII). Surface mới lớn nhất.
- **[SL-013](../../tickets/SL-013-apply-address-dropdown-admin-store-information.md)** — Store Information **+ Shipping Origin** config (`general/store_information` + `shipping/origin`, system.xml — merged). → `proposed` (Mode B, Tier-1).
- **[SL-014](../../tickets/SL-014-apply-address-dropdown-admin-msi-source.md)** — MSI Source form (`inventory/source_form`). → `proposed` (Mode B, Tier-1) — **MSI confirmed used**.

## Resolved decisions (2026-08-03, user)

- **D1 — sub_city:** KHÔNG deprecate; generic 3rd level; VN 2-level không dùng, non-VN 3 cấp dùng. → DEC-025.
- **D2 — Shipping Origin:** gộp vào SL-013 (cùng `general/store_information` + `shipping/origin`).
- **D3 — MSI used:** yes → SL-014 hợp lệ.
- **D4 — DEC-025:** created (pinning architecture).

## Risks

- Tier-2 customer/PII (SL-011) + order (SL-012): QC end-to-end + TL review.
- Admin form **mechanics khác nhau** (ui_component / block-form `Magento_Sales` / system.xml config / MSI ui_component) → approach injection khác, mini-spec mỗi surface.
- SL-012 order create: "same as billing" copy phải truyền ward; quote↔order parity.
- Server-side validation parity (mỗi surface save path riêng).
- SL-011 migrate AddressDropdown→VietNamAddress: không break storefront (align SL-007).

## References

- Sub-tickets: [SL-011](../../tickets/SL-011-apply-address-dropdown-admin-customer-form.md) · [SL-012](../../tickets/SL-012-apply-address-dropdown-admin-order-form.md) · [SL-013](../../tickets/SL-013-apply-address-dropdown-admin-store-information.md) · [SL-014](../../tickets/SL-014-apply-address-dropdown-admin-msi-source.md)
- Architecture: [DEC-025](../decisions/DEC-025.md)
- Precedent (frontend cart): [SL-003](../../tickets/SL-003-hyva-cart-estimate-city-cascade.md) · [FEAT-005](FEAT-005.md) · [`ValidateVietNamWard.php`](../../app/code/Secomm/VietNamAddress/Plugin/Cart/ValidateVietNamWard.php) · [`shipping.phtml`](../../app/code/Secomm/VietNamAddress/view/frontend/templates/hyva/php-cart/shipping.phtml)
- Related: [FEAT-001](FEAT-001.md) · [SL-007](../../tickets/SL-007-generalize-addressdropdown-remove-vn-leak.md) · [FEAT-006](FEAT-006.md) GHTK
- Decisions: [DEC-8](../decisions/DEC-008.md) · [DEC-17](../decisions/DEC-017.md) · [DEC-19](../decisions/DEC-019.md) · [DEC-20](../decisions/DEC-020.md) · [DEC-25](../decisions/DEC-025.md)
- Risk tier: AGENTS.md §9 (customer/PII L398, order L397, AddressDropdown L421)
- Toolkit version: v4.0
