---
id: FEAT-JKZM68
type: feature
project_code: SLP
parent: null
legacy_ids: [FEAT-008]
title: 'Promotion Max Discount — per-rule Maximum Discount Cap cho Cart Price Rule (by_percent)'
mode: A                      # pricing (quote totals) + order lifecycle → Tier-2 generic risk
specification_level: FULL
spec_status: VALID           # approved 2026-08-19 (user acting as TL) — DEC-FEATJKZM68-001 accepted
specification_ref: ../../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md
risk: high
status: proposed
created: 2026-08-19
updated: 2026-08-19
ticket_ref:
  - TASK-3R6X8E                   # scaffold Secomm_Promotion + PromotionMaxDiscount (Mode C)
  - TASK-33J3RP                   # db_schema column + extension attribute mapping (Mode B, Tier-2 DB)
  - TASK-5H8WKE                   # cap engine: collector 310 + LRM + unit tests (Mode A core)
  - TASK-67GGPR                   # admin UI: ValueProvider plugin + JS field + i18n (Mode B)
  - TASK-4HYX6Y                   # integration tests + idempotency + lifecycle (Mode A)
  - TASK-HPK1WZ                   # QC matrix + OSC e2e + context docs (Mode B)
decisions:
  - DEC-FEATJKZM68-001          # hook collector 310, data model, LRM redistribution, module boundary (accepted)
decision_assessment: material
decision_refs: [DEC-FEATJKZM68-001]
decision_approval_summary:
  total: 1
  pending_approval: []
  approved: [DEC-FEATJKZM68-001]
  rejected: []
  superseded: []
  last_synced: 2026-08-19
verified_against_commit: d871a85f
components:
  - CMP-PROMOTION               # Secomm_Promotion — base/foundation tối giản (anchor nhóm Promotion)
  - CMP-PROMOTIONMAXDISCOUNT    # Secomm_PromotionMaxDiscount — feature module độc lập
source_areas:
  - app/code/Secomm/Promotion/                    # NEW — scaffold tối giản, không business logic
  - app/code/Secomm/PromotionMaxDiscount/         # NEW — cap engine + admin UI + tests
  - vendor/magento/module-salesrule/Model/Quote/Discount.php           # inspected — hook-point evidence, KHÔNG modify
  - vendor/magento/module-sales-rule/Model/RulesApplier.php            # inspected — per-rule breakdown, KHÔNG modify
changes_project_state: true
changes_architecture: true    # thêm nhóm module Promotion mới (base + feature đầu tiên)
changes_integration: false
changes_known_limitations: true   # đóng gap "không cap được tổng discount của rule % "
last_verified: 2026-08-19
supersedes: []
---

# [SLP][FEAT-JKZM68] Promotion Max Discount — per-rule Maximum Discount Cap cho Cart Price Rule (by_percent)

<!-- CANONICAL RECORD — Full analysis/spec: SPEC-FEAT-JKZM68. Backend-first, theme-agnostic,
     Launchpad Core. Magento SalesRule vẫn là execution engine — feature chỉ cap + redistribute. -->

## Context

Merchant cần promotion **"Giảm X%, tối đa Y VND"** (`by_percent` + max monetary cap). Magento native không hỗ trợ (verified trên 2.4.8-p5: `ByPercent::calculate()` chỉ `min(100, percent)`; bảng `salesrule` không có field cap). Self-build đã quyết định — `Secomm_Promotion` là foundation cho nhóm mechanic promotion của Launchpad.

**Phân tíchMagento flow đã hoàn tất trước spec** (yêu cầu brief): collector order `discount(300) → shipping(350) → tax(450)`; per-rule per-item breakdown tồn tại native qua `item.extension_attributes.discounts[]` (`setDiscountBreakdown`); invoice/creditmemo là pure allocation từ order item persisted. Chi tiết file:line trong [SPEC-FEAT-JKZM68 §1, §5, §12](../../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md).

**Risk:** Tier-2 (quote totals pricing + order/invoice/creditmemo lifecycle) → Mode A, TL/SA review bắt buộc.

## Architecture (tóm tắt — canonical trong spec)

```
Magento native SalesRule Discount collector (300)  — KHÔNG đổi, KHÔNG modify core
        ↓ đọc per-rule per-item breakdown
Secomm_PromotionMaxDiscount collector (310)        — cap per-rule (by_percent + cap>0)
        ↓ factor × native contribution + largest-remainder redistribution
Collectors sau (shipping 350 / tax 450 / grand 550) thấy số đã cap → totals đúng
        ↓ native copy fieldset to_order_item_discount
Order item (capped) → Invoice / Creditmemo = native pure allocation (không re-run rule)
```

- **Hook:** custom quote total collector sort 310 qua `etc/sales.xml` — config-only, idempotent nhờ native reset mỗi collect pass.
- **Redistribution:** theo native discount **contribution** (không theo row_total), largest remainder, 2 chuỗi độc lập base/display, `PriceCurrencyInterface::round()` theo currency precision (không hard-code VND integer).
- **Data:** column `maximum_discount_amount` DECIMAL(12,4) NULL trên bảng `salesrule` (declarative schema; NULL/0 = unlimited).
- **Admin:** field `Maximum Discount Amount` trong Actions tab qua plugin sau `Metadata\ValueProvider::getMetadataValues` (không override `sales_rule_form.xml`) + JS switch theo `simple_action`; vi_VN + en_US.

## Module boundary

- `Secomm_Promotion` — base **tối giản** (module.xml/registration/README/CHANGELOG, không code): anchor nhóm Promotion; không over-engineer framework cho feature chưa tồn tại. Shared contracts chỉ rút lên khi có module thứ hai.
- `Secomm_PromotionMaxDiscount` — toàn bộ business logic cap; dependency đúng: `Secomm_Promotion` + `Magento_SalesRule`.

## Requirements (feature-level; AC chi tiết AC-001..015 trong spec §15)

- Cap = **tổng product discount contribution** của một rule trên eligible items; per-rule độc lập; `NULL/0` = native; chỉ `by_percent`; shipping benefit ngoài cap; không viết lại eligibility logic của Magento.
- Invariant: `Σ final eligible item discounts == min(native Σ, cap)` — base + display, mọi currency precision, sau LRM rounding.
- Idempotency: deterministic function của quote state; repeat `collectTotals()` identical; không accumulate/session state.
- Lifecycle: capped discount persist xuống order item; invoice/creditmemo native allocation — `Σ invoiced/refunded ≤ discount persisted`.
- Compat: không modify core, theme/checkout-agnostic, automatic + coupon, không đổi native behavior khi cap off.

## Sub-ticket breakdown (spec VALID 2026-08-19 → decomposed)

- **[TASK-3R6X8E](../../tickets/TASK-3R6X8E-scaffold-promotion-modules.md)** — scaffold `Secomm_Promotion` (base tối giản) + `Secomm_PromotionMaxDiscount` (skeleton). → `proposed` (Mode C).
- **[TASK-33J3RP](../../tickets/TASK-33J3RP-maxdiscount-db-schema.md)** — db_schema column `maximum_discount_amount` + whitelist + extension attribute cho `RuleInterface` + RuleRepository mapping. → `proposed` (Mode B, **Tier-2 DB schema**).
- **[TASK-5H8WKE](../../tickets/TASK-5H8WKE-maxdiscount-cap-engine.md)** — cap engine: collector 310 + CapResolver + LRM allocator + unit tests. → `proposed` (Mode A, Tier-2 pricing — core).
- **[TASK-67GGPR](../../tickets/TASK-67GGPR-maxdiscount-admin-ui.md)** — admin UI: ValueProvider plugin + JS switch field + i18n vi/en. → `proposed` (Mode B).
- **[TASK-4HYX6Y](../../tickets/TASK-4HYX6Y-maxdiscount-integration-tests.md)** — integration tests (quote matrix A–D + lifecycle E) + idempotency. → `proposed` (Mode A, Tier-2 order lifecycle).
- **[TASK-HPK1WZ](../../tickets/TASK-HPK1WZ-maxdiscount-qc-docs.md)** — QC matrix + OSC e2e + project-context docs. → `proposed` (Mode B).

Thứ tự thực thi: TASK-3R6X8E → TASK-33J3RP → TASK-5H8WKE → (TASK-67GGPR ∥ TASK-4HYX6Y) → TASK-HPK1WZ.

## Resolved decisions (2026-08-19, user acting as TL — chat "Approved")

- **D1 — hook:** custom collector sort 310 (đã loại 3 hướng thay thế).
- **D2 — API exposure:** extension attribute `RuleInterface` **Phase 1 include** (chống REST-save bỏ field).
- **D3 — multishipping:** quote-level per-rule cap.
- → [DEC-FEATJKZM68-001](../decisions/DEC-FEATJKZM68-001.md) (accepted).

## Risks

- Tier-2 pricing + order lifecycle: TL/SA review spec trước khi VALID; QC e2e OSC (repeat collectTotals của OSC phải idempotent).
- Column trên bảng core `salesrule`: declarative schema chuẩn nhưng cần whitelist + không migration thủ công.
- minFix divergence (Σ breakdown > item discount): chỉ scale-down — guard trong thuật toán + unit test.
- Module 3rd party khác mutate discount sau collector 300: theo dõi khi cài extension mới.

## References

- Spec: [SPEC-FEAT-JKZM68](../../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md) (18-mục analysis: native behavior, hook-point, data model, redistribution, rounding, tax, shipping, idempotency, lifecycle, edge cases, compat, AC, test strategy, plan, estimate)
- Source evidence: `vendor/magento/module-sales-rule/Model/{Quote/Discount,RulesApplier,Utility,Validator,Rule/Metadata/ValueProvider,Rule/DataProvider}.php` · `vendor/magento/module-sales/Model/Order/{Invoice,Creditmemo}/Total/Discount.php` · `etc/sales.xml` (module-quote/sales-rule/tax)
- Risk tier: AGENTS.md §9/§12 · Toolkit v4.0
