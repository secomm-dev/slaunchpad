---
id: TASK-R83FXW
type: task
title: 'Register vn_current + vn_legacy profiles, config mapping, membership seeding (vn_current)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: completed
created: 2026-08-25
updated: 2026-08-26
decisions: [DEC-FEATYA2C0W-001]
components:
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/VietNamAddress/etc/
  - app/code/Secomm/VietNamAddress/i18n/
  - app/code/Secomm/VietNamAddress/Setup/
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-YA2C0W][TASK-R83FXW] Register vn_current + vn_legacy profiles, config mapping, membership seeding (vn_current)

<!-- CANONICAL TASK RECORD — dùng AddressProfileResolver/SchemaProvider contracts (TASK-NW66H9) — KHÔNG sửa generic module. -->

## Summary

VietNamAddress khai báo 2 profile trong `etc/address_profiles.xml` của riêng nó + bind config `VN → vn_current` + seed membership `vn_current` claim 34 region mới (subtree). Label là translation keys dịch trong i18n 3 locale sẵn có.

## Mini Spec

### Goal
Hai representation hành chính VN khả dụng qua generic profile engine; dataset current được claim chính thức.

### Expected Behavior
1. `vn_current`: region label key `Province/City` + city depth 1 label key `Ward/Commune` (đúng schema 2-level hiện hành).
2. `vn_legacy`: region label key `Province/City` + city depth 1 `District/Town` + city depth 2 `Ward/Commune` (labels: Quận/Huyện/Thị xã/Thành phố → District/Town; Phường/Xã/Thị trấn → Ward/Commune).
3. i18n: keys thêm vào vi_VN.csv (Tỉnh/Thành phố; Phường/Xã/Đặc khu; Quận/Huyện/Thị xã/Thành phố; Phường/Xã/Thị trấn) + en_VN.csv + en_US.csv.
4. Data patch idempotent: insert membership `secomm_address_profile_location` (vn_current, region, 34 region_id VN hiện tại, include_subtree=1) — không trùng khi re-run.
5. Config mapping: patch set default `address/profiles/mapping` = `a:1:{s:2:"VN";s:10:"vn_current";}` (chỉ khi chưa có giá trị — không đè config merchant).
6. `AddressSchemaProvider::getSchema('vn_current'|'vn_legacy')` trả đúng số levels (verify qua unit/integration test hoặc smoke bootstrap).

### Constraints / Rules
- Không sửa Secomm_AddressDropdown (chỉ consume contracts).
- Label keys theo convention module: key English default → dịch qua i18n (DEC-FEATJSZQV3-003).
- Patch idempotent; không đè data/config có sẵn.

### Out of Scope
- Legacy dataset import (TASK-ADT94K); relations (TASK-X0XKH4); renderer.

### Acceptance Criteria
- AC-001: getSchema('vn_current') = 2 levels, getSchema('vn_legacy') = 3 levels, sort đúng.
- AC-002: resolve('VN') trả profile vn_current sau khi config mapping set (smoke bootstrap).
- AC-003: 34 membership rows, idempotent re-run không nhân bản.
- AC-004: Label keys dịch đủ 3 locale (grep evidence).

## Approach

Plan: [FEAT-YA2C0W-implementation-plan](../../plans/FEAT-YA2C0W-implementation-plan.md) — Step 1.

## Implementation Notes

Đã triển khai 2026-08-25 (chờ TL code review):

- `etc/address_profiles.xml`: 2 profiles qua generic engine — labels/placeholders là translation keys (`Province/City`, `Ward/Commune`, `District/Town`, `Legacy Ward/Commune` — key riêng cho ward legacy để dịch khác "Phường/Xã/Đặc khu" vs "Phường/Xã/Thị trấn").
- `Setup/Patch/Data/SeedVnProfileMembership.php`: select VN regions `code NOT LIKE 'L-%'` (future-proof cho legacy import) → `insertOnDuplicate` lên PK membership (idempotent); set config default `address/profiles/mapping` = `a:1:{s:2:"VN";s:10:"vn_current";}` **chỉ khi chưa có** (không đè config merchant); dependencies: InstallVietNamAddressPatch (34 regions phải tồn tại trước); có `revert()`.
- i18n: +5 keys vi_VN, +6 en_VN, +7 en_US; cập nhật `"Ward/Commune"` vi_VN → "Phường/Xã/Đặc khu" (PART 2 semantics). Ghi chú minor: 1 duplicate key placeholder cũ (giá trị trùng) tồn tại từ 1.1.0 — vô hại, không đụng (scope).
- **TL code review approved 2026-08-26** (batch; pre-review PASS) → completed.

## Verification

- [x] AC-001: getSchema('vn_current') = 2 levels, getSchema('vn_legacy') = 3 levels, sort đúng — evidence 01 (step 1-2)
- [x] AC-002: resolve('VN') = 'vn_current' sau config mapping; resolve('AU') = null — evidence 01 (step 3-4)
- [x] AC-003: 34 membership rows, 0 orphan; replay upsert ×2 count vẫn 34 — evidence 01 (step 5-6)
- [x] AC-004: keys dịch đủ 3 locale — evidence 02 (grep vi_VN/en_VN/en_US)
- Evidence: `.ai/runtime/evidence/TASK-R83FXW/` (01-smoke + php, 02-i18n-and-diff)

## Related records

- Parent feature: FEAT-YA2C0W
- Decision: DEC-FEATYA2C0W-001 (accepted — D-R2 prefix codes liên quan gián tiếp)
