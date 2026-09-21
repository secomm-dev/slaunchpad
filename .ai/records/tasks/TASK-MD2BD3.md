---
id: TASK-MD2BD3
type: task
title: 'ShippingCore v10 — PICK_PRIMARY: directional curated primary + deterministic selector (VietNamAddress + AddressResolutionPolicy)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-MD2BD3 — selection semantics theo architecture Revision v10 §35.4 (DEC-FEATYA2C0W-006 amendment)
specification_ref: ../../specs/SPEC-TASK-MD2BD3-pick-primary-v10.md
risk: medium                  # schema additive + selector mới + handoff policy branch; dataset curation supply sau
status: in_progress
priority: high
decision_assessment: implements-amendment   # thực thi DEC-FEATYA2C0W-006 amendment (v10 PICK_PRIMARY promotion, 2026-09-18)
decisions: [DEC-FEATYA2C0W-006]
components:
  - CMP-VNADDR
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/VietNamAddress/
  - app/code/Secomm/ShippingCore/
changes_project_state: true
created: 2026-09-16
updated: 2026-09-16
owner: [dev]
related_tickets: [TASK-Y3X6H5, TASK-M3ME32, TASK-NQT782]
---

# [SLP][FEAT-YA2C0W][TASK-MD2BD3] ShippingCore v10 — PICK_PRIMARY: directional curated primary + deterministic selector (VietNamAddress + AddressResolutionPolicy)

## Embedded Mini-Spec

*(đầy đủ tại specs/SPEC-TASK-MD2BD3-pick-primary-v10.md, FULL)*

### Goal

Implement architecture v10 §35.4: `AddressResolutionPolicy` (STRICT | FALLBACK | PICK_PRIMARY) +
directional curated primary designation trên mapping dataset + deterministic selector trong
`Secomm_VietNamAddress` + handoff per-operation policy integration. PICK_PRIMARY chỉ applicable
cho carrier RATE yêu cầu legacy scheme; không auto-pick; không fallback-mask data defect.

### Expected Behavior

1. VietNamAddress mapping: cột `is_primary` (boolean, directional — chỉ meaningful theo hướng
   row); reader dual-header (legacy 5-cột + v1.1 6-cột); validator duplicate-primary per
   directional key → error.
2. `VnPrimaryCandidateSelector::selectPrimary(sourceScheme, sourceCode, targetScheme,
   candidateCodes)`: count < 2 → NOT_APPLICABLE; đúng 1 curated primary → SELECTED; 0 →
   NO_DESIGNATED_PRIMARY; >1 → MULTIPLE_PRIMARY (fail-closed, không tie-break alphabetically);
   candidate order không ảnh hưởng.
3. ShippingCore handoffForOperation + policy param (BC-safe): AMBIGUOUS + PICK_PRIMARY + curated
   primary → re-resolve same-scheme EXACT trên selected candidate → resolved handoff (không phải
   $candidates[0]); NO_DESIGNATED/MULTIPLE → unresolved handoff như AMBIGUOUS. STRICT/FALLBACK
   1:1 regression.
4. Snapshot provenance +3 optional getters (selectionPolicy/selectionReason/candidateCount) —
   persistence DEFER như trước.

### Constraints / Rules

- KHÔNG: carrier changes, bridge changes, external resolver activation (v8 dormant), zones,
  physical package redesign, COD changes, rank metadata (chỉ khi curation yêu cầu nhiều mức).
- Directional: primary trên edge (2025:A → PRE:B) KHÔNG có hiệu lực khi resolve ngược
  (PRE:B → 2025) — directive §1.1.
- Import: KHÔNG infer primary; duplicate/missing-curated → fail-loud theo §6/§7.

### Out of Scope

Carrier adaptation · bridge · external resolver · zones redesign · snapshot persistence · COD ·
mass dataset curation.

### Acceptance Criteria

AC-1..AC-6 của SPEC-TASK-MD2BD3 (tóm tắt): schema+whitelist · dual-header reader · duplicate
primary validation · selector semantics (directional/0/1/>1) · handoff policy param · snapshot
provenance · compile + phpunit scoped (0 new fail) + validator 0 new finding · README/CHANGELOG
cả 2 module + CURRENT_STATE sync.

## Plan

`../plans/TASK-MD2BD3-implementation-plan.md`
