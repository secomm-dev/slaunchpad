---
id: TASK-S0M7YC
type: task
title: 'Docs sync + boundary audit + TASK-4F1K3N scope transfer note'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: C
specification_level: MINI
spec_status: DRAFT
specification_ref: Embedded Mini-Spec
risk: low
status: proposed
created: 2026-08-25
updated: 2026-08-25
decisions: [DEC-FEATYA2C0W-001]
components:
  - CMP-VNADDR
  - CMP-ADDR
source_areas:
  - app/code/Secomm/VietNamAddress/README.md
  - app/code/Secomm/VietNamAddress/CHANGELOG.md
  - .ai/records/tasks/TASK-4F1K3N.md
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-YA2C0W][TASK-S0M7YC] Docs sync + boundary audit + TASK-4F1K3N scope transfer note

<!-- CANONICAL TASK RECORD — documentation + governance cleanup, chạy cuối. -->

## Summary

README/CHANGELOG VietNamAddress theo năng lực mới (profiles, membership, relations, resolver, CLI); audit boundary (không leak VN/carrier logic vào AddressDropdown); ghi nhận scope transfer từ TASK-4F1K3N.

## Mini Spec

### Goal
Docs + records phản ánh đúng phân tầng sau FEAT; chống re-open boundary violations.

### Expected Behavior
1. README: mô tả 2 profiles + datasets + relation model + resolver + CLI workflow (source → import/validate → canonical nodes → membership → relations); CHANGELOG entry FEAT-YA2C0W.
2. Boundary audit: grep AddressDropdown không có VN/carrier terms mới (`vn_current|vn_legacy|VietnamAddressResolver|secomm_vietnam`) — evidence.
3. TASK-4F1K3N record: note scope transfer (phần đăng ký vn_current profile + membership chuyển sang FEAT-YA2C0W; 4F1K3N giữ import v2 machinery + re-key generic).
4. `09_MAGENTO_MODULE_MAP.md` + BR-002 note nếu cần (coordinate TASK-ZHFVRH của FEAT-2PZQKJ tránh double-write).

### Constraints / Rules
- Chỉ docs/records — không code.
- Link, không copy knowledge (no-duplicate-knowledge).

### Out of Scope
- Regeneration toolkit; AGENTS.md §12.

### Acceptance Criteria
- AC-001: README/CHANGELOG cập nhật đủ các năng lực mới.
- AC-002: Boundary grep sạch (evidence).
- AC-003: TASK-4F1K3N có note scope transfer rõ ràng.

## Approach

Plan: [FEAT-YA2C0W-implementation-plan](../../plans/FEAT-YA2C0W-implementation-plan.md) — Step 6.

## Implementation Notes

Chưa triển khai (proposed).

## Verification

- [ ] AC-001..003 — evidence: `.ai/runtime/evidence/TASK-S0M7YC/`

## Related records

- Parent feature: FEAT-YA2C0W
- Related: TASK-4F1K3N (scope transfer), TASK-ZHFVRH (docs FEAT-2PZQKJ)
