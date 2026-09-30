---
id: TASK-MQ2DRG
type: task
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
legacy_ids: []
title: 'GHN Checkout Pre-Validation + >50kg RATE Integration Fallback — weight decision matrix trước fee API, hard-constraint vs INTEGRATION_LIMITATION tách bạch, threshold centralization'
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: ../../records/specs/SPEC-FEAT-FQWEQ3-ghn-carrier-adapter.md
risk: high
status: in_review  # dev-complete 2026-09-23; Ghn 400/211020 + ShippingCore 550/1437 + Launchpad 135 OK; compile OK
created: 2026-09-23
updated: 2026-09-23
plan: ../../plans/TASK-MQ2DRG-implementation-plan.md
decisions: [DEC-TASKMQ2DRG-001]
decision_assessment: major
decision_refs: [DEC-TASKMQ2DRG-001]
related_tickets: [TASK-WAWNDS, TASK-9Q5ZAK]
verified_against_commit:
components:
  - CMP-GHN
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghn/
  - app/code/Secomm/ShippingCore/
changes_project_state: true
changes_architecture: false
---

# [SLP][FEAT-FQWEQ3][TASK-MQ2DRG] GHN Checkout Pre-Validation + >50kg RATE Integration Fallback — weight decision matrix trước fee API, hard-constraint vs INTEGRATION_LIMITATION tách bạch, threshold centralization

TL directive "GHN Checkout Pre-Validation + >50kg RATE Integration Fallback" (18 section,
2026-09-23). Supersede stance TASK-WAWNDS "no weight pre-rejection at RATE / >50kg not
rejected" (TL + user approvals 2026-09-23 — AskUserQuestion 2 điểm). KHÔNG cartonization,
KHÔNG package splitting, KHÔNG đổi CREATE behavior, KHÔNG đổi ShippingCore ngoài amendment
tối thiểu đã được approve.

## Mini Spec

### Goal

1. Decision matrix pre-validation TRƯỚC fee API (deterministic, reuse output của
   `QuoteParcelEstimator` — không parallel weight calculation):
   - Bất kỳ unit > 50000g → UNAVAILABLE `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED` (hard, carrier-owned
     reason, KHÔNG fallback-eligible — real business rejection), không gọi API.
   - Total > 50000g với mọi unit ≤ 50000g → UNAVAILABLE `RATE_REQUEST_UNREPRESENTABLE` (shared
     reason mới) → INTEGRATION_LIMITATION → fallback theo RateSourceMode, không gọi API.
   - ≤ 20000g → type 2; 20000–50000g → type 5 + items[] per-unit — giữ nguyên flow hiện có.
   - Boundaries strict `>`: đúng 20000g (single) → type 5; đúng 50000g (unit hoặc total) →
     representable. Mixed violation → HARD thắng (A trước E).
2. Centralize thresholds: `Model\GhnShipmentConstraints` (20000/50000/200/150 với provenance
   riêng từng giá trị) — các const cũ thành alias, không còn duplicate literal.
3. ShippingCore amendment tối thiểu (TL-approved 2026-09-23): + constant
   `RATE_REQUEST_UNREPRESENTABLE`, + policy default map entry, + `outcomeDrivenEligibility()`
   branch → `integrationLimitation()` — materialize dòng "capability unsupported → fallback
   YES" của §35.5. KHÔNG tạo v11.

### Expected Behavior

- Matrix đầy đủ (kèm boundaries) xem plan `../../plans/TASK-MQ2DRG-implementation-plan.md` §4.
- Case E: `CARRIER_WITH_FALLBACK` → fallback qua machinery hiện có (orchestrator
  `hasIntegrationLimitationEligibility()` → Launchpad coordinator — 0 đổi Launchpad);
  `CARRIER_ONLY` → `none()`; `FALLBACK_ONLY` → không đổi (short-circuit step 2 hiện có).
- Case A: UNAVAILABLE mọi mode, không fallback (policy anti-abuse: carrier-owned string không
  bao giờ unlock fallback).
- 429/5xx technical classification KHÔNG đổi; weight violation không bao giờ là
  TECHNICAL_FAILURE.
- Dimension pre-validation (Case 6/9) DEFERRED — gap report: không có attribute
  shipping-dimension có unit contract (`length/width/height` Secomm_Base = varchar display;
  `ts_dimensions_*` Mageplaza chưa seed). Missing dims không reject (Case 7/10).

### Constraints / Rules

- FROZEN: ShippingCore ngoài 3 điểm amendment; `QuoteParcelEstimator`/`GhnRateRequestMapper`/
  `RealtimeRateContributor`/CREATE path/`Ghn::processAdditionalValidation` — nguyên vẹn.
- Không cartonization/splitting/fake multi-package request; không >50kg request gửi fee API;
  không Mageplaza dependency trong Secomm_Ghn; không fallback provider coupling trong GHN.
- Reason ownership: `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED` = GHN-owned (chỉ emit cho hard);
  `RATE_REQUEST_UNREPRESENTABLE` = shared (chỉ emit cho representational gap thật — rule ghi
  trong DEC).
- `GHN_HEAVY_RATE_ESTIMATION_UNAVAILABLE` giữ reserved (không xoá).

### Out of Scope

Dimension pre-validation (deferred + gap report); cartonization/packing optimization;
shipment CREATE change; multi-package rate calculation; mới EAV attributes; fallback provider
selection mechanism mới; website scope/UI.

### Acceptance Criteria

Directive §14 (14 case): type bands + boundaries (20000/50000 exact), >50kg limitation +
fallback theo mode (CARRIER_WITH_FALLBACK eligible / CARRIER_ONLY none), hard unit >50kg →
UNAVAILABLE không API, missing dims không reject, API not-called assertions (fixture call
count), ≤50kg API đúng 1 lần, FALLBACK_ONLY unchanged, 429/5xx regression green. Cộng:
ShippingCore tests mirror PROVIDER_MAPPING_MISSING trio (policy + execution service + flow
E2E testCaseI); threshold grep không còn duplicate literal; compile + validator green.
Final report A–N.
