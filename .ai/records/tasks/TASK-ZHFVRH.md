---
id: TASK-ZHFVRH
type: task
title: 'Docs sync — module map 09, BR-002, README/CHANGELOG (AddressDropdown + VietNamAddress)'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: C
specification_level: MINI
spec_status: DRAFT
specification_ref: Embedded Mini-Spec
risk: low
status: proposed
created: 2026-08-25
updated: 2026-08-28
decisions: [DEC-FEAT2PZQKJ-001]
components:
  - CMP-ADDR
  - CMP-VNADDR
source_areas:
  - .ai/project-context/09_MAGENTO_MODULE_MAP.md
  - .ai/project-context/02_BUSINESS_RULES.md
  - app/code/Secomm/AddressDropdown/README.md
  - app/code/Secomm/AddressDropdown/CHANGELOG.md
  - app/code/Secomm/VietNamAddress/README.md
  - app/code/Secomm/VietNamAddress/CHANGELOG.md
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-2PZQKJ][TASK-ZHFVRH] Docs sync — module map 09, BR-002, README/CHANGELOG (AddressDropdown + VietNamAddress)

<!-- CANONICAL TASK RECORD — documentation-only, chạy cuối release. -->

## Summary

Đồng bộ documentation sau refactor: module map bổ sung module Secomm còn thiếu + cập nhật mô tả AddressDropdown/VietNamAddress theo kiến trúc mới; BR-002 cập nhật cascade rule; README/CHANGELOG 2 module theo quy định Secomm-owned modules (AGENTS §7.2 [WARN]).

## Mini Spec

### Goal
Project-context phản ánh đúng thực tế codebase sau FEAT-2PZQKJ.

### Expected Behavior
1. `09_MAGENTO_MODULE_MAP.md`: bổ sung các module Secomm chưa liệt kê (GhnAddressMapper, Ghtk, GiaoHangNhanh, ShippingCore, Ahamove, MoMo, ZaloPay, DisableFileUpload, VietNamMarket — theo `app/etc/config.php`) + cập nhật purpose/risk AddressDropdown (recursive hierarchy + profiles) và VietNamAddress (vn_current profile).
2. `02_BUSINESS_RULES.md` BR-002: cascade mô tả theo profile/schema (bỏ hardcode "city/district→sub-city/ward").
3. README/CHANGELOG 2 module: kiến trúc mới, migration notes, deprecation GraphQL cũ.
4. `12_UPGRADE_NOTES.md` (nếu liên quan): ghi chú upgrade schema phase.

### Constraints / Rules
- Chỉ docs — không đụng code.
- Không copy knowledge trùng lặp (link, không restate — rules/no-duplicate-knowledge).

### Out of Scope
- AGENTS.md §12 overlay (chỉ update khi TL yêu cầu); regeneration toolkit.

### Acceptance Criteria
- AC-001: 09 map khớp 100% module enabled trong `app/etc/config.php` (cross-check script/evidence).
- AC-002: BR-002 mô tả đúng behavior sau Phase 2 cutover.
- AC-003: README/CHANGELOG 2 module có mục version mới của FEAT.

## Approach

Plan: [FEAT-2PZQKJ-implementation-plan](../../plans/FEAT-2PZQKJ-implementation-plan.md) — Step 10 (cuối release).

## Implementation Notes

Chưa triển khai (proposed).

## Verification

- [ ] AC-001..003 — evidence: `.ai/runtime/evidence/TASK-ZHFVRH/`

## Audit findings — 2026-08-28

Nguồn: audit cấu trúc + tính năng 2026-08-28 — docs findings bổ sung vào scope hiện có:

1. **README AddressDropdown marketing-stale**: `app/code/Secomm/AddressDropdown/README.md` không phản ánh engine hiện tại (profiles `address_profiles.xml`, GraphQL `addressLocations`/`addressSchema`, `HierarchyImportService`, recursive hierarchy) — vẫn mô tả city/sub_city legacy như tính năng chính.
2. **Module map** (item 1 — confirm): audit đếm 6 module được document trong `09_MAGENTO_MODULE_MAP.md` vs 17 module `Secomm/*` enabled trong `app/etc/config.php`.
3. **§27 overwrite semantics**: cần doc cho ops — refresh same-scheme = upsert theo code (name bị ghi đè theo dataset, chỉnh tay bị mất); swap = purge. CRUD chỉnh tay hiện có thể bị import ghi đè → note vào README + BR.
4. **Stale doc-comments canonical scheme codes**: description trong `etc/schema.graphqls`, comment `etc/config.xml`, vài unit test references còn `vn_current`/`vn_legacy` như identity (giờ chỉ là status label trong registry) — sync khi quét docs.
5. **i18n anomalies**: `Secomm/AddressDropdown/i18n/vi_VN.csv` rỗng 0 byte (BR-001); `Secomm/VietNamAddress/i18n/en_VN.csv` có ~10 keys không tồn tại trong `en_US.csv` của cùng module; key trùng lặp ví dụ `Please select a Ward/Commune.` — cần sweep dedupe + đối chiếu 3 file (en_US/en_VN/vi_VN) của cả 2 module.
6. **Composer metadata**: `Secomm/AddressDropdown/composer.json` thiếu `version`; `require` `experius/module-extracheckoutaddressfields` không có trong vendor (ghost dependency — verify khi sync).

## Related records

- Parent feature: FEAT-2PZQKJ
