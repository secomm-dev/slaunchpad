---
id: TASK-Y3X6H5
type: task
title: 'ShippingCore v4 delta A–E — per-operation capability + CanonicalResolutionSnapshot + AMBIGUOUS-only seam (reopen-and-freeze)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-Y3X6H5 — contract shapes theo address-shipping.md Revision v4 (§28 "Contract P1 đã chốt")
specification_ref: ../../specs/SPEC-TASK-Y3X6H5-shippingcore-v4-delta-a-e.md
risk: medium                  # BC-safe additions; old call sites (GhnRateCalculator/GhtkAddressAdapter) không đổi; snapshot là contract-only
status: in_progress
priority: high
decision_assessment: none-material   # 5 delta đã được amend trong architecture Revision v3/v4 (§28 "Contract P1 đã chốt"); không DEC mới
decisions: [DEC-FEATYA2C0W-004]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
created: 2026-09-11
updated: 2026-09-11
owner: [dev]
related_tickets: [TASK-STC3NB, TASK-7AJ3K8, TASK-M3ME32]
---

# [SLP][FEAT-YA2C0W][TASK-Y3X6H5] ShippingCore v4 delta A–E — per-operation capability + CanonicalResolutionSnapshot + AMBIGUOUS-only seam (reopen-and-freeze)

## Embedded Mini-Spec

*(đầy đủ tại specs/SPEC-TASK-Y3X6H5-shippingcore-v4-delta-a-e.md, FULL)*

### Goal

Implement 5 delta v3/v4 còn thiếu để freeze ShippingCore: (A) capability per-operation
(RATE/CREATE + UNIT_ID/TEXT_NAME); (B/C) `CanonicalResolutionSnapshot` contract (canonical-only,
failure_class, provenance — persistence defer); (D) resolver seam AMBIGUOUS-only explicit;
(E) verify COD (đã implement TASK-STC3NB).

### Expected Behavior

1. `ShippingAddressOperation` (RATE/CREATE) + `AddressRepresentation` (UNIT_ID/TEXT_NAME)
   constant classes (assertKnown → `LocalizedException`).
2. `CarrierOperationAddressCapabilityInterface`: `getRequiredScheme(op)` /
   `getSupportedRepresentations(op)` / `supportsTextualFallback(op)`. Old
   `CarrierAddressCapabilityInterface` @deprecated docblock-only (signature unchanged — carriers
   compile/run tiếp).
3. Handoff per-op BC-safe: builder `buildForOperation`; service `handoffForOperation` /
   `handoffContextForOperation`; handoff VO `getSupportedRepresentations()` (optional param
   default []). Unknown op → `LocalizedException`; prebuilt context targetScheme mismatch →
   `LogicException`. Old `handoff()/handoffContext()` behavior 1:1 (regression).
4. `CanonicalResolutionSnapshotInterface` + VO: RESOLVED(NONE)/UNRESOLVED(AMBIGUOUS|UNMAPPED|
   TECHNICAL); source LOCAL_MAPPING/EXTERNAL_RESOLVER; canonical_2025 scheme+unit_code;
   resolved_pre2025 = Secomm PRE-2025 unit codes (province/district/ward nullable — provider IDs
   cấm tuyệt đối, structural); provenance resolver/timestamp/mapping_version (EXTERNAL ⇒ resolver
   required). Persistence defer (§5.1).
5. Delta D docblock-only trên `ExternalAddressResolverInterface` (AMBIGUOUS-only selector; không
   UNMAPPED/mint/authoritative edge). Delta E verify-only.

### Constraints / Rules

- KHÔNG modify `Secomm_Ghn`/`Secomm_Ghtk` (adaptation = task kế tiếp; consumer impact REPORT).
- KHÔNG: GEOPOINT/GeocodeHandoff/CarrierType, CANCEL/TRACK capability, snapshot persistence/DB,
  resolver provider, COD framework mở rộng, foundation redesign (rate outcome/service-level/
  fallback/bridge — regression only).
- Dependency invariant giữ nguyên; 0 provider IDs trong ShippingCore.

### Out of Scope

Như Constraints + persistence schema + resolver provider implementation.

### Acceptance Criteria

AC-1..AC-6 của SPEC-TASK-Y3X6H5 (tóm tắt): RATE/CREATE scheme+representation khác nhau qua
handoffForOperation · snapshot canonical-only + failure_class preserved + impossible combos
reject · unknown op/context mismatch fail-fast · old handoff regression 1:1 · old capability
0 signature change · Delta D docblock · Delta E regression · compile + validator 0 new finding ·
README/CHANGELOG + freeze conclusion + working memory sync.

## Plan

`../plans/TASK-Y3X6H5-implementation-plan.md`
