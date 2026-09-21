---
id: TASK-TBM30R
type: task
title: 'Phase GHN-B2 — Address dataset lifecycle: versioned export → offline reviewed mapping → import/bootstrap (auto-mapping demoted to candidate tooling)'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-TBM30R — chuẩn hóa từ TL/SA directive 2026-09-10 ("decisions already approved")
specification_ref: ../../specs/SPEC-TASK-TBM30R-secomm-ghn-address-dataset-lifecycle.md
risk: high                    # production mapping activation path + bundled dataset bootstrap
status: in_progress           # activated 2026-09-10 — directive TL/SA + Mini-Spec + plan artifact đủ (DEC-TASKZ132WA-002)
priority: high
decision_assessment: material   # DEC-FEATFQWEQ3-002 — lifecycle architecture change (accepted theo directive)
decisions: [DEC-FEATFQWEQ3-002, DEC-FEATFQWEQ3-001, DEC-FEATYA2C0W-004]
components:
  - CMP-GHN
source_areas:
  - app/code/Secomm/Ghn/
created: 2026-09-10
updated: 2026-09-10
owner: [dev]
related_tickets: [TASK-MZ2TCB]
changes_project_state: true
---

# [SLP][FEAT-FQWEQ3][TASK-TBM30R] Phase GHN-B2 — Address dataset lifecycle (export → offline review → import/bootstrap)

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-TASK-TBM30R-secomm-ghn-address-dataset-lifecycle.md, FULL; supersedes một phần SPEC-FEAT-FQWEQ3 §7 theo DEC-FEATFQWEQ3-002)*

### Goal

Đổi mapping lifecycle: KHÔNG còn cơ chế tự tạo APPROVED mapping (exact/normalized name, extension_names,
fuzzy, curated alias đều chỉ là tooling đề xuất/audit). Chuẩn mới: GHN API → export deterministic CSV →
offline AI/manual review (đối chiếu canonical Secomm CSVs) → reviewed mapping CSV → import → audit →
runtime resolver. Module SHIP bundled reviewed dataset (`data/`) để first-install không cần GHN API.

### Expected Behavior

1. `secomm:ghn:address:export [--scheme= --dir= --version=]`: fetch qua fetchers GHN-B → CSV
   deterministic (header cố định `scheme_code,level,provider_key,provider_id,provider_code,
   parent_provider_key,name,extension_names,status`; ordering scheme/level/provider_key; UTF-8;
   diacritics nguyên vẹn; tên verbatim; không entity_id; hierarchy reconstructable) + manifest.json
   (dataset_version, generated_at, source_environment, per-file scheme/record_count/sha256).
2. `secomm:ghn:address:import [--dir= --master-only --mapping-only]`: mặc định import bundled
   `data/` (bootstrap). Master import: validate manifest checksum/count khi có → upsert
   (parent_provider_key → parent entity) → unit mất khỏi snapshot → DISABLED (không DELETE).
   Mapping import: format `secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,
   mapping_method,mapping_status,note`; chỉ activate APPROVED; REVIEW_REQUIRED/UNRESOLVED/AMBIGUOUS
   skip + đếm; duplicate/unknown canonical/unknown provider/conflict/dangling/malformed → fail loud
   toàn bộ (không partial); UPSERT (không truncate); portable identity (không DB id trong file).
3. `secomm:ghn:address:suggest [--scheme= --output=]`: `MappingSuggester` từ matcher → workfile
   mapping CSV (exact/alias → REVIEW_REQUIRED + note nguồn; ambiguous → AMBIGUOUS + candidates;
   unmapped → UNRESOLVED) — không bao giờ ghi APPROVED.
4. `secomm:ghn:address:audit`: + duplicate, dangling (canonical identity của stored row không tồn
   tại), production_ready (unmapped=ambiguous=invalid=duplicate=dangling=0); CLI in rõ cờ.
5. Sync master data và import mapping tách bạch hoàn toàn; provider sync không đụng bảng mapping.
6. Runtime resolver giữ nguyên: stable code → APPROVED → stable GHN identity; PRE_2025 đủ triple;
   2025 verbatim names; fail closed.
7. `MappingGenerator` (auto-write) xóa; `MappingMatcher`/`NameNormalizer`/`AliasRepository` giữ
   nguyên làm candidate tooling (classification A — directive §15).

### Constraints / Rules

- Portable identity: Secomm scheme+unit_code; GHN scheme+provider_key/id/code (string). CẤM
  entity_id/region_id/city_id trong file. DB ids chỉ resolve trong importer.
- Không xây fuzzy engine/versioning platform trong module; module giữ deterministic.
- Không truncate bảng mặc định; không ghi đè mapping reviewed bằng provider sync.
- extension_names: chỉ offline candidate/diagnostic; không runtime approval tier.
- Scope discipline (directive §18): không rate/ShippingCore/VietMap/checkout/carrier khác.
- Giữ GHN-B tests (fetchers/synchronizer/matcher/resolver/normalizer/alias) — chỉ xóa test của
  behavior đổi có chủ đích (nếu có); thêm tests mới theo ma trận SPEC AC-L10.

### Out of Scope

Rate/leadtime (GHN-C) · create/webhook (GHN-D/E) · cutover (GHN-F) · admin import UI (CLI primary
theo §13) · versioning platform · E-B v2/VietMap/NO_MATCH (ngoài FEAT).

### Acceptance Criteria

AC-L1..AC-L12 của SPEC-TASK-TBM30R (nguyên văn — tóm tắt: export deterministic + manifest ·
bootstrap không cần API · import APPROVED-only fail-loud · portable identity cross-entity-id ·
DISABLED-not-delete refresh · resolver import-only · extension_names không approve · audit 9 trạng
thái + production_ready · không còn đường ghi APPROVED ngoài importer · tests đủ ma trận + round-trip ·
validators/README/i18n · QC gate khi MySQL up).

## Plan

`../plans/TASK-TBM30R-implementation-plan.md`
