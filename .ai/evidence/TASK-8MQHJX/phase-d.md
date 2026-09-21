# Evidence — TASK-8MQHJX Phase D: Integration + Cross-module Regression + Architecture Closure

Ngày: 2026-09-18 · TASK-8MQHJX · architecture v10 (không tạo v11).

## A. Final runtime call path (real chain, proven bởi `CarrierRateExecutionFlowIntegrationTest`)

```text
Magento RateCollector (carrier module, unchanged)
→ Secomm\ShippingCore\Model\Rate\CarrierRateExecutionService::execute()
    1. CarrierEligibilityEvaluator::evaluate()          (REAL matcher + REAL registry)
    2. RateSourceMode gate (FALLBACK_ONLY short-circuit)
    3. OriginProviderInterface::resolve() → country gate
    4. CarrierAddressHandoffService::handoffContextForOperation(RATE, policy)
       → ShippingAddressResolutionManager → REAL VnAdminAddressResolver (Secomm_VietNamAddress)
       → AddressResolutionPolicy: STRICT / FALLBACK / PICK_PRIMARY (REAL selector seam)
    5. RealtimeCarrierRateContributorInterface::contribute()  (carrier-owned, boundary-logged)
→ Secomm\ShippingCore\Model\ServiceLevel\ServiceLevelRateAggregator::aggregate()
→ Secomm\ShippingCore\Model\ServiceLevel\ServiceLevelRateOrchestrator::decide()
    (final owner: aggregation · realtime-success suppression · fallback dispatch)
```

Zero parallel orchestration path: fallback provider được dispatch CHỈ bởi orchestrator;
integration test assert provider mock calls qua `expects(never/once)` tại từng case.

## B. Case matrix (§5) — 15 tests / 73 assertions, all green

| Case | Scenario | Kết quả chứng minh |
|---|---|---|
| A | ALL + CARRIER_ONLY | realtime invoked 1 lần; fallback 0 |
| B | SELECTED_ZONES match | realtime path allowed |
| C | SELECTED_ZONES miss | realtime = 0, policy = 0 (handoff null), API = 0, fallback contribution = 0, provider = 0 |
| D | FALLBACK_ONLY + eligible | eligibility chạy; policy/handoff/API = 0; orchestrator dispatch fallback (provider exactly once) |
| E | FALLBACK_ONLY + ineligible | realtime = 0; contribution = 0; provider = 0 |
| F | WITH_FALLBACK + TECHNICAL_FAILURE | realtime FIRST; TECHNICAL_FALLBACK → orchestrator dispatch |
| G | UNAVAILABLE + PROVIDER_MAPPING_MISSING | outcome stays UNAVAILABLE; INTEGRATION_LIMITATION; orchestrator dispatch (§35.5 E2E) |
| H | INVALID_CONFIGURATION | fail closed; 0 sources; provider = 0 |
| §6 STRICT | AMBIGUOUS | no realtime, no fallback, selector never called |
| §6 FALLBACK | AMBIGUOUS + WITH_FALLBACK | no realtime; LEGACY_ADDRESS_FALLBACK → fallback dispatch; AMBIGUOUS shape retains candidates (policy consumer) |
| §6 PICK_PRIMARY ✓ | AMBIGUOUS + selector SELECTED | contributor receives resolved destination, `candidates === []` (no leakage) |
| §6 PICK_PRIMARY ✗ | NO_DESIGNATED_PRIMARY | CARRIER_ONLY → 0/0; WITH_FALLBACK → fallback qua orchestrator |
| §7 suppression | A technical + B success | final = REALTIME; provider = 0 calls |
| §9 regression | CARRIER_ONLY + mapping-missing | no degradation |

## C. Cross-module regression (§13)

```text
Secomm_ShippingCore     OK (359 tests, 980 assertions)
Secomm_VietNamAddress   OK (182 tests, 510 assertions)
Secomm_Ghn              OK (333 tests, 210823 assertions)
Secomm_Ghtk             OK (237 tests, 585 assertions; 1 PHPUnit deprecation — pre-existing baseline)
setup:di:compile        OK
project-ai-validate     exit 0
```

Baseline KHÔNG thuộc task: `Secomm_FulfillmentCore` 22 tests — 1 error + 2 failures
(pre-existing, external stream — tracking/warehouse baseline, chờ stream đó resolve).

Ghi chú vận hành: một lần chạy đa-module trước đó báo errors tạm thời (file ghi đè giữa lúc
chạy từ stream song song); chạy lại sạch toàn bộ — số liệu trên là lần chạy ổn định cuối.

## D. Integration defects found & fixed trong Phase D

1. **`ServiceLevelRateOrchestrator` bỏ qua INTEGRATION_LIMITATION** — final owner chỉ đọc
   `hasTechnicalFailure` + `hasLegacyAddressFallbackEligibility` → eligibility source thứ 3
   (Phase C amendment) bị drop trước khi dispatch, §35.5 "mapping missing → fallback YES"
   không thể xảy ra E2E. Minimal generic fix (§11 cho phép): orchestrator đọc thêm
   `hasIntegrationLimitationEligibility()`. Ownership/khung khác không đổi.
2. **`FallbackEligibility::__set_state()` thiếu** — DI compiler `var_export` object default
   (`new FallbackEligibility()` trong CarrierRateExecutionDecision) vào generated config;
   CLI fatal "Call to undefined method __set_state()" lúc bootstrap. Được RUNTIME SMOKE
   verification phát hiện (CLI boot check). Fix chuẩn PHP: `__set_state` round-trip 3 flags.

## E. GHN integration proof (§3)

- `Secomm_Ghn\Model\Carrier\Ghn::collectRates()`: FALLBACK_ONLY guard = DEFENSIVE_ONLY
  (thin config read, short-circuit trước mapping/API) — không own mode semantics.
- `GhnRateCalculator`: policy pass-through (thin read) vào `handoffContextForOperation()`
  — selection/ranking nằm trong shared handoff/selector, GHN không pick candidate.
- GHN structured outcomes giữ nguyên (AMBIGUOUS/UNMAPPED/PROVIDER_MAPPING_MISSING dưới
  STATUS_UNAVAILABLE, không reclassify).
- 0 redesign; Type-5/CREATE/webhook untouched (test suite 333 green).

## F. GHTK regression proof (§4)

- 0 refs tới `RateSourceMode` / `AddressResolutionPolicy` / `DestinationScope` / `CanonicalZone` /
  PRE_2025 trong production code — không bị ép GHN semantics.
- `GhtkAddressAdapter` dùng `handoffContextForOperation` với default policy (FALLBACK) —
  giữ address/provider semantics riêng.
- 0 Mageplaza/TableRate call (1 docblock mention trong GhtkFeeResponse = historical note).

## G. Invariant grep (§14) — 0 active violations

| Pattern | Hits | Phân loại |
|---|---|---|
| GHN/GHTK zone matching + eligibility | 0 | — |
| carrier is_primary inspection | 0 | — |
| carrier direct Mageplaza/TableRate call | 1 | docblock historical note — false positive |
| carrier-local fallback dispatch | 0 | — |
| ShippingCore GHN-specific reason parsing | 3 | docblock examples (`e.g. GHN_LOCATION_NOT_FOUND`) — false positive |
| provider-ID / localized-text zone matching | 6 | `$label` VO field (không phải matching input) — false positive |
| active LegacyRateStrategy consumer | 0 | — |

## H. Runtime smoke (§12) — BLOCKED_BY_ENVIRONMENT (không invented evidence)

- GHN sandbox credentials present (environment=sandbox, api_token/shop_id set, active=1).
- Blockers: `origin_district_id` unset (merchant origin chưa cấu hình) + store catalog/quote
  data trống (local dev DB) → không chạy được real quote flow E2E.
- Giá trị phụ thực của bước smoke: CLI bootstrap check đã phát hiện và xác nhận fix
  `__set_state` (mục D2) — CLI hoạt động lại bình thường sau compile.

## I. Provider auth/config ambiguity (§10) — DEFERRED

Không có consumer runtime hiện tại yêu cầu seam provider-auth warning; không invent.
Ghi nhận: `ShippingFailureReason::INVALID_CONFIGURATION` = merchant-side (fail closed);
§35.5 configurable auth/config seam cần constant provider-auth riêng + warning seam — được
ghi rõ trong architecture SSOT amendment note + CHANGELOG. Không overloading
INVALID_CONFIGURATION/TECHNICAL_FAILURE/INTEGRATION_LIMITATION.
