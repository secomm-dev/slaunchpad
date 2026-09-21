---
id: TASK-5JQYMP
type: task
title: 'ShippingCore v5 — fallback eligibility orchestration (TECHNICAL_FALLBACK | LEGACY_ADDRESS_FALLBACK) + legacy RATE strategy constants'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-5JQYMP — eligibility semantics theo DEC-FEATYA2C0W-005 + architecture v5 §14/§15.1
specification_ref: ../../specs/SPEC-TASK-5JQYMP-shippingcore-v5-fallback-eligibility.md
risk: medium                  # BC-safe optional params trên E-SL1/E-SL2 contracts; legacy path chưa có consumer thật
status: in_progress
priority: high
decision_assessment: none-material   # thực thi DEC-FEATYA2C0W-005 + architecture v5 §14/§15.1 (đã accepted); naming DIRECT_FALLBACK là clarification — amend addendum DEC-005
decisions: [DEC-FEATYA2C0W-005]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
created: 2026-09-11
updated: 2026-09-11
owner: [dev]
related_tickets: [TASK-M3ME32, TASK-32ACTR, TASK-NQT782]
---

# [SLP][FEAT-YA2C0W][TASK-5JQYMP] ShippingCore v5 — fallback eligibility orchestration (TECHNICAL_FALLBACK | LEGACY_ADDRESS_FALLBACK) + legacy RATE strategy constants

## Embedded Mini-Spec

*(đầy đủ tại specs/SPEC-TASK-5JQYMP-shippingcore-v5-fallback-eligibility.md, FULL)*

### Goal

E-SL2 hết giả định "fallback eligible IFF hasTechnicalFailure": eligibility = explicit
orchestration state từ ĐÚNG 2 nguồn (`TECHNICAL_FALLBACK` từ aggregate; `LEGACY_ADDRESS_FALLBACK`
input caller-supplied từ legacy RATE strategy — DIRECT_FALLBACK/MAP_THEN_FALLBACK). Implementation
naming `DIRECT_FALLBACK` ≡ architecture wording "FALLBACK_ONLY" (§15.1) — mapping documented,
không silent rename.

### Expected Behavior

1. `FallbackEligibilitySource` constants + `FallbackEligibilityInterface`/VO (2 flags +
   `isEligible()` + `getSources()` deterministic).
2. `LegacyRateStrategy` constants `DIRECT_FALLBACK`/`MAP_THEN_FALLBACK` + exists/assertKnown —
   mapping documented về architecture wording.
3. Aggregator/Aggregate: optional eligibility param (BC) — aggregate carries eligibility.
4. Orchestrator: optional eligibility param; eligible = technical(aggregate) OR legacy(input);
   các nhánh SUCCESS-suppress/UNAVAILABLE/policy/provider giữ nguyên 1:1.

### Constraints / Rules

- KHÔNG: `LEGACY_ADDRESS_FAILURE` outcome, reclassify AMBIGUOUS/UNMAPPED → TECHNICAL_FAILURE,
  rules engine, ranking, retry, carrier/bridge changes, strategy resolver interface (consumer
  supply sau — directive §16).
- BC-safe: optional params cuối signature; old call sites 1:1.
- Delta A/B/D đã implement (Y3X6H5) — không refactor; Delta E (STC3NB) verify-only.

### Out of Scope

Strategy resolver seam · snapshot persistence · external resolver provider · carrier changes ·
bridge changes · rules engine.

### Acceptance Criteria

AC-1..AC-5 của SPEC-TASK-5JQYMP (tóm tắt): eligibility VO/sources · LegacyRateStrategy constants
(không FALLBACK_ONLY wording cho strategy) · BC-safe aggregator/orchestrator extensions · legacy
→ FALLBACK khi policy+provider · SUCCESS suppress · both-sources 1 call · naming mapping
documented · compile + validator 0 new finding + regression pass.

## Plan

`../plans/TASK-5JQYMP-implementation-plan.md`
