# Implementation Plan — TASK-MQ2DRG (GHN Checkout Pre-Validation + >50kg RATE Integration Fallback)

| Specification | SPEC-FEAT-FQWEQ3 (../../records/specs/SPEC-FEAT-FQWEQ3-ghn-carrier-adapter.md) + delta embedded trong TASK-MQ2DRG |
|---|---|
| **Work item** | TASK-MQ2DRG — slice của FEAT-FQWEQ3 (GHN carrier adapter; related TASK-WAWNDS, TASK-9Q5ZAK) |
| **Mode** | A |
| **Depends** | TASK-WAWNDS (type-5 RATE items[] — đang trên working tree), v10 freeze baseline |
| **Risk** | high — supersede test-locked decision + ShippingCore frozen contract amendment (TL-approved) |
| **TL decisions** | Amendment tối thiểu APPROVED + supersede >50kg stance CONFIRMED (AskUserQuestion 2026-09-23) |

## Steps

1. **Constraints centralization** — `Model/GhnShipmentConstraints.php` (MỚI, final
   const-namespace private ctor): `TYPE_2_MAX_WEIGHT_G=20000` (docs fee contract),
   `TYPE_5_MAX_WEIGHT_G=50000` (docs CREATE cap; RATE enforcement = business decision 2026-09-23),
   `MAX_SIDE_CM=200` (docs CREATE), `RATE_MAX_SIDE_CM=150` (SANDBOX_OBSERVED). Alias-only
   refactor: `QuoteParcelEstimate::HEAVY_WEIGHT_THRESHOLD_GRAMS`, `GhnParcel::HEAVY_WEIGHT_THRESHOLD_GRAMS`,
   `GhnPhysicalParcelInterpreter::HEAVY_BOUNDARY_G` → TYPE_2; `GhnPhysicalLimit::MAX_WEIGHT_G`
   → TYPE_5, `MAX_SIDE_CM` → MAX_SIDE_CM; `GhnPackageLimits::MAX_DIMENSION_CM` → RATE_MAX_SIDE_CM.
   Gate: Ghn suite green (số test giữ nguyên).
2. **Pre-validation** — `Model/Rate/GhnWeightConstraintViolation.php` (MỚI VO: kind/packageIndex/
   weightGrams/limitGrams — không reason string); `QuoteParcelEstimate::findWeightLimitViolation():
   ?GhnWeightConstraintViolation` (HARD trước AGGREGATE; strict `>`); `GhnPackageLimits::+
   REASON_PACKAGE_WEIGHT_LIMIT_EXCEEDED`; `GhnRateCalculator`: guard trong CẢ `resolveAndQuote()`
   và `quoteWithHandoff()` + helper private `weightLimitOutcome()` (kind→reason mapping single
   home; log + unavailable). Gate: Ghn suite đúng 1 test đỏ (rewrite target).
3. **ShippingCore amendment** — `ShippingFailureReason::+RATE_REQUEST_UNREPRESENTABLE`;
   `SafeDegradationEligibilityPolicy` default map +=; `CarrierRateExecutionService::
   outcomeDrivenEligibility()` + branch → `integrationLimitation()`.
4. **Tests** — GHN: `GhnRateCalculatorTest` +4 test +1 rewrite (aggregate >50kg → limitation
   no-call), `QuoteParcelEstimateTest` (MỚI — boundary grid), `RealtimeRateContributorTest`
   +pass-through. ShippingCore: policy test + execution service ×2 + Flow testCaseI.
5. **Docs + records** — Ghn CHANGELOG (supersede note), ShippingCore CHANGELOG, §35.5
   amendment note (NO v11), USER_GUIDE checkout matrix, FEAT record refs, DECISIONS.md annotate
   stale "type-5 RATE backlog" line, SESSION_STATE/CURRENT_STATE.
6. **Gates** — full suites (Ghn/ShippingCore/Ghtk/VietNamAddress/Launchpad) + compile +
   validator + invariant grep §16 (11 mục) + final report A–N.

## Decision matrix (locked)

Xem TASK-MQ2DRG Expected Behavior + plan file §4: strict `>` boundaries (20000-exact → type 5;
50000-exact unit/total → representable); HARD trước AGGREGATE; hard → không fallback mọi mode;
limitation → fallback theo mode; không gọi API cho cả hai; CREATE + estimator + contributor
+ legacy max_package_weight không đụng.

## Test plan (directive §14)

1-2 type bands (existing + giữ) · 3 (20000 exact → type 5, existing lock) · 4-5 (50000 exact,
NEW ×2) · 5 (aggregate >50kg → limitation, REWRITE) · 6-7 (mode-gated fallback, ShippingCore
tests NEW ×3) · 8 (hard unit, NEW) · 9 (dimension — DEFERRED, existing dormant tests giữ) ·
10 (missing dims — NEW estimate-layer test) · 11 (API not-called — fixture `post` never) ·
12 (≤50kg API once — existing) · 13 (FALLBACK_ONLY — existing GhnZoneExecutionTest) ·
14 (429/5xx — existing regression).
