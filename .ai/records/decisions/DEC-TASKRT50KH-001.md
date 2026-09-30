---
id: DEC-TASKRT50KH-001
title: 'Product shipping dimension contract P1 — reuse length/width/height (delete+recreate decimal/GLOBAL, labels "(cm)"), completeness-only-authoritative + ceil int cm, reader ở Secomm_Base, dims chỉ cho GHN hard-limit gate (fee payload vẫn omit)'
status: accepted             # TL directive 2026-09-23 + user approval Option A (AskUserQuestion) — Tier-2 review trước merge
owners: [tl, sa]
decision_type: architecture
approval_date: 2026-09-23
created: 2026-09-23
last_verified: 2026-09-23
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-RT50KH, FEAT-FQWEQ3]
---

# Decision Record: Product Shipping Dimensions Contract + GHN Checkout Dimension Pre-Validation

## Status

Accepted (2026-09-23 — TL directive 21 section; user approval Option A qua AskUserQuestion).
Tier-2 (CTO/SA) review trước merge. Bổ sung 1 correction của TASK-MQ2DRG: 150cm alias.

## Decision Type

Architecture (product data contract + carrier pre-validation activation — KHÔNG tạo version mới,
KHÔNG đổi ShippingCore runtime)

## Decisions

1. **Reuse qua delete+recreate (Option A)** — audit DB: `length/width/height` (144/145/146)
   varchar/STORE/merchandising-flags, **0/176 products có giá trị** (empty shells) → xoá +
   tạo lại cùng codes: decimal, GLOBAL, labels "Shipping ... (cm)", frontend_class
   `validate-number validate-zero-or-greater`, merchandising flags false. Migration patch
   add-only (`getDependencies: [AddDimensionProductAttribute]`); FK cascade dọn set rows;
   EavSetup group propagation tái phân bổ 9 sets. Consumers (Base plugin float-cast, Ahamove
   volumetric) decimal-compatible — verified.

2. **Completeness-only-authoritative + ceil** — dims chỉ authoritative khi CẢ 3 present +
   numeric + > 0 → `ceil()` từng giá trị về int cm (conservative tại 150: 149.2 → 150 pass,
   150.1 → 151 reject); mọi case khác → null = missing (không rejection, không substitute
   0/1/default). Lý do ceil: eligibility check phải bảo thủ theo hướng từ chối.

3. **Reader ownership ở Secomm_Base** (product-data owner; sequence += Magento_Quote):
   `Api\ShippingDimensionsReaderInterface::read(Quote\Item): ?Api\Data\ShippingDimensions` +
   `Model\Shipping\ProductShippingDimensionsReader`. Composite resolution trong reader:
   configurable ship-together → selected child (`getChildren()[0]`); bundle ship-together →
   parent dims (merchant khai báo cấp bundle); ship-separately → child product; simple/grouped
   → own; virtual/downloadable → estimator skip sẵn (downloadable = `Product\Type\Virtual`
   subtype — verified); product null/children rỗng → null. Ghn sequence += Secomm_Base.

4. **Dims CHỈ cho hard-limit gate** — estimator fill `EstimatedPackage` dims (cùng giá trị
   mọi unit — KHÔNG nhân với qty); `findHardLimitViolation()` (150cm = `RATE_MAX_SIDE_CM`,
   SANDBOX_OBSERVED) live; fee payload GIỮ omit dims (TASK-WAWNDS: dims làm type-2 fee sai
   lệch — test-locked). Hard violation → `GHN_PACKAGE_{L|W|H}_LIMIT_EXCEEDED` (GHN-owned,
   UNAVAILABLE, không fallback mọi mode); missing dims → không rejection.

5. **150 de-dup correction** — `GhnPackageLimits::MAX_DIMENSION_CM` → alias
   `GhnShipmentConstraints::RATE_MAX_SIDE_CM` (TASK-MQ2DRG silent replace failure; grep
   invariant lúc đó chỉ check 20000/50000, miss 150 — ghi nhận làm lesson).

## Consequences

- Products không có dims tiếp tục quote GHN theo weight logic — 0 checkout regression.
- Merchant điền dims → unit vượt 150cm bị chặn tại checkout (không đến CREATE mới fail).
- `ShipmentPhysicalData` vẫn authoritative tại shipment creation; checkout dims chỉ là
  pre-validation data.
- Fresh install: original patch (varchar) → migration patch (delete+recreate decimal) —
  nhất quán; không sửa patch history.
