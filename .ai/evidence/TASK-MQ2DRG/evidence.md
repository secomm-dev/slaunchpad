# TASK-MQ2DRG — GHN Checkout Pre-Validation + >50kg RATE Integration Fallback — Evidence

Ngày: 2026-09-23 · Mode A · parent FEAT-FQWEQ3 · DEC-TASKMQ2DRG-001 (TL + user approval 2 điểm)

## 1. Suites (số chính xác)

| Suite | Tests | Assertions | Kết quả |
|---|---|---|---|
| Secomm_Ghn | **400** (baseline 386 → +14 net: +5 calculator tests, +8 estimate-grid tests, +1 contributor test, −1 rewrite) | 211020 | OK |
| Secomm_ShippingCore | **550** (baseline 546 → +4: policy + service ×2 + flow testCaseI) | 1437 | OK |
| Secomm_Ghtk | 237 | 595 | OK |
| Secomm_VietNamAddress | 195 | 786 | OK |
| Launchpad (TableRate) | 135 | 234 | OK |

Failures 0 · Errors 0 · Skips 0 · 9 PHPUnit deprecations (pre-existing). `setup:di:compile` OK.

## 2. Decision matrix proof (tests → directive §14)

1. ≤20kg type 2: `GhnRateCalculatorTest::testLightParcelQuotesServiceType2WithoutOptionalFields` + `testParcelJustUnderHeavyThresholdStillQuotesType2` (existing)
2. 20–50kg type 5: `testHeavyQuoteRatesType5WithPerUnitItems` + `testMultiParcelQuoteExpandsPerUnitRowsAndAggregatesRootWeight` (existing)
3. 20000g exact → type 5: `testHeavyQuoteRatesType5WithPerUnitItems` (existing lock) + `QuoteParcelEstimateTest::testSingleUnitExactlyAtHeavyBoundaryHasNoWeightViolation` (new)
4. 50000g exact total: `testAggregateExactlyFiftyKgStillQuotes` (new) + `GhnRateRequestMapperTest` estimate lock (existing)
5. 50000g exact single unit: `testSingleUnitExactlyFiftyKgStillQuotes` (new) + `QuoteParcelEstimateTest` (new)
6. >50kg aggregate valid units → INTEGRATION_LIMITATION: `testAggregateOverFiftyKgIsUnavailableAsUnrepresentableWithoutApiCall` (REWRITE — supersede) + `QuoteParcelEstimateTest::testAggregateOverFiftyKgWithValidUnitsIsUnrepresentable` (new)
7. CARRIER_WITH_FALLBACK → fallback eligible: `CarrierRateExecutionServiceTest::testRateRequestUnrepresentableWithFallbackModeEmitsIntegrationLimitation` (new) + E2E `CarrierRateExecutionFlowIntegrationTest::testCaseI_*` (new — SOURCE_FALLBACK reached)
8. CARRIER_ONLY → no fallback: `testCarrierOnlyRateRequestUnrepresentableEmitsNoFallback` (new)
9. Hard unit >50kg → UNAVAILABLE: `testSingleUnitOverFiftyKgIsHardUnavailableWithoutApiCall` + `testMixedViolationHardUnitWins` (new)
10. Invalid dimension → UNAVAILABLE: DEFERRED (dimension source gap) — dormant 150cm machinery giữ nguyên + tests existing (`testTrustedDimensionOverVerifiedLimitRejectsBeforeAnyProviderCall`)
11. Missing dimensions → no rejection: `testMissingDimensionsAreNotHardRejections` (existing) + `QuoteParcelEstimateTest::testMissingDimensionsNeverProduceViolations` (new)
12. API not called: `post->expects(never())` trong hard/aggregate tests + `testWeightGateRunsBeforeHandoffOnStandalonePath` (new)
13. ≤50kg API đúng 1 lần: `RealtimeContributorCallCountTest` + `CalculatorApiClientFixtureTest` (existing, green)
14. FALLBACK_ONLY unchanged: `GhnZoneExecutionTest` FALLBACK_ONLY case (existing, green) · 429/5xx: `RealtimeRateContributorTest` data provider (existing, green)

## 3. Invariant grep (directive §16)

1. No cartonization: 0 (không class/method packing mới; estimator PRODUCT_UNIT_AS_PACKAGE giữ nguyên)
2. No automatic package splitting: 0 (estimator untouched)
3. No fake multi-package RATE request: guard chặn trước payload build (>50kg không tới fetchFeeTotal)
4. No >50kg request gửi fee API: test `post->never()` cho aggregate >50kg + unit >50kg
5. No deterministic weight violation → TECHNICAL_FAILURE: weightLimitOutcome chỉ trả unavailable(); grep TECHNICAL trong calculator chỉ ở provider-exception chain
6. Hard constraint ≠ integration limitation: 2 reason tách bạch + testMixedViolationHardUnitWins
7. ShippingCore change = đúng amendment TL-approved 3 điểm (git diff xác minh)
8. No fallback provider coupling trong Secomm_Ghn: GHN chỉ emit outcome; grep FallbackRateProvider trong Ghn = 0
9. No Mageplaza dependency trong Secomm_Ghn: grep Mageplaza trong app/code/Secomm/Ghn = 0
10. No duplicate 20/50kg constants: grep `= 20000;|= 50000;` app/code/Secomm/Ghn/Model → chỉ GhnShipmentConstraints
11. No shipment CREATE behavior change: GhnPhysicalParcelInterpreter/GhnShipmentCreationService/StoreWeightConverter untouched (alias-only const)

## 4. Ghi chú

- Task chạy song song TASK-DFGFZ9 (COD ledger) trên cùng working tree — không đụng file.
- Stale memory `DECISIONS.md` (DEC-TASK9Q5ZAK-001 "RATE type-5 giữ backlog") đã annotate supersede pointer.
