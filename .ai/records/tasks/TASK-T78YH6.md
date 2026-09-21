---
id: TASK-T78YH6
type: task
title: 'Phase E-C0 — Carrier-facing address handoff trong Secomm_ShippingCore (builder + handoff service, Option B-minimal)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-T78YH6 — handoff shape theo approved E-C0 directive (SPIKE-YH439T basis); TL review spec text chạy cùng code pre-review
specification_ref: ../../specs/SPEC-TASK-T78YH6-shippingcore-carrier-address-handoff.md
risk: medium                  # additive builder/contract/service; chưa có runtime consumer; 0 carrier code
status: in_progress
priority: high
decision_assessment: none-material   # thực thi SPIKE-YH439T §4 Option B-minimal (đã approved); không architecture decision mới
decisions: [DEC-FEATYA2C0W-004]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
created: 2026-09-08
updated: 2026-09-08
owner: [dev]
related_tickets: [TASK-5XDG1P, SPIKE-YH439T, TASK-XXBN5X]
---

# [SLP][FEAT-YA2C0W][TASK-T78YH6] Phase E-C0 — Carrier-facing address handoff trong Secomm_ShippingCore (builder + handoff service, Option B-minimal)

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-TASK-T78YH6-shippingcore-carrier-address-handoff.md, FULL)*

### Goal

Điền gap "carrier không có standard handoff": `DestinationContextBuilder` (Quote\Address +
capability → `ShippingAddressResolutionContext` qua bridge `VnOperationalAddressResolverInterface`
REUSE duy nhất) → `ShippingAddressResolutionManager` (E-B, cached) → `CarrierAddressHandoffService`
(translate + catch exception + fallback-eligibility) → `CarrierAddressHandoff` DTO. Carrier không
còn tự dựng context / catch `UnsupportedDestinationException` / diễn giải 4-state.

### Expected Behavior

1. Builder: map country/identity/street + targetScheme từ `capability.getRequiredScheme()` (1
   điểm duy nhất — context DTO bắt buộc targetScheme); bridge unresolved → source identity
   null/null (→ manager UNMAPPED); `candidateCodes` luôn `[]` (không trust caller — §17); KHÔNG
   resolve graph/provider mapping/carrier IDs.
2. Handoff matrix: non-VN → `applicable=false` + `UNSUPPORTED_DESTINATION` (service catch —
   carrier không catch); EXACT/MAPPED → applicable + resolvedAddress + no-fallback + no-reason;
   AMBIGUOUS/UNMAPPED → resolvedAddress **null** (không auto-select) + `CANONICAL_UNRESOLVED` +
   fallbackEligible = `capability.supportsTextualFallback()` (chỉ ALLOW — carrier tự build
   payload riêng, ShippingCore không build text payload — §13).
3. Service: single path qua manager (§16 — không gọi VnAdminAddressResolver/MappingCandidateFinder
   trực tiếp, cache hiệu lực); external pool KHÔNG invoke (§14); không log expected states (§22);
   không đụng service-level/fallback-price (§20) và rate outcome (§21).
4. Handoff contract 4 members + 2 reason constants (`UNSUPPORTED_DESTINATION`,
   `CANONICAL_UNRESOLVED`) — KHÔNG `isResolved()` (derive), KHÔNG `getUnresolved()`, KHÔNG PII
   (§18), KHÔNG provider-stage reasons (§19: Stage 1 canonical handoff tách Stage 2 provider
   mapping — stage-2 failure không convert ngược thành CANONICAL_UNRESOLVED).

### Constraints / Rules

- Bridge runtime→canonical: DUY NHẤT `VnOperationalAddressResolverInterface` (audit §2 — resolveFromRuntime
  pure code-lookup, unresolved = reason không exception; 0 production consumer trước E-C0 — trở
  thành consumer đầu tiên). Không tạo path thứ hai.
- KHÔNG: carrier code, rate orchestration, external resolver, provider mapping, checkout methods,
  origin canonicalization, Mageplaza/Launchpad, admin config, DB.
- Input builder: `Quote\Address` typed (carriers giữ qua `RateRequest->getShippingAddress()`);
  order-address shipment-path là follow-up.
- DI: 2 preference mới; external pool + service-level registry giữ nguyên.
- Architecture decisions mới: KHÔNG (Option B-minimal + bridge reuse đã approved trong SPIKE-YH439T
  + directive; target-scheme-at-builder là consequence của context constructor hiện tại — documented).

### Out of Scope

Rate outcome (E-C1) · service-level aggregation/fallback pricing (E-SL1) · external resolver ·
provider mapping · checkout methods · OrderOperations · origin canonicalization · carrier modules ·
Mageplaza_*/Launchpad_* · logging policy · shipment-path builder overload.

### Acceptance Criteria

AC-1..AC-7 của SPEC-TASK-T78YH6 (tóm tắt): builder map đúng + candidateCodes [] · service
delegate 100% qua manager (mock assert) + catch-translate non-VN · matrix 4 hàng đúng · VO
invariants + 0 PII + không isResolved/getUnresolved · bridge duy nhất + external pool không
invoke · DI + compile + validator 0 new finding + phpunit pass + 0 carrier/Mageplaza/Launchpad
code · README engineering rule + Stage 1/2 + CHANGELOG + working memory sync.

## Plan

`../plans/TASK-T78YH6-implementation-plan.md`
