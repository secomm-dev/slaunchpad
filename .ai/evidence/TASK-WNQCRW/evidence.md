# Evidence — TASK-WNQCRW (Align GHN RATE Weight Classification and CREATE Physical Parcel Decision)

Date: 2026-10-01 · DEC-TASKWNQCRW-001 · Executor: Claude (AI pre-review + dev-complete chờ TL)

## A. Pre-change audit (code-truth, trước khi sửa)

- RATE type selector duy nhất: `QuoteParcelEstimate::getServiceTypeId()` — cũ: `packageCount === 1 && total < 20000 → 2 else 5`
  (TASK-WAWNDS; docs câu "5: 20 kg or more, or multi-parcel orders"). Consumer duy nhất:
  `GhnRateCalculator::fetchFeeTotal()` L252; `items[]` guard theo type (L264).
- Trước task này KHÔNG có weight cap ở RATE (DEC-TASKFXFMJ0-001 đã gỡ pre-gate của
  DEC-TASKMQ2DRG-001; `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED` không emitter;
  `RATE_REQUEST_UNREPRESENTABLE` RESERVED).
- RATE transient: estimate không persist/cache; `secomm_ghn_shipment.service_type_id` ghi từ
  CREATE plan (`GhnShipmentCreationService:314` → `GhnShipmentRepository:120`).
- CREATE: `GhnPhysicalParcelInterpreter` derive độc lập từ PhysicalPackages; 50kg/package cap
  frozen (`GhnPhysicalLimit::MAX_WEIGHT_G` = `TYPE_5_MAX_WEIGHT_G`).
- Chỉ 2 test assertion phụ thuộc packageCount (flip 5→2): `GhnRateCalculatorTest::
  testTwoParcelTenKgTotalStillSelectsTypeFive`, `QuoteParcelEstimateTest` lightMulti leg.

## B. Sandbox retest (§6) — 2026-10-01, shop 200537, dest 1846/291124 (Nghệ An)

Script: `sandbox_weight_matrix.php` (payload mirror `fetchFeeTotal`; type theo FROZEN rule;
items[] chỉ cho type 5). Kết quả **13/13 HTTP 200, code 200 "Success"**:

| ID | Case | ST | Root(g) | Items | HTTP | Code | Total (VND) |
|----|------|----|---------|-------|------|------|-------------|
| A | single 10kg | 2 | 10000 | 0 | 200 | 200 | 199,100 |
| B | single 19.999kg | 2 | 19999 | 0 | 200 | 200 | 353,100 |
| C | single 20kg (boundary) | 5 | 20000 | 1 | 200 | 200 | 121,000 |
| D | single 35kg | 5 | 35000 | 1 | 200 | 200 | 440,000 |
| E | single 50kg (boundary) | 5 | 50000 | 1 | 200 | 200 | 440,000 |
| F | single 50.001kg | 5 | 50001 | 1 | 200 | 200 | 660,000 |
| G | 2×5kg = 10kg (multi-unit type 2) | 2 | 10000 | 0 | 200 | 200 | 199,100 |
| H | 2×10kg = 20kg (boundary) | 5 | 20000 | 2 | 200 | 200 | 121,000 |
| I | 2×30kg = 60kg | 5 | 60000 | 2 | 200 | 200 | 660,000 |
| J | 2×50kg = 100kg | 5 | 100000 | 2 | 200 | 200 | 660,000 |
| K | 3×40kg = 120kg | 5 | 120000 | 3 | 200 | 200 | 660,000 |
| L | 2×35kg = 70kg (FXFMJ0 continuity) | 5 | 70000 | 2 | 200 | 200 | 660,000 |
| M | 4×30kg = 120kg (FXFMJ0 continuity) | 5 | 120000 | 4 | 200 | 200 | 660,000 |

Đọc evidence:
- **Case G (mới — chưa từng có)**: type 2 weight-only multi-unit được provider chấp nhận,
  fee 199.100 = đúng bằng 1×10kg (case A) → provider định giá theo weight, xác nhận rule
  total-weight-only là classification đúng.
- Boundary nhất quán: C (single 20000) và H (2×10=20000) cùng fee 121.000.
- Provider KHÔNG có weight bound: F (50.001kg single) quote 200 → default gate 50000 là lựa
  chọn coherence với CREATE (merchant-tunable), không phải giới hạn provider.
- Local pre-gate (§6 expectation "1×50.001 → blocked locally, API count 0"): unit-test proof
  `GhnRateCalculatorTest::testSingleUnitOverDefaultWeightLimitIsUnavailableBeforeAnyProviderCall`
  (apiClient `expects(never)`) — script này cố tình bypass pre-gate để chứng minh provider side.

## C–F. New RATE rule / 50kg unit rule / aggregate / fallback (unit-test proof)

- Total-weight-only + item-count independence: `QuoteParcelEstimateTest::
  testItemCountDoesNotInfluenceServiceType` (1×15kg ≡ 3×5kg ≡ 10×1.5kg → 2; 1×30kg ≡ 3×10kg → 5),
  `testMultiPackageTypeFollowsTotalWeightNotPackageCount`; runtime bootstrap: 3×5kg → 2,
  2×10kg → 5.
- 50kg per-unit gate: 50001 → UNAVAILABLE `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED`, API never
  (`testSingleUnitOverDefaultWeightLimitIsUnavailableBeforeAnyProviderCall`); 50000 đúng biên
  quote (`testSingleUnitExactlyFiftyKgStillQuotes`); weight reason precedes dimension reason
  (`testWeightViolationTakesPrecedenceOverDimensionViolation`).
- Aggregate: 2×50kg=100kg quote (`testAggregateOfBoundaryUnitsHundredKgQuotesThroughTheFeeApi`),
  2×35kg=70kg full flow (`testValidUnitAggregateRunsTheFullStandaloneFlow`), sandbox I/K/J/L/M.
- Fallback NONE: `HeavyWeightRateFlowVerificationTest::
  testOverLimitUnitOutcomeIsNeverFallbackEligible` — real `SafeDegradationEligibilityPolicy`
  trả `isFallbackEligible(UNAVAILABLE, GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED) === false`.
- Merchant raise: `testRaisedWeightLimitLetsSingleUnitSixtyKgQuote` + sandbox F — provider
  chấp nhận 60kg khi config nâng (FXFMJ0 evidence giữ được dưới shape mới).

## G–H. CREATE boundary + regression

- RATE ≠ CREATE: `GhnPhysicalParcelInterpreterTest::
  testCreateDerivesIndependentlyFromTheRateClassification` — cùng order 15kg: RATE type 2
  (3 items), CREATE 2 light physical parcels → type 5 + items[] (derive lại từ physical).
- CREATE constraints intact: interpreter 51000g → "above the 50000 g per-package limit"
  (test hiện có giữ); per-dimension limits (TASK-ZS2B41 rev.2) giữ nguyên; runtime
  `GhnPhysicalLimit::getMaxPackageWeightG()` = 50000 frozen.

## I. Tests

- Ghn: **484/484** GREEN (211.303 assertions; 471 → 484, +13 net), 0 failures, 0 errors, 0 skips.
- Ghn + ShippingCore: **1084/1084** GREEN (212.833 assertions), 0 failures, 0 errors
  (9 PHPUnit deprecations = pre-existing).
- Test mới/re-pin: QuoteParcelEstimateTest +4 (over-cap, weight-precedence, raised-limit,
  item-count) + 2 re-pin; GhnRateCalculatorTest +3 mới (over-default unavailable, raised 60kg,
  boundary 100kg aggregate) + 1 rework (mixed over-limit) + 1 flip (multi-item light type 2);
  HeavyWeightRateFlowVerificationTest +1 (fallback-none) + 1 re-pin; ConfigTest +3;
  QuoteParcelEstimatorTest +2; interpreter +1 (CREATE independence).

## J. Compile / validator

- `setup:di:compile`: OK ("Generated code and dependency injection configuration
  successfully") + `cache:flush`.
- Runtime bootstrap: `getMaxPackageWeightG()` = 50000; `getMaxLengthCm()` = 200; CREATE
  frozen 50000; 3×5kg→2; 2×10kg→5; 50001g → weight tuple.
- `.ai/bin/project-ai-validate --check-records --check-specs`: baseline **56 FAIL** → sau
  **56 FAIL** (0 tăng; 1 FAIL tạm thời do plan thiếu `| Specification |` row đã fix ngay).

## K. Docs/comments updated

- Docblocks: `QuoteParcelEstimate` (header + getServiceTypeId + findHardLimitViolation),
  `GhnPackageLimits` (provenance 4 nguồn), `GhnShipmentConstraints` (TYPE_5), `GhnRateCalculator`
  (contract + 2 guard comments), `GhnParcel`, `GhnRateQuery`, `GhnPhysicalLimit`, `Config`,
  `QuoteParcelEstimator`.
- `USER_GUIDE.md` (ShippingCore): weight section + rate-time summary + legacy knob interplay.
- CHANGELOG: Ghn **0.18.0**; ShippingCore 0.26.3 (docs bullet).
- Tests comment sweep: "multi-parcel"/"no weight cap" wording (QuoteParcelEstimateTest,
  GhnRateCalculatorTest, GhnRateRequestMapperTest, HeavyWeightRateFlowVerificationTest,
  RealtimeRateContributorTest).

## L. Closure

**GHN RATE/CREATE weight semantics = FROZEN**

- RATE: total-weight-only type + per-unit 50000g display gate (merchant-tunable, default =
  CREATE contract) + aggregate uncapped + fallback NONE — implemented, unit-proven,
  sandbox-proven (13/13).
- CREATE: derive độc lập từ PhysicalPackages, constraints frozen — audit + regression tests.
- Blocker: none. (Manual checkout/admin E2E với product thật để TL/QC review — logic đã được
  unit + integration + runtime bootstrap cover.)
