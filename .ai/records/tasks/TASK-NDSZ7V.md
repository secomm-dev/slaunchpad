---
id: TASK-NDSZ7V
type: task
title: 'Canonical mapping seed — VN_ADMIN_PRE_2025→2025 (10.064 edges) + auto-import Data Patch + resolver graph verification'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: B
specification_level: MINI
spec_status: VALID            # user-directed implementation request 2026-09-04 (dataset reviewed baseline + DoD chi tiết trong request); TL code review pending
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-04
updated: 2026-09-04
decisions: [DEC-FEATYA2C0W-003]
decision_assessment: none-material   # seed data + deploy automation cho model DEC-003 §5 / TASK-J9AVGK đã ship — không đổi decision nào
components:
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/VietNamAddress/Files/
  - app/code/Secomm/VietNamAddress/Setup/Patch/Data/
  - app/code/Secomm/VietNamAddress/Test/Unit/
changes_project_state: true
changes_architecture: false   # populate data cho mapping layer hiện có; resolver/finder/schema KHÔNG đổi
changes_integration: false
changes_known_limitations: false
last_verified: 2026-09-04
supersedes: []
---

# [SLP][FEAT-YA2C0W][TASK-NDSZ7V] Canonical mapping seed — PRE_2025→2025 + auto-import patch

<!-- CANONICAL TASK RECORD — nguồn mapping data (đã review production baseline) giờ có sẵn:
     seed theo TASK-J9AVGK mini-spec ("Mapping DATA chờ nguồn cung cấp") + Data Patch deploy automation. -->

## Summary

Dataset canonical `VN_ADMIN_PRE_2025_TO_2025_mapping.csv` (10.064 edges, 63 region + 10.001 ward; PRE_2025 → 2025) được accept làm baseline — KHÔNG regenerate/enrich/complete. Import tự động qua Data Patch (`setup:upgrade`) tái dùng `VnMappingImporter` (validate ALL → upsert UNIQUE edge, idempotent). Resolver giữ nguyên: graph cardinality (1=MAPPED, >1=AMBIGUOUS, 0=UNMAPPED) là nguồn sự thật, `relation_type` chỉ là metadata; AMBIGUOUS không bao giờ auto-pick.

## Mini Spec

### Goal

Runtime pipeline (Phase E sau này) resolve được unit code giữa 2 canonical scheme ngay sau deploy — không cần manual CLI; PRE_2025 wards không có edge → `UNMAPPED` là kết quả hợp lệ (không đoán, không synthetic edge).

### Expected Behavior

1. Patch `ImportVnAdminPre2025To2025MappingPatch`: `VnMappingImporter->import(<module>/Files/VN_ADMIN_PRE_2025_TO_2025_mapping.csv, dryRun: false)`; deps `[ImportVnAdminPre2025ReferencePatch]` (chuỗi: 2025 bootstrap → PRE_2025 reference → mapping seed); path resolve qua `ComponentRegistrarInterface` (module Files/).
2. Importer validation hiện có (reuse, không duplicate): scheme ∈ catalog, src≠tgt scheme, relation_type ∈ SAME_AS|RENAMED_TO|MERGED_INTO|SPLIT_INTO, duplicate edge (canonical identity = source_scheme+source_code+target_scheme+target_code), orphan code vs `secomm_vietnam_address_unit`. STOP_ON_ERROR → exception → setup fail, 0 partial write.
3. Runtime status KHÔNG đổi: `VN_ADMIN_2025=CURRENT`, `VN_ADMIN_PRE_2025=HISTORICAL`; không đụng `directory_*`, `active_scheme`, profile mapping.
4. Resolver verification (tests, không redesign): 1:1 → MAPPED; N:1 reverse → AMBIGUOUS candidates sorted, resolvedCode null; 1:N reverse → MAPPED cho từng target; N:N → X AMBIGUOUS [A,B] + Y MAPPED A — đều suy từ cardinality (kể cả khi edge ghi SPLIT_INTO); UNMAPPED → hợp lệ, reason NO_MAPPING.
5. Idempotency: patch chạy 1 lần qua patch_list, nhưng importer an toàn khi re-run (CLI/manual) — upsert theo UNIQUE edge.

### Constraints / Rules

- KHÔNG sửa schema mapping (không weights/overlap/needs_review/district rows); KHÔNG tạo district→ward mapping (ward đã có `parent_code` → PRE district); KHÔNG runtime name matching/fuzzy; KHÔNG tự chọn candidate đầu.
- Dataset file là read-only baseline — chỉ được can thiệp nếu importer validation CHỨNG MINH file invalid.
- Không ShippingCore/VietMap/Google/carrier work (Phase E/F — out of scope).
- Mapping coverage < 100% là chấp nhận được — coverage KHÔNG phải import invariant.

### Out of Scope

VietMap/Google resolver; external geocoding; ShippingCore orchestration; carrier fallback; GHN/GHTK/Ahamove migration; mapping completion cho wards chưa cover; provider selection config.

### Acceptance Criteria

- AC-001: `setup:upgrade` import tự động 10.064 edges vào `secomm_vietnam_address_mapping`; patch deps đúng chuỗi 3 patch.
- AC-002: SQL: `COUNT(*) WHERE source=PRE_2025 AND target=2025` = 10.064; grouped by relation_type: MERGED_INTO 9.250 / SPLIT_INTO 627 / SAME_AS 146 / RENAMED_TO 41; registry status giữ nguyên; runtime directory không đổi.
- AC-003: re-import (CLI dry-run + real) không duplicate (vẫn 10.064).
- AC-004: resolver unit tests cover đủ 1:1 / N:1 / 1:N-reverse / N:N / UNMAPPED; AMBIGUOUS không auto-pick; outcome suy từ cardinality (N:N test dùng edge SPLIT_INTO để chứng minh relation_type không driving).
- AC-005: reverse-ambiguity rows tồn tại trong DB (expected, KHÔNG giảm về 1 edge).

## Approach

Tái dùng 100%: `VnMappingReader` + `VnMappingValidator` + `VnMappingImporter` (TASK-J9AVGK) + `VnAddressUnitProvider` (orphan check). Patch pattern mirror `ImportVnAdminPre2025ReferencePatch` (TASK-F9XJ5G). Resolver/finder KHÔNG đổi — chỉ bổ sung 2 unit test cases thiếu (1:N reverse, N:N).

## Verification

- [x] AC-001..005 — 2026-09-04: unit tests scoped config 214/589 xanh cho 2 address modules (+2 resolver graph cases, +3 patch tests); `setup:upgrade` exit 0, patch applied đúng chuỗi deps; SQL: grouped counts MERGED_INTO 9.250 / SPLIT_INTO 627 / SAME_AS 146 / RENAMED_TO 41, total 10.064, registry/runtime unchanged; re-import CLI → vẫn 10.064 distinct edges (idempotent); resolver live 5 cases đúng (AMBIGUOUS 12 candidates không auto-pick; UNMAPPED reason=no_mapping; N:N thật qua targets 7/6 candidates); 924/10.595 PRE wards UNMAPPED by design; 3.120 reverse-ambiguity targets được giữ nguyên. Evidence `.ai/runtime/evidence/TASK-NDSZ7V/`.

**Status: dev complete + pre-review pending → TL review (Tier-2: CMP-VNADDR).**

## Related records

- Parent FEAT-YA2C0W; Decision DEC-FEATYA2C0W-003; mapping model/CLI/API = TASK-J9AVGK; reference layer = TASK-9394A9; reference-only import + patch chuỗi = TASK-F9XJ5G.
