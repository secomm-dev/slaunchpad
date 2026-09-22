# Evidence — TASK-6YG3HP: GHTK structural alignment lên ShippingCore v5

Date: 2026-09-14 · Basis: SPIKE-A1DGPY + TASK-KCXKVR + TASK-44F7V7 (BLOCKED_BY_CREDENTIAL) + actual v5 contracts

## A. Executive result

**PARTIAL ALIGNMENT — ADDRESS SCHEME BLOCKED** (đúng §35 Option A/B kết hợp):
- Structural pieces KHÔNG cần concrete scheme → migrated đầy đủ (per-operation capability,
  operation-specific handoff, shared COD resolver).
- Address scheme VALUES → giữ candidate hiện hành trong 1 freeze seam duy nhất
  (`GhtkOperationAddressCapability`), documented PENDING — KHÔNG encode như architecture fact.
- Deprecated contract → còn ĐÚNG 1 documented shim (`GhtkLegacyCapabilityShim`) vì ShippingCore
  scalar builder vẫn type-hint per-carrier contract (hard stop §24 — GHTK scheme uncertainty
  không phải ShippingCore gap).

## B. Delta matrix (§36)

| Area | Before | After | Status |
|---|---|---|---|
| address capability | `GhtkAddressCapability` implements deprecated per-carrier `CarrierAddressCapabilityInterface` | `GhtkOperationAddressCapability` implements `CarrierOperationAddressCapabilityInterface` (per-op); old class DELETED | MIGRATED |
| RATE handoff | `handoffContext(context, per-carrier capability)` | `handoffContextForOperation(context, capability, RATE)` | MIGRATED |
| CREATE handoff | cùng generic handoff | `handoffContextForOperation(context, capability, CREATE)` | MIGRATED |
| pickup (origin) handoff | implicit shared | operation passthrough từ caller (RATE từ collectRates, CREATE từ OrderSubmitService) | MIGRATED |
| representation | "TEXT_NATIVE" profile mode concept | `AddressRepresentation::TEXT_NAME` per-op qua `getSupportedRepresentations(op)` | MIGRATED |
| scheme | assumed trong capability cũ | CANDIDATE — pending TASK-44F7V7; single seam `GhtkOperationAddressCapability::getRequiredScheme(op)` | PENDING (documented) |
| COD detection | carrier config `carriers/ghtk/cod_method_codes` + `GhtkConfig::getCodMethodCodes` | shared `CodPaymentMethodResolverInterface::isCod` (`secomm_shippingcore/cod/payment_methods`) | MIGRATED |
| snapshot/failure | carrier interpretation qua handoff | vẫn consume ShippingCore handoff (AMBIGUOUS/UNMAPPED → null; non-VN → not-applicable) — v5 per-op path | ALIGNED (no dup logic) |
| GHTK adapter | carrier-owned | unchanged carrier-owned (+operation param) | PRESERVED |

## C/D. RATE & CREATE flow (before/after)

```text
BEFORE: collectRates/OrderSubmitService → GhtkAddressAdapter.resolve(…)
        → runtimeContextBuilder.build(…, per-carrier capability)
        → handoffContext(context, per-carrier capability)          # generic, op inferred

AFTER:  collectRates        → adapter.resolve(…, ShippingAddressOperation::RATE)
        OrderSubmitService  → adapter.resolve(…, ShippingAddressOperation::CREATE)
        PickupAddressResolver(origin, operation)                    # origin thuộc op đang chạy
        → runtimeContextBuilder.build(…, GhtkLegacyCapabilityShim)  # documented shim, 1 legacy call
        → handoffContextForOperation(context, GhtkOperationAddressCapability, operation)
        → GhtkAddressAdapter Stage-2 (canonical name_vi + optional override)  # KCXKVR giữ nguyên
```

TRACK: `GhtkTrackingFetcher` — identifier-only, KHÔNG request address handoff (structural; grep
proof: fetcher không import capability/handoff). CANCEL chưa tồn tại (out of scope).

## E. Address capability state (VERIFIED vs PENDING tách bạch)

- `representation = [TEXT_NAME]` per op — VERIFIED_FROM_OFFICIAL_DOCS (text-based API, 0 carrier
  IDs — SPIKE-A1DGPY); freeze sẵn trong capability.
- `scheme = CANDIDATE VN_ADMIN_2025` — PENDING (TASK-44F7V7 BLOCKED_BY_CREDENTIAL). Được ghi
  trong docblock capability là candidate + single freeze seam; KHÔNG được trích như fact.
- `districtRequirement = PENDING` probe.
- `supportsTextualFallback = false` per op — khớp primary-TEXT_NAME semantics (directive §22).

## F. COD ownership (§12–§14)

- `DefaultCodAmountResolver` inject `CodPaymentMethodResolverInterface::isCod()` (shared,
  config `secomm_shippingcore/cod/payment_methods`); provider conversion giữ nguyên
  (base_total_due → pick_money; partial-COD fail-fast; prepaid 0).
- Carrier-owned config REMOVED clean (pre-stable, không deployed consumer — §14): system.xml
  field + config.xml default + `GhtkConfig::getCodMethodCodes` + DB value cần xóa thủ công nếu
  có (local DB đã có row — merchant migration note: set
  `secomm_shippingcore/cod/payment_methods=cashondelivery,...` khi enable GHTK COD; empty =
  nothing is COD — ShippingCore contract).

## G. Canonical snapshot/failure semantics

GHTK KHÔNG tự reinterpret: consume `CarrierAddressHandoffInterface` (applicable/resolvedAddress/
candidateCodes) từ `handoffContextForOperation`; AMBIGUOUS/UNMAPPED → null (no request);
non-VN → not-applicable → null. 0 manual failure_class calculation trong module (grep). Snapshot
persistence chưa có runtime wiring (ShippingCore contract only) — GHTK consume qua handoff là
đủ tầng carrier; không tự dựng snapshot store (§11).

## H. Deprecated code removed / isolated

- DELETED: `Model/Address/GhtkAddressCapability.php` (deprecated per-carrier impl).
- ISOLATED: `Model/Address/GhtkLegacyCapabilityShim.php` — DUY NHẤT reference còn lại tới
  deprecated `CarrierAddressCapabilityInterface`; WHY: ShippingCore scalar builder type-hint
  (hard stop); REMOVAL CONDITION: khi ShippingCore có per-operation context-builder entry.
- REMOVED: `carriers/ghtk/cod_method_codes` (system.xml + config.xml + GhtkConfig).
- `GhtkApiProfile` giữ `GHTK_2025` + `getAddressMode()` (§26 — không cosmetic churn; note:
  profile code là internal identity, ver=1.5 semantics vẫn NEEDS_RUNTIME_VERIFICATION).

## I. Tests

- Scoped (Ghtk|ShippingCore|VietNamAddress): **578 tests / 1563 assertions — 0 failure** trong
  scope modules (baseline 570 → +8 net mới/tái cấu trúc).
- GHTK: 178 tests pass — gồm mới: `GhtkOperationAddressCapabilityTest` (7: per-op scheme/
  representation/fallback, unknown-op reject, shim delegate), adapter test operation-forward
  (RATE+CREATE captured qua `handoffContextForOperation`), COD resolver test tái viết trên
  shared resolver (7 — gồm "unconfigured method collects nothing").
- Full suite: 1488 tests — 12 failing TẤT CẢ pre-existing ngoài scope (Secomm_Tracking 7,
  Secomm_FulfillmentCore 3, Secomm_Ghn 1 — WIP streams song song; GHN số giảm từ 27→12 giữa
  2 lần chạy do stream đó đang tự fix). New failures: 0.

## J. Gates (§32/§27)

```
grep deprecated handoff/per-carrier usage trong Ghtk production:
  → DUY NHẤT GhtkLegacyCapabilityShim (documented shim + removal condition)
grep getCodMethodCodes/cod_method_codes (production):
  → CLEAN (comment removal-note duy nhất)
grep ShippingCore diff trong session này:
  → 0 file mới/sửa bởi TASK-6YG3HP (diff hiện hữu = r0 uncommitted work + streams khác)
$ php .ai/bin/project-ai-validate --check-specs --check-records → exit 0
```
