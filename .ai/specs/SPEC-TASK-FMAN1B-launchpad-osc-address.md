# Spec: Launchpad OSC Address Integration (TASK-FMAN1B)

Specification ID: SPEC-TASK-FMAN1B
Feature ID: NONE
Specification Level: FULL

> **Status:** Draft — backfilled 2026-08-18 từ ticket TASK-FMAN1B (spec TBD "chưa tạo") + DEC-7/DEC-8 + BR-002/BR-004. Work chưa deliver (contingent trên OSC seam research — giữ nguyên các TBD của ticket).
> **Mode A** · Tier 2 (Mageplaza OSC — §12) · Tickets: TASK-FMAN1B · Depends: TASK-88NDV5

# Purpose

Tích hợp hierarchical VN address dropdown (Hyvä-native, từ `Secomm_AddressDropdown`) vào **Mageplaza One Step Checkout** (shipping + billing address) trong **package Launchpad** (project layer). Merchant/customer ở VN checkout qua OSC với address cascade chuẩn BR-002 thay vì text input.

# Scope

- Theme override / project integration wire address component vào OSC shipping + billing form.
- Cascade hoạt động + set shipping/billing information qua cơ chế submit của OSC.

# Out of Scope

- Code coupling OSC trong module `Secomm_AddressDropdown` (DEC-8 boundary).
- Edit `app/code/Mageplaza/*` in-place (chỉ theme override/plugin/preference).
- FEAT-KQ6WC4 storefront proposal rộng hơn.

# Actors / Context

Customer (checkout VN) · Mageplaza OSC (Knockout-based + compat luma-checkout, dispatch event riêng) · Secomm_AddressDropdown (module tái dụng — GraphQL + data) · Launchpad theme layer.

# Business Rules

- BR-002: address fields = AJAX hierarchical dropdowns (country→province→ward, VN 2-level).
- BR-004: OSC thay default checkout; không được regress OSC features (ExtraFee BR-006, DeliveryTime, Mollie BR-003).
- BR-001: chuỗi mới song ngữ vi_VN + en_US.
- DEC-8: module giữ reusable — integration sống trong Launchpad.
- DEC-7 (Strategy B): address UI Hyvä-native (Alpine/Magewire/.phtml), không Knockout mới.

# System Behaviour

- OSC shipping + billing form render VN address dropdown Hyvä-native trong khu vực address của OSC; cascade load options qua data layer module (không duplicate master data).
- Chọn address → set shipping information qua OSC mechanism → totals + shipping methods (incl. Mageplaza TableRate) load đúng; billing persist đúng khi place order.
- Ward persist vào native `city` theo model Launchpad (2-level).

# Main Flows

1. Customer vào OSC → chọn country VN → cascade province → ward → submit shipping information.
2. Billing address tương tự; place order giữ nguyên dữ liệu.

# Edge Cases

Non-VN country trong OSC → form native OSC (không ép cascade) · đổi province liên tục → options không stale (request guard) · OSC re-render region → component không mất state.

# Contracts / Invariants

> Module `Secomm_AddressDropdown` không được coupling OSC; mọi integration nằm trong Launchpad. Mageplaza không bị edit in-place.

# Failure Behaviour

Cascade fail (GraphQL error) → form degrade không crash checkout; log masked.

# Acceptance Criteria

Theo [ticket TASK-FMAN1B](../tickets/TASK-FMAN1B-launchpad-osc-address-integration.md): AC-002 (shipping dropdown + methods load), AC-003 (billing persist), AC-004 (ExtraFee/DeliveryTime/Mollie không regress), AC-O1 (boundary module), AC-O2 (no in-place edit Mageplaza), AC-O3 (i18n ×2). QC end-to-end checkout bắt buộc (Tier 2).

# Related Decisions

DEC-7 (Strategy B full Hyvä-native) · DEC-8 (module/Launchpad boundary) · DEC-19/DEC-FEATE2HM1J-001 (generic/adapter boundary).
