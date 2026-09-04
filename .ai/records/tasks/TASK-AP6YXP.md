---
id: TASK-AP6YXP
type: task
title: 'VietnamAddressResolverInterface + Resolution DTO + implementation + unit tests'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: MINI
spec_status: DRAFT
specification_ref: Embedded Mini-Spec
risk: medium
status: proposed
created: 2026-08-25
updated: 2026-08-25
decisions: [DEC-FEATYA2C0W-001]
decision_assessment: material
components:
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/VietNamAddress/Api/
  - app/code/Secomm/VietNamAddress/Model/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-YA2C0W][TASK-AP6YXP] VietnamAddressResolverInterface + Resolution DTO + implementation + unit tests

> **SUPERSEDED 2026-08-27 — DEC-FEATYA2C0W-003 / TASK-J9AVGK**: resolution contract mới (`VnAdminAddressResolverInterface`, statuses EXACT/MAPPED/AMBIGUOUS/UNMAPPED + reason + candidateCodes, union outgoing+incoming edges) thay thế thiết kế RESOLVED/AMBIGUOUS/NOT_FOUND của task này. Không implement task này; historical note giữ lại.

> **Input assumption changed 2026-08-27 — DEC-FEATYA2C0W-002 (swap model)**: DB chứa một scheme/lần → resolver không thể JOIN current↔legacy trong DB. Thiết kế lại khi kích hoạt: resolve theo **code** (`VNC-…`/`VNL-…` dataset-supplied, ổn định vĩnh viễn) + relation data từ TASK-X0XKH4 (code-based); nếu cần both-schemes runtime, coi swap CLI hoặc đọc quan hệ từ relation table. Contract RESOLVED/AMBIGUOUS/NOT_FOUND giữ nguyên giá trị.

<!-- CANONICAL TASK RECORD — service contract cho carrier modules consume SAU này. Blocked-by TASK-X0XKH4 (relation data). -->

## Summary

Contract `VietnamAddressResolverInterface` (`resolveLegacyLocation` / `resolveCurrentLocation`) trả structured `VietnamAddressResolutionInterface` — status RESOLVED/AMBIGUOUS/NOT_FOUND + candidates; hoàn toàn không biết carrier.

## Mini Spec

### Goal
Resolution current↔legacy deterministic cho 1:1, tường minh AMBIGUOUS cho 1:N, NOT_FOUND khi không có relation — caller (carrier module sau này) tự quyết cách xử ambiguity.

### Expected Behavior
1. API: `Api/VietnamAddressResolverInterface` — `resolveLegacyLocation(int $locationId): VietnamAddressResolutionInterface` (input: region_id|city_id thuộc VN) + `resolveCurrentLocation(int $legacyLocationId)` tương tự.
2. `Api/Data/VietnamAddressResolutionInterface`: status (const RESOLVED/AMBIGUOUS/NOT_FOUND), sourceType, sourceLocationId, targetType, candidateLocationIds[], resolvedLocationId (set chỉ khi RESOLVED). DTO theo pattern module.
3. Impl `Model/VietnamAddressResolver`: validate node tồn tại + thuộc country VN (query bảng chung theo type — region thì country_id, city thì join region); không phải VN → NOT_FOUND + log info; query relations theo (source_type, source_id) chiều tương ứng; đếm candidates: 0→NOT_FOUND, 1→RESOLVED (+resolvedLocationId), ≥2→AMBIGUOUS (KHÔNG auto-chọn).
4. Unit tests mock relation collection: 6 kịch bản spec (1:1, 1:N, no-map, reverse, non-VN input, node-không-tồn-tại).

### Constraints / Rules
- Không biết GHN/GHTK/Ahamove/carrier IDs/shipping methods (audit trong review).
- Không geocoding/lat-lng.
- Result object — không nullable array, không magic values.

### Out of Scope
- Consumer nào của resolver (carrier modules — work items riêng).

### Acceptance Criteria
- AC-001: 6 unit scenarios xanh, phủ đủ ma trận status.
- AC-002: AMBIGUOUS trả đầy đủ candidateLocationIds (không drop).
- AC-003: Non-VN + non-existent input → NOT_FOUND có log, không throw.

## Approach

Plan: [FEAT-YA2C0W-implementation-plan](../../plans/FEAT-YA2C0W-implementation-plan.md) — Step 4.

## Implementation Notes

Chưa triển khai (proposed — blocked-by TASK-X0XKH4).

## Verification

- [ ] AC-001..003 — evidence: `.ai/runtime/evidence/TASK-AP6YXP/`

## Related records

- Parent feature: FEAT-YA2C0W
- Decision: DEC-FEATYA2C0W-001 (accepted — contract shape)
