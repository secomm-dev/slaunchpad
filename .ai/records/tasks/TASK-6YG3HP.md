---
id: TASK-6YG3HP
type: task
title: 'GHTK structural alignment lên ShippingCore v5 — per-operation capability + operation-specific handoff + shared COD resolver (address scheme PENDING probe)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: B
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec — user-directed structural task 2026-09-14; address scheme values KHÔNG freeze (TASK-44F7V7 BLOCKED_BY_CREDENTIAL)
specification_ref: Embedded Mini-Spec
risk: medium                  # structural migration trên carrier runtime; KCXKVR correctness được bảo vệ bằng regression tests
status: dev-complete          # implemented 2026-09-14 (scoped 578 tests — 0 failure trong scope; PARTIAL ALIGNMENT — ADDRESS SCHEME BLOCKED per TASK-44F7V7; evidence .ai/evidence/TASK-6YG3HP/); chờ Tier-2 review
priority: high
decision_assessment: none-material # thực thi v5 contracts hiện có (TASK-Y3X6H5/STC3NB); không mở architecture mới; scheme-pending là documented blocker
decisions: [DEC-TASK7AJ3K8-002]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghtk/
changes_project_state: true
created: 2026-09-14
updated: 2026-09-14
owner: [dev]
related_tickets: [TASK-KCXKVR, TASK-44F7V7, SPIKE-A1DGPY, TASK-Y3X6H5, TASK-STC3NB]
---

# [SLP][FEAT-YA2C0W][TASK-6YG3HP] GHTK structural alignment lên ShippingCore v5

## Embedded Mini-Spec

### Goal

Migrate `Secomm_Ghtk` sang v5 contracts hiện hữu: per-operation capability
(`CarrierOperationAddressCapabilityInterface` + `ShippingAddressOperation` +
`AddressRepresentation`), operation-specific handoff (`handoffContextForOperation`), shared COD
identification (`CodPaymentMethodResolverInterface`). Remove deprecated per-carrier contract
usage. **KHÔNG** freeze address scheme values (TASK-44F7V7 BLOCKED_BY_CREDENTIAL); **KHÔNG** sửa
ShippingCore/VietNamAddress; **KHÔNG** đụng KCXKVR correctness (status mapper, CREATE shape,
weight kg, tracking).

### Expected Behavior

1. `GhtkOperationAddressCapability` implements `CarrierOperationAddressCapabilityInterface`:
   per-op scheme = profile scheme (**CANDIDATE — pending TASK-44F7V7 probe; single freeze seam**),
   per-op representations = `[AddressRepresentation::TEXT_NAME]`, per-op textualFallback = false.
   Đồng thời **compatibility shim**: implements deprecated `CarrierAddressCapabilityInterface`
   (delegate per-op RATE) — LÝ DO: ShippingCore `RuntimeAddressContextBuilderInterface` vẫn
   type-hint per-carrier interface (ShippingCore hard stop — không sửa vì uncertainty của GHTK).
   Removal condition: khi ShippingCore có per-op builder entry.
2. `GhtkAddressAdapter::resolve(…, string $operation)` → `handoffContextForOperation(context,
   capability, operation)`. RATE (`Carrier\Ghtk`) và CREATE (`OrderSubmitService`) truyền
   operation RÕ RỘNG; `PickupAddressResolver` nhận operation từ caller (origin thuộc operation
   đang chạy). Không infer operation downstream (§17).
3. `DefaultCodAmountResolver` consume shared `CodPaymentMethodResolverInterface::isCod()`;
   BỎ carrier-owned `cod_method_codes` (config.xml default + system.xml field +
   `GhtkConfig::getCodMethodCodes`) — clean migration (pre-stable, không deployed consumer).
   Merchant migration note: set `secomm_shippingcore/cod/payment_methods` (empty = nothing COD —
   ShippingCore contract). Amount logic giữ nguyên (base_total_due + partial guard = provider
   conversion, không phải policy §13).
4. Deprecated grep gate: 0 GHTK production reference tới deprecated handoff/per-carrier capability
   NGOẠI TRỪ shim interface được document ở (1).

### Constraints / Rules

- GHTK → ShippingCore → VietNamAddress; 0 ShippingCore/VietNamAddress diff; 0 Mageplaza/external
  resolver dependency.
- KHÔNG encode RATE/CREATE = VN_ADMIN_2025 như architecture fact — values là CANDIDATE pending
  probe, cô lập 1 seam (`GhtkOperationAddressCapability`), đổi ~2 dòng khi probe xong (§34).
- KHÔNG đụng: status mapper/webhook/CREATE payload/weight conversion (KCXKVR), ORDER_ID_EXIST
  recovery, CANCEL, pickup UI, business-error taxonomy, LegacyRateStrategy (GHTK không consume).
- `GHTK_2025` profile code giữ (§26 — internal identity, không cosmetic churn).

### Out of Scope

Address capability freeze (TASK-44F7V7) · ORDER_ID_EXIST recovery · CANCEL · pickup UI ·
business-error classification · LegacyRateStrategy consumption · snapshot persistence (chưa có
runtime wiring — v5 contract consumption qua handoff là đủ ở tầng carrier).

### Acceptance Criteria

AC-1: GHTK implements per-operation capability; representations = [TEXT_NAME] per op; fallback
false per op (test). AC-2: RATE + CREATE gọi `handoffContextForOperation` với operation riêng;
không dùng deprecated `handoffContext`/`handoff` (test + grep). AC-3: shim deprecated methods
delegate per-op RATE + documented + removal condition (grep gate: duy nhất 1 class implements
deprecated interface). AC-4: COD — shared resolver isCod=true → pick_money = collect amount;
false → 0; 0 runtime read của carriers/ghtk/cod_method_codes (test + grep). AC-5: adapter
không infer operation; TRACK không request address handoff (fetcher — structural, grep). AC-6:
KCXKVR regression pass (mapper/webhook/payload/weight tests giữ nguyên pass). AC-7: ShippingCore
+ VietNamAddress 0 diff mới. AC-8: scoped suite pass; validator pass.
