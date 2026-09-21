# Task Spec: ShippingCore — v10 Runtime Completion (Carrier Eligibility / Zones / Rate Source Mode / Address Policy execution)

Specification ID: SPEC-TASK-8MQHJX

> Filename: `SPEC-TASK-8MQHJX-shippingcore-v10-runtime-completion.md`. Implement controlled delta
> v10 runtime completion — 4 deliverables theo directive; KHÔNG reopen các foundation khác.

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-8MQHJX |
| Feature ID | FEAT-YA2C0W (parent — chuỗi ShippingCore) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ implementation directive; architecture basis = address-shipping.md **Revision v10** |
| Status | **VALID → IMPLEMENTED & CLOSED** 2026-09-18 (Phase A+B+C+D; runtime orchestration COMPLETE; amendment INTEGRATION_LIMITATION TL-approved; evidence `.ai/evidence/TASK-8MQHJX/`) |
| Date | 2026-09-16 |
| Related Ticket(s) | TASK-8MQHJX · TASK-Y3X6H5 (per-op capability) · TASK-M3ME32 (E-SL2) · TASK-NQT782 (bridge) |
| Workflow Mode | A (shipping shared-contract) |

## 1. Objective

Đóng 4 deliverables v10 runtime:

1. **CarrierEligibility runtime** — evaluate DestinationScope + zones per carrier
2. **CanonicalZone runtime** — static canonical geography zone registry + matcher
3. **RateSourceMode upstream gating** — execute coordinator quyết short-circuit realtime
4. **AddressResolutionPolicy execution** — chỉ sau eligibility + mode permit realtime

Tái sử dụng: `CarrierAddressHandoffService` (handoff), `ShippingAddressResolutionManager` (resolution), `ServiceLevelRateOrchestrator` (E-SL2 — KHÔNG đổi).

## 2. Audit (code thật 2026-09-16)

| Capability | Trạng thái | Verdict |
|---|---|---|
| `AddressResolutionPolicy` (STRICT/FALLBACK/PICK_PRIMARY) | `Api/Address/AddressResolutionPolicy.php` ✓ | EXISTS_AND_REUSE |
| `RateSourceMode` (CARRIER_ONLY/CARRIER_WITH_FALLBACK/FALLBACK_ONLY) | `Api/Rate/RateSourceMode.php` ✓ | EXISTS_AND_REUSE |
| DestinationScope | **THIẾU** | NEW |
| CanonicalZone (VO) | **THIẾU** | NEW |
| CanonicalZoneRegistry | **THIẾU** | NEW |
| Zone matcher | **THIẾU** | NEW |
| CarrierEligibility evaluator | **THIẾU** | NEW |
| RateSourceMode gating consumer | **THIẾU** (chỉ constants) | NEW |
| Policy evaluation consumer | **THIẾU** | NEW |
| Fulfillment context | **THIẾU** (P1: scalar origin readiness flag) | NEW (lean) |

## 3. Scope

### 3.1 `Api\Address\DestinationScope` (constants)

```text
ALL | SELECTED_ZONES + all()/exists()/assertKnown
```

### 3.2 `Api\Address\CanonicalZoneInterface` + `Model\Address\CanonicalZone` (VO)

```text
getCode(): string
getLabel(): string
isEnabled(): bool
getIncludeProvinceCodes(): array   // canonical VN province codes (VN-XX)
getIncludeWardCodes(): array       // canonical VN ward codes (VNA25-*)
getExcludeWardCodes(): array       // canonical VN ward codes (VNA25-*)
```

Matching precedence (deterministic, fail-closed):

```text
1. !enabled → không match
2. provinceCodes non-empty + province KHÔNG match → không match
3. includeWardCodes non-empty + ward KHÔNG trong → không match
4. excludeWardCodes non-empty + ward CÓ trong → không match (exclude wins)
5. Còn lại → match
```

### 3.3 `Api\Address\CanonicalZoneRegistryInterface` + `Model\Address\CanonicalZoneRegistry`

```text
getByCode(code): ?CanonicalZoneInterface
getEnabled(): CanonicalZoneInterface[]
```

DI array, zero-zone valid state.

### 3.4 `Api\Rate\CarrierEligibilityResultInterface` + `Model\Rate\CarrierEligibilityResult`

```text
isEligible(): bool
getReasonCode(): ?string   // ELIGIBLE | DESTINATION_NOT_IN_SCOPE | ORIGIN_NOT_READY | CARRIER_INELIGIBLE
getMatchedZoneCode(): ?string
```

### 3.5 `Api\Rate\CarrierEligibilityEvaluatorInterface` + `Model\Rate\CarrierEligibilityEvaluator`

```text
evaluate(destinationScope, allowedZoneCodes, destinationProvince, destinationWard): CarrierEligibilityResult
```

* ALL → eligible (nếu generic prerequisites pass)
* SELECTED_ZONES + zone match → eligible
* SELECTED_ZONES + không match → ineligible

### 3.6 `Api\Rate\CarrierRateExecutionDecisionInterface` + `Model\Rate\CarrierRateExecutionDecision`

```text
getRateSourceMode(): string    // CARRIER_ONLY | CARRIER_WITH_FALLBACK | FALLBACK_ONLY
shouldCallCarrier(): bool      // CARRIER_ONLY/CARRIER_WITH_FALLBACK → true; FALLBACK_ONLY → false
mayUseFallback(): bool         // CARRIER_WITH_FALLBACK → true; else → false
```

### 3.7 `Api\Rate\CarrierRateExecutionServiceInterface` + `Model\Rate\CarrierRateExecutionService`

```text
decide(carrierCode, destinationScope, allowedZoneCodes, destinationProvince, destinationWard): CarrierRateExecutionDecision
```

B1 runtime: eligibility → mode gating → return decision. KHÔNG gọi carrier API, KHÔNG gọi fallback provider, KHÔNG resolve address.

## 4. Out of scope

Carrier adaptation · bridge changes · external resolver · PICK_PRIMARY selector integration (đã có qua selector contracts) · physical package · COD changes · zones admin UI · mass zone data curation · FulfillmentContext VO mở rộng (P1: scalar origin readiness flag qua config) · RateSourceMode dynamic mutation.

## 5. Acceptance Criteria

* **AC-1**: `DestinationScope` constants ALL/SELECTED_ZONES; KHÔNG hardcode HCM_INNER v.v.
* **AC-2**: `CanonicalZone` matching precedence đúng (enabled → province → include ward → exclude ward); exclude wins; empty includeWard = no positive ward restriction.
* **AC-3**: Registry: zero-zone valid; getByCode lookup; getEnabled filter.
* **AC-4**: `CarrierEligibilityResult` + evaluator: ALL → eligible; SELECTED_ZONES + match → eligible; miss → ineligible; ineligible carrier X → cả realtime lẫn fallback contribution = 0.
* **AC-5**: `CarrierRateExecutionDecision`: mode gating đúng 3 modes; FALLBACK_ONLY → shouldCallCarrier = false.
* **AC-6**: compile + phpunit scoped (0 new fail) + validator 0 new finding.
* **AC-7**: README/CHANGELOG + CURRENT_STATE sync; architecture SSOT v10 note "runtime implementation complete" (không tạo v11).

## 6. Test plan

`DestinationScopeTest` · `CanonicalZoneTest` (VO invariants) · `CanonicalZoneRegistryTest` (zero-zone, getByCode, getEnabled, duplicate code) · `CarrierEligibilityEvaluatorTest` (ALL/SELECTED_ZONES match/miss, zone matching precedence) · `CarrierRateExecutionDecisionTest` (3 modes × shouldCallCarrier/mayUseFallback).

## 7. Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Zone matching logic phức tạp hoá | P1 static canonical geography only; KHÔNG GIS/DSL |
| Eligibility evaluator tách khỏi fallback orchestration | E-SL2 orchestrator giữ nguyên ownership; execution coordinator chỉ emit contribution |

Rollback: revert — không DB, không carrier code.
