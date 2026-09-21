---
id: TASK-MZ2TCB
type: task
title: 'Phase GHN-B — GHN dual-scheme master data + mapping: secomm_ghn_address_unit + secomm_ghn_address_mapping + sync/audit CLI + fail-closed resolver'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-FEAT-FQWEQ3 — canonical Full Spec (slice reference; đặc biệt §3..§8, §42)
specification_ref: ../../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md
risk: high                    # DB schema mới (Tier-2) + external API sync + mapping data quality
status: in_progress           # activated 2026-09-10 — Mini-Spec + plan artifact đủ (DEC-TASKZ132WA-002); endpoints v3/legacy verified developer.ghn.vn 2026-09-10
priority: high
decision_assessment: material   # schema chi tiết (provider_key UNIQUE design) + không DirectoryReferenceGuard → TL review tại plan/approve
decisions: [DEC-FEATFQWEQ3-001, DEC-FEATYA2C0W-004]
components:
  - CMP-GHN
source_areas:
  - app/code/Secomm/Ghn/
changes_project_state: true
created: 2026-09-10
updated: 2026-09-10
owner: [dev]
related_tickets: [TASK-RJFTPZ, TASK-FMBBSD, TASK-9Q5ZAK]
---

# [SLP][FEAT-FQWEQ3][TASK-MZ2TCB] Phase GHN-B — GHN dual-scheme master data + mapping: secomm_ghn_address_unit + secomm_ghn_address_mapping + sync/audit CLI + fail-closed resolver

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md, FULL — đặc biệt §3 Address Models, §5 Database, §6 Mapping, §7 Mapping Rules, §8 Sync, §42 AC-ADDR)*

### Goal

`Secomm_Ghn` sở hữu GHN administrative master data cả 2 scheme (`GHN_ADMIN_2025` province→ward;
`GHN_ADMIN_PRE_2025` province→district→ward) và bridge mapping canonical↔GHN — sync được, audit
được, runtime resolver fail-closed. Đây là nền cho rate (GHN-C, cần legacy IDs) và create (GHN-D,
cần 2025 names).

### Expected Behavior

1. Schema (Tier-2): `secomm_ghn_address_unit` (entity_id, scheme_code, provider_id, provider_code,
   provider_key = COALESCE do app điền, parent_id self-FK, depth 1..3, name, extension_names,
   status ACTIVE|DISABLED, source_version, synced_at, timestamps; UNIQUE(scheme_code, provider_key))
   + `secomm_ghn_address_mapping` (secomm_scheme_code + secomm_unit_code UNIQUE, ghn_address_unit_id
   FK CASCADE, mapping_method EXACT_NAME|CURATED_ALIAS|MANUAL, mapping_status APPROVED|
   PENDING_REVIEW, verified_at, timestamps); whitelist entry đủ; KHÔNG FK/đụng
   `secomm_vietnam_address_unit` hay `directory_*` (AC-ADDR-005).
2. Sync CLI `secomm:ghn:address:sync --scheme= --dry-run`: fetch GHN master data cho 1 scheme
   (PRE_2025 = legacy master-data p/d/w; 2025 = new-model master data — path verify docs, KHÔNG
   infer); upsert theo (scheme, provider_key); row biến mất → status=DISABLED (không DELETE); set
   source_version + synced_at; report counts; chạy CLI-only, không trong checkout request.
3. Mapping generation: với mỗi canonical unit (`VnAddressUnitProviderInterface`) trong parent chain
   tương ứng, normalize name (NFC, trim, collapse-space, lowercase, bỏ punctuation) → exact match
   trong cùng parent → `EXACT_NAME`/APPROVED; không exact → curated alias CSV module-shipped
   (`Files/ghn_mapping_aliases_*.csv`) → `CURATED_ALIAS`/APPROVED; 0 hoặc >1 match → UNMAPPED/
   AMBIGUOUS `PENDING_REVIEW` — KHÔNG auto-approve (AC-ADDR-006). Runtime không fuzzy-match name.
4. Audit CLI `secomm:ghn:address:audit --scheme= --format=table|json`: mapped/unmapped/ambiguous/
   invalid/disabled_provider_unit + coverage % theo scheme + level; export unresolved list JSON.
5. `GhnMappingResolver`: `(secomm_scheme_code, secomm_unit_code)` → GHN unit; PRE_2025 ward trả đủ
   triple `province_id + district_id + ward_code` (walk parent chain); chỉ cache APPROVED (cache
   type `secomm_ghn_mapping`); miss/DISABLED → `GhnMappingNotFoundException` fail-closed.

### Constraints / Rules

- Một row unit = một GHN unit trong MỘT scheme — cấm column trộn scheme (current_name/legacy_name…).
- Sync KHÔNG mutate `secomm_vietnam_address_unit` / `directory_country_region` /
  `directory_region_city` (SPEC §8); không cần DirectoryReferenceGuard (không runtime-id FK — reasoning ghi vào plan).
- Legacy seed: KHÔNG auto-import từ `secomm_ghn_address_mapping_location` (R26 first-match/invalid).
- Curated alias file versioned trong repo; resolution loop = sửa file + chạy lại generation.
- PHP 8.2+ strict_types; parameterized SQL only; i18n 2 file; README/CHANGELOG cập nhật.

### Out of Scope

Rate/services/leadtime (GHN-C) · create/shipment (GHN-D) · webhook (GHN-E) · admin mapping UI ·
cutover legacy (GHN-F) · E-B v2/VietMap/NO_MATCH authoring (ngoài FEAT — nhận export JSON từ audit
làm input) · sửa ShippingCore/VietNamAddress.

### Acceptance Criteria

- AC-B1: `setup:upgrade` tạo đúng 2 bảng + constraints + whitelist; `db:status` không tăng entry lạ.
- AC-B2: sync `--dry-run` và thật chạy được cho TỪNG scheme; counts khớp GHN portal (evidence
  `.ai/evidence/TASK-MZ2TCB/`); re-sync idempotent (upsert, không duplicate).
- AC-B3: audit xuất đủ 5 trạng thái + coverage %; unresolved JSON export được.
- AC-B4: resolver unit test: hit APPROVED; PRE_2025 ward trả đủ triple; miss → exception fail-closed;
  DISABLED unit → exception; cache chỉ APPROVED.
- AC-B5: mapping generator unit test: exact / curated / ambiguous (nhiều match không auto-pick) /
  unmapped + normalization; pipeline không ghi APPROVED cho fuzzy.
- AC-B6: phpunit scoped green; validators không tăng FAIL mới; **TL review schema (Tier-2) trước
  khi merge**.

## Plan

`../plans/TASK-MZ2TCB-implementation-plan.md`
