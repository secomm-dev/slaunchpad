---
id: TASK-ADT94K
type: task
title: 'Phase B — Versioned scheme import foundation (VN_ADMIN_* codes, 7-col datasets, active_scheme config, profiles rename)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: MINI
spec_status: VALID            # approved via plan 2026-08-27 (user acting as SA/TL)
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-08-25
updated: 2026-08-28
decisions: [DEC-FEATYA2C0W-002, DEC-FEATYA2C0W-003]
decision_assessment: material
components:
  - CMP-VNADDR
  - CMP-ADDR
source_areas:
  - app/code/Secomm/VietNamAddress/Files/
  - app/code/Secomm/VietNamAddress/Model/
  - app/code/Secomm/VietNamAddress/Model/Import/
  - app/code/Secomm/VietNamAddress/Model/Scheme/
  - app/code/Secomm/VietNamAddress/Setup/
  - app/code/Secomm/VietNamAddress/Console/
  - app/code/Secomm/VietNamAddress/etc/
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: true
last_verified: 2026-08-28
supersedes: []
---

# [SLP][FEAT-YA2C0W][TASK-ADT94K] Phase B — Versioned scheme import foundation (VN_ADMIN_*)

<!-- CANONICAL TASK RECORD — RE-SCOPED 2026-08-27 (2nd) theo DEC-FEATYA2C0W-003: scheme codes VN_ADMIN_2025/VN_ADMIN_PRE_2025
     thay vn_current/vn_legacy; datasets 7 cột tự chứa region names; config active_scheme mới; swap model giữ (DEC-002). -->

## Summary

Đổi nền import sang scheme identity versioned: catalog `VnSchemes` (hằng số + files + counts + aliases), reader 7 cột (chấp nhận cả 5), validator theo scheme mới (bỏ ASCII rule, thêm region consistency), importer ghi thêm `secomm_vietnam_address/general/active_scheme`, profiles rename `vn_admin_2025`/`vn_admin_pre_2025` (alias upgrade không purge), CLI chỉ nhận canonical codes, patch rename `ImportVnAdmin2025SchemePatch`.

## Mini Spec

### Goal
`Secomm_VietnamAddress` import 2 dataset versioned (`VN_ADMIN_2025` 2 cấp, `VN_ADMIN_PRE_2025` 3 cấp) với identity bất biến; active scheme do config tường minh; upgrade từ trạng thái dev (vn_current) không purge.

### Expected Behavior
1. `Model/Scheme/VnSchemes.php`: constants + `catalog()` (profile_code, level_count, unit_file, code_pattern, counts, collision, legacy aliases) + `schemeForProfile()` + `assertKnown()`.
2. Config: `etc/config.xml` default `VN_ADMIN_2025`; `etc/adminhtml/system.xml` field `secomm_vietnam_address/general/active_scheme` (source từ registry, label kèm status); backend model chặn flip sang scheme chưa cài; `etc/acl.xml`.
3. Reader: header 7 cột `region_code,region_name_vi,region_name_en,code,parent_code,name_vi,name_en` (chấp nhận 5 cột cũ); tách region rows từ 7 cột (verify 1 tên biến thể/code); BOM/CRLF/NFC.
4. Validator: counts {34/3321/0, 63/699/10595}; code pattern theo scheme; collision 19/38 (PRE); 2025 cấm depth-2; KHÔNG reject name_en non-ASCII (tên dân tộc hợp lệ); reject ký tự ẩn; region consistency + coverage.
5. Importer: alias map (membership `vn_current` → VN_ADMIN_2025 → same-scheme refresh, re-key bridge bảo toàn city_id); FK guard thêm `secomm_ghtk_address_map`; synthesize entity_type; ghi active_scheme + profile mapping SAU import thành công.
6. CLI: `--scheme VN_ADMIN_2025|VN_ADMIN_PRE_2025` (giá trị cũ rejected kèm gợi ý); `[--swap] [--dry-run]`.
7. Patch: `ImportVnAdmin2025SchemePatch` (rename từ ImportVnCurrentAddressDatasetPatch — uncommitted/never-ran).

### Constraints / Rules
- Swap model DEC-002 + naming DEC-003; runtime bảng không thêm cột; directory_country_region Magento-owned.
- `Secomm_AddressDropdown` không đổi (`HierarchyImportService` giữ nguyên).
- Shipped patches (Install/2Level) không đụng; SeedVnProfileMembership (uncommitted) sửa tại chỗ.
- Unit codes từ dataset (immutable), không sinh từ tên; không parse scheme từ code prefix.
- §7 ordering: active_scheme/membership/profile config chỉ đổi SAU import success.

### Out of Scope
- Phase C (registry + unit history — TASK-9394A9); Phase D (mapping + resolver — TASK-J9AVGK); carrier; §23 snapshot.

### Acceptance Criteria
- AC-001: dry-run/import `VN_ADMIN_2025` trên dev DB = same-scheme refresh qua alias (không purge, re-key 3311/3311, city_id bảo toàn).
- AC-002: `VN_ADMIN_PRE_2025` thiếu `--swap` bị từ chối; với `--swap` purge + import 63/699/10.595 + config/membership đúng.
- AC-003: 19 nhóm/38 collision = 38 entity riêng; UTF-8 byte-exact; dòng bẩn (ZWSP) bị validator chặn.
- AC-004: admin không flip active_scheme sang scheme chưa cài (backend model); CLI giá trị cũ bị reject.
- AC-005: entity v1 + shipped patches nguyên vẹn; GraphQL addressSchema 2/3 levels theo scheme active.

## Approach

Plan approved 2026-08-27 (`fancy-exploring-token.md` §5). tái dùng generic `HierarchyImportService` nguyên vẹn.

## Implementation Notes

- Dev DB: 3.313 rows code=NULL + membership vn_current (alias healed lần import đầu).
- File 2025 có 1 dòng bẩn line 2704 (ZWSP×2 + "Commune" thừa) — USER sửa file nguồn (action item).
- `generated/metadata` DI compile: regenerate sau khi thêm DI mới.

### Session 2026-08-28 — bootstrap từ empty VN DB (user request, continuation)

- **Root cause 34 errors**: `VnDatasetValidator::checkRegions` pattern `/^\d{2}$/` vs CSV shipped `VN-XX` → 34/34 region rows reject ở bước validate (không đụng DB). Fix: `VnSchemes::REGION_CODE_PATTERN='/^VN-\d{2}$/'` + regression test đọc 2 CSV shipped thật.
- **Region re-key bridge (thêm)**: legacy regions code 2-digit trần theo đánh số chính phủ (`01`=Hà Nội) ≠ dataset alphabet (`VN-01`=An Giang) — KHÔNG prefix-transform được; match theo name normalised (enKey primary/viKey fallback), chạy TRƯỚC city bridge → region_id/city_id bảo toàn, fresh-install không còn duplicate 34 regions.
- **All-or-nothing**: toàn bộ write phase trong 1 transaction (nested chunk commits flatten — `Pdo\Mysql` nested semantics đã verify trong vendor).
- **Reconciliation**: stray-region cleanup (FK-guarded như swap) + orphan membership sweep (claims id chết — 68 rows trên dev DB được dọn).
- **Error reporting**: `VnImportValidationException` nhúng 10 lỗi đầu + "(+N more)" vào message (setup:upgrade thấy lý do thật).
- Unit snapshot `VN_ADMIN_2025`=3.389 gồm 34 artifact region codes cũ (`01`,`32`… từ iteration 1.3.x) — giữ theo design accumulate; `--rebuild` để dọn nếu muốn (dev DB có 1 customer address + 12 orders nên không tự rebuild).

## Verification

- [x] AC-001..005 — 2026-08-27 dev DB: dry-run 3313/3313 re-key; setup:upgrade patch applied; 34/3321/0-stale/membership 34/snapshot 3355/registry CURRENT/config đúng; swap PRE (63+699+10595) + swap back sạch; 38/19 collision; GraphQL schema+locations đúng; 121 unit tests xanh. Evidence: `.ai/runtime/evidence/TASK-ADT94K/` (+ 9394A9, J9AVGK cho phases C/D).
- [x] Bootstrap fix 2026-08-28 — empty VN DB (0 regions/0 cities/68 orphan membership) → `setup:upgrade` tạo đúng 34 regions (`VN-01..VN-34`) + 3.321 VNA25 cities + membership 34 + 0 orphan; rerun CLI = 0 inserts (34/3.321 updated, no dup codes); invalid-dataset demo → 9 lỗi chi tiết trong exception message, nothing written. 84 unit tests xanh (VietNamAddress) + 48 (AddressDropdown Unit). Evidence: `04-bootstrap-empty-db-2026-08-28.md` + `04a/04b/04c`.

**Status: dev + pre-review PASS — chờ TL review (Tier-2).**

## Audit findings — 2026-08-28

Nguồn: audit cấu trúc + tính năng 2026-08-28 — defect bổ sung trên pipeline import (task đang `in_progress`, phần này chưa nằm trong AC hiện có):

1. **Re-key asymmetric**: `Model/Import/VnAddressSchemeImporter.php:111-114` — city bridge (bảo toàn `city_id`) chỉ chạy cho `VN_ADMIN_2025`; `VN_ADMIN_PRE_2025` qua `--swap` không có bridge city tương ứng (region bridge 1.4.1 không phủ city) → khác biệt giữa 2 scheme cần documented hoặc symmetric fix.
2. **Swallowed exception trong patch**: `Setup/Patch/Data/InstallVietNamAddressPatch.php:128-130` — try/catch nuốt exception → `setup:upgrade` báo success dù install data fail (che root cause).
3. **CLI help stale**: `Console/Command/ImportVnAddressSchemeCommand.php:47-55` — help text còn ghi `vn_current|vn_legacy` (đã bị reject từ 1.4.0).
4. **Composer version lệch**: `composer.json` version `1.0.0` vs CHANGELOG `1.4.1` — sync.
5. **Raw `serialize()`**: `Setup/Patch/Data/SeedVnProfileMembership.php:76` — nên dùng `\Magento\Framework\Serialize\SerializerInterface` (JSON) theo Magento convention.
6. **`detectInstalledSchemes` inconsistency**: `Model/Import/VnAddressSchemeImporter.php:752-774` — install-state filter theo `is_default` trong khi hướng dài hạn là bỏ marker này (Out of Scope TASK-K09G8Y) → chốt nguồn truth trước khi marker bị migrate.
7. **Undeclared dependency**: module dùng locale `en_VN` do `Secomm_VietNamMarket` đăng ký nhưng không declare trong `composer.json` / `module.xml` sequence.
8. **`CurrentDatasetRekeyMatcher` stateful**: state match tích lũy giữa các lần gọi trong cùng process — nếu importer chạy nhiều lần trong 1 CLI/session pha cần explicit reset; xem xét stateless.

## Related records

- Parent: FEAT-YA2C0W; Decisions: DEC-FEATYA2C0W-002 (swap), DEC-FEATYA2C0W-003 (versioned naming)
- Next: TASK-9394A9 (Phase C), TASK-J9AVGK (Phase D); pull-forward từ TASK-4F1K3N (note đã ghi trong record đó)
