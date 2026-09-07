---
id: TASK-J9AVGK
type: task
title: 'Phase D — Administrative mapping layer + directional resolution API (EXACT/MAPPED/AMBIGUOUS/UNMAPPED)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: MINI
spec_status: VALID            # approved via plan 2026-08-27 (user acting as SA/TL)
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-08-27
updated: 2026-08-27
decisions: [DEC-FEATYA2C0W-003]
decision_assessment: material
components:
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/VietNamAddress/etc/db_schema.xml
  - app/code/Secomm/VietNamAddress/Model/
  - app/code/Secomm/VietNamAddress/Model/Import/
  - app/code/Secomm/VietNamAddress/Api/
  - app/code/Secomm/VietNamAddress/Console/
changes_project_state: true
changes_architecture: true    # mapping model + resolution contract mới
changes_integration: false
changes_known_limitations: false
last_verified: 2026-08-27
supersedes: [TASK-X0XKH4, TASK-AP6YXP]   # mapping model + resolver contract thay thế thiết kế relation/resolver cũ
---

# [SLP][FEAT-YA2C0W][TASK-J9AVGK] Phase D — Administrative mapping layer + resolution API

<!-- CANONICAL TASK RECORD — supersede TASK-X0XKH4 (relation table) + TASK-AP6YXP (resolver RESOLVED/AMBIGUOUS/NOT_FOUND)
     theo DEC-FEATYA2C0W-003: directed mapping edges + statuses EXACT/MAPPED/AMBIGUOUS/UNMAPPED, code-based (không cần 2 scheme cùng runtime). -->

## Summary

Bảng `secomm_vietnam_address_mapping` (directed edges giữa unit codes của 2 scheme, relation SAME_AS/RENAMED_TO/MERGED_INTO/SPLIT_INTO) + CLI import/validate (STOP_ON_ERROR, orphan check vs unit table, reverse-ambiguity report) + `VnAdminAddressResolverInterface` (union outgoing+incoming edges; AMBIGUOUS không auto-pick).

## Mini Spec

### Goal
Carrier/resolution pipeline tương lai (Phase E) có thể dịch unit code giữa các scheme versioned một cách deterministic, kể cả khi scheme nguồn đã rời runtime (chỉ còn trong historical layer).

### Expected Behavior
1. Schema: mapping_id PK, source_scheme, source_code, target_scheme, target_code, relation_type; UNIQUE(source_scheme, source_code, target_scheme, target_code); index target (reverse); KHÔNG FK.
2. Mapping CSV: header `source_scheme,source_code,target_scheme,target_code,relation_type`; rules: scheme ∈ catalog, src≠tgt, codes tồn tại trong unit table (orphan = error), không trùng edge; BOM/CRLF tolerated.
3. CLI: `secomm:vietnam-address:import-mapping <file> [--dry-run]` (validate all → upsert idempotent) + `secomm:vietnam-address:validate-mapping [--file]` (orphan/same-scheme/bad-type errors + reverse-ambiguity REPORT informational; exit nonzero chỉ khi errors).
4. `Api/VnAdminAddressResolverInterface::resolve(sourceScheme, sourceCode, targetScheme): VnAddressResolutionInterface`; statuses tại `Api/Data/VnAddressResolutionInterface`: cùng scheme + unit tồn tại → EXACT; cross-scheme: candidates = union outgoing (source=) + incoming (target=) edges → 1 = MAPPED (kèm relationType), >1 = AMBIGUOUS (candidateCodes sorted, KHÔNG auto-pick), 0 = UNMAPPED (reason UNKNOWN_SOURCE_UNIT nếu unit thiếu, NO_MAPPING nếu còn). Unknown scheme → LocalizedException.
5. Impl `Model/VnAdminAddressResolver` + `Model/MappingCandidateFinder` (SQL mỏng, parameterized) + DTO.

### Constraints / Rules
- Directional: mapping không giả định reversible; reverse-merge (A→C, B→C) cho forward deterministic nhưng reverse AMBIGUOUS — API phải diễn đạt.
- Không thêm cột mapping lên `directory_region_city` hay unit table.
- Mapping DATA chưa cung cấp — ship model + CLI + API; seed chờ nguồn (như D-R1 cũ, không bao giờ infer theo tên).
- Relation types chỉ giữ subset có ích cho resolution (SAME_AS/RENAMED_TO/MERGED_INTO/SPLIT_INTO).

### Out of Scope
Phase E orchestration + carrier capability + external mapping (design notes trong DEC-003 §notes); disambiguation strategies.

### Acceptance Criteria
- AC-001: import-mapping fixture nhỏ idempotent; dry-run 0 writes; lỗi orphan/duplicate → nonzero exit, không ghi.
- AC-002: resolver 4 statuses đúng: EXACT (same scheme), MAPPED (1 edge), AMBIGUOUS (split A→C+A→D; reverse-merge C→[A,B]), UNMAPPED (no mapping / unknown unit).
- AC-003: validate-mapping báo reverse-ambiguity informational; orphan là error.
- AC-004: unit tests 7 resolver cases + mapping validator/reader green.

## Approach

Plan §7. Phụ thuộc TASK-9394A9 (unit table cho orphan check).

## Verification

- [x] AC-001..004 — 2026-08-27: mapping CLI smoke (dry-run/real/DB-validate + reverse-ambiguity warning); resolver live 4 statuses (MAPPED/AMBIGUOUS candidates/EXACT/UNMAPPED reason). Evidence `.ai/runtime/evidence/TASK-J9AVGK/`.

**Status: dev + pre-review PASS — chờ TL review (Tier-2).** Mapping DATA chờ nguồn cung cấp.

## Related records

- Parent FEAT-YA2C0W; Decision DEC-FEATYA2C0W-003; supersedes TASK-X0XKH4 + TASK-AP6YXP (notes đã ghi trong 2 record đó); Phase E/F = future tasks.
