---
id: DEC-TASKMQ2DRG-001
title: 'GHN RATE pre-validation weight matrix — >50kg aggregate = RATE_REQUEST_UNREPRESENTABLE (INTEGRATION_LIMITATION, supersede TASK-WAWNDS no-pre-rejection stance), hard unit >50kg = UNAVAILABLE không fallback, ShippingCore amendment tối thiểu materialize capability-unsupported'
status: accepted             # TL directive 2026-09-23 + user approval 2 điểm (AskUserQuestion) — Tier-2 review trước merge
owners: [tl, sa]
decision_type: architecture
approval_date: 2026-09-23
created: 2026-09-23
last_verified: 2026-09-23
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-MQ2DRG, FEAT-FQWEQ3]
---

# Decision Record: GHN Checkout Pre-Validation + >50kg RATE Integration Fallback

## Status

Accepted (2026-09-23 — TL directive 18 section; user approval 2 điểm qua AskUserQuestion:
① ShippingCore amendment tối thiểu, ② supersede TASK-WAWNDS >50kg stance). Tier-2 (CTO/SA)
review toàn bộ change set trước merge.

## Decision Type

Architecture (ShippingCore v10 additive amendment + GHN rate-path business decision — KHÔNG tạo v11)

## Decisions

1. **Weight decision matrix pre-validation trước fee API** (reuse `QuoteParcelEstimator`
   output — không parallel calculation; strict `>` boundaries; HARD trước AGGREGATE):
   unit >50000g → UNAVAILABLE `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED` (GHN-owned, KHÔNG
   fallback-eligible mọi mode); total >50000g mọi unit hợp lệ → UNAVAILABLE
   `RATE_REQUEST_UNREPRESENTABLE` (shared, INTEGRATION_LIMITATION — fallback theo mode);
   ≤20000g type 2 / 20000–50000g type 5 items[] — giữ nguyên. Không gọi fee API cho 2 case
   pre-validation. Không cartonization/splitting/fake request.

2. **ShippingCore v10 additive amendment (TL-approved)** — materialize dòng "capability
   unsupported → fallback YES" §35.5 (tồn tại trong frozen taxonomy nhưng chưa có constant):
   (a) `ShippingFailureReason::RATE_REQUEST_UNREPRESENTABLE`; (b) `SafeDegradationEligibilityPolicy`
   default map +=; (c) `CarrierRateExecutionService::outcomeDrivenEligibility()` + branch →
   `integrationLimitation()` (mode gate giữ nguyên). Lý do DI-only không đủ: transported path
   hard-code reason→source switch fail-closed với reason lạ. KHÔNG tạo v11 — dated note §35.5.

3. **Supersede TASK-WAWNDS ">50kg aggregate NOT rejected at RATE"** (và residual stance
   DEC-TASK9Q5ZAK-001 "RATE type-5 giữ backlog" — STALE từ khi TASK-WAWNDS implement items[]):
   sandbox fact "fee API quotes >50kg" VẪN đúng và được giữ làm history; việc quote >50kg
   giờ là business NO tại pre-validation (checkout thiếu packing truth; CREATE-side 50kg/
   package cap nghĩa là quote aggregate không authoritative). ≤50kg type-5 items[] per-unit
   flow giữ nguyên.

4. **Reason ownership split**: `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED` = carrier-owned — chỉ emit
   cho hard unit rejection (real business rejection, §35.5 "fallback NO"); `RATE_REQUEST_
   UNREPRESENTABLE` = shared — CHỈ emit cho representational gap thật, KHÔNG BAO GIỜ cho
   business rejection (rule chống carrier tự mở fallback).

5. **Threshold centralization**: `GhnShipmentConstraints` (provenance riêng từng giá trị —
   không merge evidence sources); các const cũ thành alias giữ public name.

6. **Dimension pre-validation (Case 6/9) DEFERRED** — gap report: không có attribute
   shipping-dimension có unit contract (Secomm_Base length/width/height = varchar display,
   consumer-plugin không module active nào đọc; Mageplaza `ts_dimensions_*` chưa seed).
   Missing dims không reject. Không invent EAV mới.

7. **`GHN_HEAVY_RATE_ESTIMATION_UNAVAILABLE` giữ reserved** (frozen module; removal = churn).
   Legacy `carriers/secomm_ghn/max_package_weight` guard (`processAdditionalValidation`) giữ
   nguyên — interplay documented.

## Consequences

- >50kg carts: mất realtime GHN quote (trước đây có) → fallback TableRate khi
  CARRIER_WITH_FALLBACK; GHN ẩn khi CARRIER_ONLY. §35.5-intended degradation.
- `RATE_REQUEST_UNREPRESENTABLE` fallback-eligible cho MỌI carrier emit nó (1 emitter hiện
  tại: GHN) — rule decision 4 là guard.
- Test-locked change: `GhnRateCalculatorTest::testAggregateWeightAboveDocumentedRootCap...`
  REWRITE (SUCCESS 70kg → UNAVAILABLE no-call).
