---
id: TASK-9AEAQQ
type: task
title: 'Add recursive hierarchy schema — parent_city_id + code + membership table (declarative, additive)'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: high
status: completed
created: 2026-08-25
updated: 2026-08-26
decisions: [DEC-FEAT2PZQKJ-001]
decision_assessment: material
components:
  - CMP-ADDR
source_areas:
  - app/code/Secomm/AddressDropdown/etc/db_schema.xml
  - app/code/Secomm/AddressDropdown/etc/db_schema_whitelist.json
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-2PZQKJ][TASK-9AEAQQ] Add recursive hierarchy schema — parent_city_id + code + membership table (declarative, additive)

<!-- CANONICAL TASK RECORD — Phase 1, additive-only. Tier-2 (DB schema migration — AGENTS §11/§12). -->
<!-- TL code review approved 2026-08-26 (batch FEAT-2PZQKJ/YA2C0W; AI pre-review: PRE-REVIEW-2026-08-26-FEAT-2PZQKJ-YA2C0W.md PASS). → completed. -->

## Summary

Thêm chiều đệ quy cho `directory_region_city` (`parent_city_id`, `code`) + bảng membership `secomm_address_profile_location`, thuần additive qua declarative schema — không drop/modify gì, mọi hành vi hiện tại không đổi.

## Mini Spec

### Goal
`directory_region_city` hỗ trợ arbitrary nesting dưới region; profile có bảng membership để kiểm soát dataset theo profile. Là nền cho TASK-NW66H9/J49PRZ.

### Expected Behavior
1. `setup:upgrade` thêm: `parent_city_id` INT UNSIGNED NULL (self-FK `ON DELETE CASCADE`), `code` VARCHAR(64) NULL, index `(region_id, parent_city_id)`, index `(parent_city_id)`, UNIQUE `(region_id, parent_city_id, code)` — tất cả trong db_schema.xml + whitelist cập nhật.
2. Bảng mới `secomm_address_profile_location` (profile_code VARCHAR(64), location_type VARCHAR(16) ∈ {region, city}, location_id INT UNSIGNED, include_subtree TINYINT(1) DEFAULT 1, PK (profile_code, location_type, location_id)).
3. Data hiện tại (3.313 rows, 0 sub_city) KHÔNG đổi — `parent_city_id` NULL = root dưới region (đúng semantics 2-level hiện tại).
4. Không thay đổi nào đối với `directory_city_sub_city*` (Phase 3 xử lý).

### Constraints / Rules
- Declarative schema only (không InstallSchema/patch DDL).
- FK self-reference phải khai báo đúng referenceId; whitelist cập nhật đồng bộ.
- Không phá FK `GHN_ADDR_MAP_CITY_ID_DIR_REGION_CITY_CITY_ID` của GhnAddressMapper (city_id vẫn PK).
- Tier-2 sign-off trước khi chạy trên env có data thật.

### Out of Scope
- Backfill `code` cho data VN hiện có (thuộc TASK-4F1K3N import v2 re-key).
- PHP provider/DTO (TASK-NW66H9/J49PRZ).

### Acceptance Criteria
- AC-001: `bin/magento setup:upgrade` + `declarative:schema:diff` sạch trên DB có data; không warning whitelist.
- AC-002: Insert city có `parent_city_id` trỏ city khác hoạt động; CASCADE delete node cha xoá node con.
- AC-003: UNIQUE chặn duplicate `(region_id, parent, code)`; NULL code không bị chặn (MySQL NULL semantics).
- AC-004: Mọi surface hiện tại (storefront, admin grids, GraphQL cũ, import cũ) regression-clean.

## Approach

Plan: [FEAT-2PZQKJ-implementation-plan](../../plans/FEAT-2PZQKJ-implementation-plan.md) — Phase 1, Step 1.

## Implementation Notes

Đã triển khai 2026-08-25 (chờ TL code review):

- `etc/db_schema.xml`: `directory_region_city` + `parent_city_id` (self-FK CASCADE `DIR_REGION_CITY_PARENT_CITY_ID_DIR_REGION_CITY_CITY_ID`), + `code`, UNIQUE `DIRECTORY_REGION_CITY_REGION_ID_PARENT_CITY_ID_CODE`, index `(region_id, parent_city_id)` + `(parent_city_id)`. Bảng mới `secomm_address_profile_location` (PK 3 cột + index location; polymorphic `location_id` — không FK có chủ đích).
- `etc/db_schema_whitelist.json`: synced — regenerate chính thức + trim về đúng tên object trong DB (Magento derive tên index/constraint từ cột; referenceId trong schema đã align theo).
- **Không đổi PHP nào** — thuần additive, zero behavior change.

Findings trong lúc triển khai:
1. Magento core không có precedent self-FK trong db_schema.xml — đã verify thực tế: self-FK apply + CASCADE hoạt động (smoke test).
2. `setup:db:status` "not up to date" là **pre-existing drift** (verify bằng stash test: bỏ schema của module, status vẫn dirty) — KHÔNG thuộc task này; cần ticket riêng để audit. Bằng chứng module khớp DB: `generate-whitelist --module-name Secomm_AddressDropdown` exit 0 + SHOW CREATE TABLE parity (evidence 01/02).
3. MySQL NULL semantics: UNIQUE không chặn row có `parent_city_id`/`code` NULL — đúng như mini-spec AC-003 chấp nhận (import v2 sẽ bảo đảm code NOT NULL trên data mới).

## Verification

- [x] AC-001: `setup:upgrade` clean; whitelist khớp DB (generate-whitelist exit 0) — evidence 01/02. *(db:status toàn project còn pre-existing drift — không phải của task, xem Finding 2)*
- [x] AC-002: nested insert OK; CASCADE delete parent xoá 3 children — evidence 03 (TEST 1/4)
- [x] AC-003: UNIQUE chặn duplicate `(region_id, parent, code)`; NULL code không bị chặn — evidence 03 (TEST 2/3)
- [x] AC-004: 3.313 rows nguyên vẹn, không residue sau test; không PHP thay đổi; unit test module xanh (2 tests, 3 assertions) — evidence 03 (TEST 6) + 05
- Evidence: `.ai/runtime/evidence/TASK-9AEAQQ/` (01-show-create-table, 02-db-status-after, 03-smoke-tests, 04-whitelist)

## Related records

- Parent feature: FEAT-2PZQKJ
- Decision: DEC-FEAT2PZQKJ-001 (accepted)
