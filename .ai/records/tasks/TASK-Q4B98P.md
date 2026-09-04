---
id: TASK-Q4B98P
type: task
title: 'Operational ↔ canonical identity bridge + DirectoryReferenceGuard extension (bỏ hardcode carrier tables khỏi VietNamAddress)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-FEAT-YA2C0W-canonical-identity-bridge approved 2026-09-03 (user acting as SA/TL)
specification_ref: ../../specs/SPEC-FEAT-YA2C0W-canonical-identity-bridge.md
risk: high
status: in_progress
priority: high
decision_assessment: material
decisions: [DEC-FEATYA2C0W-004]
components:
  - CMP-VNADDR
  - CMP-ADDR
changes_project_state: true
source_areas:
  - app/code/Secomm/VietNamAddress/
  - app/code/Secomm/GhnAddressMapper/
  - app/code/Secomm/Ghtk/
created: 2026-09-03
updated: 2026-09-03
owner: [dev]
related_tickets: [TASK-9394A9, TASK-J9AVGK]
---

# [SLP][FEAT-YA2C0W][TASK-Q4B98P] Operational ↔ canonical identity bridge + DirectoryReferenceGuard extension

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-FEAT-YA2C0W-canonical-identity-bridge.md, FULL, Approved)*

### Context & Objective

Runtime tables đã mang canonical codes (`directory_country_region.code = VN-XX`,
`directory_region_city.code = VNA25-*/VNAP25-*` — verified 2026-09-03) nhưng không có contract nào
chuyển `region_id/city_id ↔ scheme_code/unit_code`; carrier đang reconstruct identity từ name
(WardIdBridge, getCityIdByName). Đồng thời `VnAddressSchemeImporter` hardcode 2 bảng carrier trong
scheme-swap guard (audit P0-1). Objective: bridge ID-based deterministic + guard thành DI extension —
không schema change, không bảng mới.

### Scope / changes

1. `Secomm_VietNamAddress`: `Api/VnOperationalAddressResolverInterface` + `Api/DirectoryReferenceGuardInterface`
   + `Api/Data/VnOperationalIdentityInterface` + implementations + DI (guard array argument trên
   `VnAddressSchemeImporter`, aggregate violations, dry-run report).
2. `Secomm_GhnAddressMapper` + `Secomm_Ghtk`: guard impl cho bảng của mình (move behavior từ
   `assertNoCarrierReferences`), DI registration, `module.xml` += `Secomm_VietNamAddress`
   (+ composer require cho GhnAddressMapper).
3. Xoá `TABLE_GHN_MAPPING`/`TABLE_GHTK_MAPPING` + hardcode khỏi `VietNamAddress`.
4. i18n: guard message vào vi_VN + en_US của module sở hữu guard.

### Acceptance Criteria

AC-1..AC-10 của SPEC-FEAT-YA2C0W-canonical-identity-bridge.md (nguyên văn — không lặp lại ở đây).
Tóm tắt: 2025/PRE_2025 both directions; non-active scheme không fabricate id; resolver gốc không đổi;
0 carrier reference trong VietNamAddress; guard parity + DI registration không sửa VietNamAddress;
drift + NULL-code → explicit result.

### Approach (tóm tắt)

Pure code-lookup: operational→canonical đọc `directory_region_city.code`/`directory_country_region.code`
(scheme từ `active_scheme`, drift-check qua registry `status=CURRENT`); canonical→operational query
ngược chỉ khi scheme active; metadata DTO hydrate qua `VnAddressUnitProviderInterface` (reuse).
Guard: interface `assertSafe(array $regionIds, array $cityIds): void`; importer chạy toàn bộ guards rồi
throw MỘT exception aggregate; thứ tự implement = contract + guards trước, xoá hardcode sau (safety
liên tục trong cùng PR).

### Constraints / Rules

- PHP 8.2+ strict_types; DI constructor; không ObjectManager; parameterized SQL only.
- Không schema/whitelist change; CLI import/validate giữ behavior + output format (dry-run BỔ SUNG guard report).
- `VnAdminAddressResolverInterface` không đổi; không name-based join key; không cache ở lần đầu.
- Có cache → chỉ khi profile chứng minh. String mới vào cả vi_VN.csv + en_US.csv.
- Mode A Tier-2: plan phải được approve trước khi code; pre-review trước TL review.

## Plan

`../plans/TASK-Q4B98P-implementation-plan.md`
