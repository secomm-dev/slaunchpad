---
id: FEAT-E2HM1J
type: feature
project_code: SLP
parent: null
legacy_ids: [FEAT-007]
title: 'Admin VN 2-level address dropdown (global mechanism + VietNamAddress adapter)'
mode: A                      # spans customer/PII + order (Tier-2) across sub-tickets
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-4ZV5NG-admin-vn-address-customer-form.md
risk: high
status: proposed
created: 2026-08-03
updated: 2026-08-03
ticket_ref:
  - TASK-4ZV5NG                   # admin customer-address form (verify + migrate VN logic → VietNamAddress)
  - TASK-SQY42T                   # admin order forms (create + edit order address) — Tier-2 order
  - TASK-8WSERX                   # admin Store Information + Shipping Origin config (merged)
  - TASK-2V0AEV                   # admin MSI Source form (inventory/source_form) — MSI confirmed used
decisions:
  - DEC-8                    # reusable-module vs project boundary (accepted)
  - DEC-FEATJSZQV3-001                  # generic VN address capability lives in Secomm_VietNamAddress (accepted)
  - DEC-FEATJSZQV3-003                  # generic uses Magento-default labels; VN behavior/labels → VietNamAddress (accepted)
  - DEC-TASKYJENM2-001                  # 2-level VN: ward = city level (directory_region_city.city_id) (accepted)
  - DEC-FEATE2HM1J-001                  # admin architecture: global data-driven mechanism + country adapter; sub_city generic 3rd level (accepted)
decision_assessment: material
decision_refs: [DEC-8, DEC-FEATJSZQV3-001, DEC-FEATJSZQV3-003, DEC-TASKYJENM2-001, DEC-FEATE2HM1J-001]
decision_approval_summary:
  total: 5
  pending_approval: []
  approved: [DEC-8, DEC-FEATJSZQV3-001, DEC-FEATJSZQV3-003, DEC-TASKYJENM2-001, DEC-FEATE2HM1J-001]
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
changes_architecture: true   # VN adapter extended from frontend-only to admin surfaces (DEC-FEATE2HM1J-001)
changes_integration: false
changes_known_limitations: true   # admin VN address capture gap closed
last_verified: 2026-08-03
supersedes: []
---

# [SLP][FEAT-E2HM1J] Admin VN 2-level address dropdown (global mechanism + VietNamAddress adapter)

<!-- CANONICAL RECORD — multi-surface admin capability; shared architecture (DEC-FEATE2HM1J-001); decomposes into TASK-4ZV5NG…TASK-2V0AEV. -->
<!-- Mirrors frontend cart pattern (TASK-FD6A9X / FEAT-JSZQV3): VietNamAddress owns VN behaviour, reuses generic -->
<!-- AddressDropdown data; ward persisted as native Magento `city`; server-side validation. -->

## Context

VN address dropdown đã cover **storefront** (FEAT-YVN39K) + **cart estimate** (FEAT-JSZQV3/TASK-FD6A9X qua `Secomm_VietNamAddress`). Admin address forms — customer address, sales order address, Store Information + Shipping Origin, MSI Source — **chưa có** ward dropdown: `city` vẫn là text input → admin nhập tay sai/thiếu ward.

FEAT này apply **architecture đã pin ở DEC-FEATE2HM1J-001** lên mọi admin address surface.

**Risk:** TASK-4ZV5NG/TASK-SQY42T Tier-2 (customer/PII, order) → Mode A, escalate TL. TASK-8WSERX/TASK-2V0AEV Tier-1.

## Architecture (DEC-FEATE2HM1J-001 — single design)

Tách **global mechanism** vs **country adapter** (DEC-FEATJSZQV3-001/019):

### `Secomm_AddressDropdown` — GENERIC, country-agnostic, data-driven multi-level
- **Data + collections**: `directory_region_city` (city/ward level), `CityLocaleCollection`/`CityCollection`, `CityRepository`, GraphQL `GetListCity(region_id)`, option/source models (`Selector\City`, `Source\City`).
- **Mechanism**: cascade `region → city → sub_city` generic; **render đúng số level mà data country đó có** ("mấy tầng sẽ là mấy tầng"); helper inject dropdown; persist vào **store riêng của form**.
- **KHÔNG** chứa `country == 'VN'`, label VN, hay behaviour country-specific (DEC-FEATJSZQV3-003). Form thiếu `city` → mechanism thêm field.

### `Secomm_VietNamAddress` — country adapter
- **`country == 'VN'`**: `city` = **ward** (`directory_region_city`, **2-level** DEC-TASKYJENM2-001); ward options theo `region_id` (reuse data AddressDropdown — không duplicate, DEC-FEATJSZQV3-003); ward **persist vào native `city`**; server-side validate ward ∈ province (mirror `ValidateVietNamWard`).
- **Non-VN** → generic mechanism (native). Label ward = "Phường/Xã" (i18n VietNamAddress).
- VN = 2 cấp → adapter **không render `sub_city`** cho VN.

## Level model & sub_city (DEC-FEATE2HM1J-001 — KHÔNG deprecate)

- Cascade **data-driven theo country**. VN data = 2 cấp (region + city/ward) → render 2 level.
- **`sub_city` = generic 3rd level**, KHÔNG deprecate. Country non-VN có data 3 cấp → `sub_city` hiện (generic). VN không dùng → adapter không render cho VN. Field/infra `sub_city` giữ.
- Ward persist vào native `city` (giống cart TASK-FD6A9X). (`ward_id` canonical = path A dài hạn DEC-TASKYJENM2-001 — ngoài scope.)

## Data stores (per surface)

| Surface (ticket) | Persist ward vào | Notes |
|---|---|---|
| Customer address (TASK-4ZV5NG) | `customer_address_entity.city` | modal Add + Edit; migrate VN logic AddressDropdown→VietNamAddress |
| Sales order address (TASK-SQY42T) | `sales_order_address.city` (+ `quote_address` parity) | order create billing/shipping + edit; "same as billing" copy |
| Store Information **+ Shipping Origin** (TASK-8WSERX) | `core_config_data` (`general/store_information` + `shipping/origin`) | PDF/print origin + carrier/TableRate origin |
| MSI Source (TASK-2V0AEV) | `inventory_source` (source address) | MSI confirmed used |

## Requirements (feature-level; AC chi tiết trong sub-ticket)

- **AC-001:** mỗi admin surface — `country==VN` → native `city` thành ward dropdown (options theo region, reuse AddressDropdown data); ward persist vào native `city` của store form đó.
- **AC-002:** non-VN → generic mechanism (native `city` text, + `sub_city` nếu country có data 3 cấp); generic không leak VN (DEC-FEATJSZQV3-003).
- **AC-003:** server-side validate ward ∈ province cho surface có backend save (mirror `ValidateVietNamWard`); invalid → neutralise + log, không crash.
- **AC-004:** form thiếu `city` → generic mechanism thêm field trước khi adapter gắn ward data.
- **AC-005:** regression — storefront (customer form, cart estimate, OSC), admin data CRUD, existing save flows nguyên vẹn.
- **AC-006:** DEC-FEATE2HM1J-001 compliance (VN logic chỉ ở VietNamAddress; data reuse không duplicate; multi-level data-driven; sub_city generic).

## Sub-ticket breakdown + readiness

- **[TASK-4ZV5NG](../../tickets/TASK-4ZV5NG-apply-address-dropdown-admin-customer-form.md)** — admin customer-address: verify + **migrate VN logic AddressDropdown→VietNamAddress** (DEC-FEATJSZQV3-003 violation hiện tại); `sub_city` giữ generic. → `proposed` (Mode A, Tier-2 PII).
- **[TASK-SQY42T](../../tickets/TASK-SQY42T-apply-address-dropdown-admin-order-form.md)** — admin order create + address edit: apply ward dropdown (block-form `Magento_Sales`, approach TBD mini-spec). → `proposed` (Mode A, Tier-2 order+PII). Surface mới lớn nhất.
- **[TASK-8WSERX](../../tickets/TASK-8WSERX-apply-address-dropdown-admin-store-information.md)** — Store Information **+ Shipping Origin** config (`general/store_information` + `shipping/origin`, system.xml — merged). → `proposed` (Mode B, Tier-1).
- **[TASK-2V0AEV](../../tickets/TASK-2V0AEV-apply-address-dropdown-admin-msi-source.md)** — MSI Source form (`inventory/source_form`). → `proposed` (Mode B, Tier-1) — **MSI confirmed used**.

## Resolved decisions (2026-08-03, user)

- **D1 — sub_city:** KHÔNG deprecate; generic 3rd level; VN 2-level không dùng, non-VN 3 cấp dùng. → DEC-FEATE2HM1J-001.
- **D2 — Shipping Origin:** gộp vào TASK-8WSERX (cùng `general/store_information` + `shipping/origin`).
- **D3 — MSI used:** yes → TASK-2V0AEV hợp lệ.
- **D4 — DEC-FEATE2HM1J-001:** created (pinning architecture).

## Risks

- Tier-2 customer/PII (TASK-4ZV5NG) + order (TASK-SQY42T): QC end-to-end + TL review.
- Admin form **mechanics khác nhau** (ui_component / block-form `Magento_Sales` / system.xml config / MSI ui_component) → approach injection khác, mini-spec mỗi surface.
- TASK-SQY42T order create: "same as billing" copy phải truyền ward; quote↔order parity.
- Server-side validation parity (mỗi surface save path riêng).
- TASK-4ZV5NG migrate AddressDropdown→VietNamAddress: không break storefront (align TASK-KCBDDT).

## References

- Sub-tickets: [TASK-4ZV5NG](../../tickets/TASK-4ZV5NG-apply-address-dropdown-admin-customer-form.md) · [TASK-SQY42T](../../tickets/TASK-SQY42T-apply-address-dropdown-admin-order-form.md) · [TASK-8WSERX](../../tickets/TASK-8WSERX-apply-address-dropdown-admin-store-information.md) · [TASK-2V0AEV](../../tickets/TASK-2V0AEV-apply-address-dropdown-admin-msi-source.md)
- Architecture: [DEC-FEATE2HM1J-001](../decisions/DEC-FEATE2HM1J-001.md)
- Precedent (frontend cart): [TASK-FD6A9X](../../tickets/TASK-FD6A9X-hyva-cart-estimate-city-cascade.md) · [FEAT-JSZQV3](FEAT-JSZQV3.md) · [`ValidateVietNamWard.php`](../../app/code/Secomm/VietNamAddress/Plugin/Cart/ValidateVietNamWard.php) · [`shipping.phtml`](../../app/code/Secomm/VietNamAddress/view/frontend/templates/hyva/php-cart/shipping.phtml)
- Related: [FEAT-YVN39K](FEAT-YVN39K.md) · [TASK-KCBDDT](../../tickets/TASK-KCBDDT-generalize-addressdropdown-remove-vn-leak.md) · [FEAT-AE761Z](FEAT-AE761Z.md) GHTK
- Decisions: [DEC-8](../decisions/DEC-008.md) · [DEC-17](../decisions/DEC-FEATJSZQV3-001.md) · [DEC-19](../decisions/DEC-FEATJSZQV3-003.md) · [DEC-20](../decisions/DEC-TASKYJENM2-001.md) · [DEC-25](../decisions/DEC-FEATE2HM1J-001.md)
- Risk tier: AGENTS.md §9 (customer/PII L398, order L397, AddressDropdown L421)
- Toolkit version: v4.0
