---
id: TASK-F9XJ5G
type: task
title: 'Historical VN address reference-only import (VN_ADMIN_PRE_2025 vào reference layer, không đụng runtime)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: B
specification_level: MINI
spec_status: VALID            # user-directed implementation request 2026-09-04 (behavior + tests + DoD chi tiết trong request); TL code review pending
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-04
updated: 2026-09-04
decisions: [DEC-FEATYA2C0W-003, DEC-FEATYA2C0W-004]
decision_assessment: none-material   # operationalize DEC-003 §4 (reference layer) — không đổi decision nào
components:
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/VietNamAddress/Model/Import/
  - app/code/Secomm/VietNamAddress/Console/
  - app/code/Secomm/VietNamAddress/Test/
changes_project_state: true
changes_architecture: false   # thêm entry point import reference-only vào model DEC-003 hiện có
changes_integration: false
changes_known_limitations: false
last_verified: 2026-09-04
supersedes: []
---

# [SLP][FEAT-YA2C0W][TASK-F9XJ5G] Historical VN address reference-only import (VN_ADMIN_PRE_2025)

<!-- CANONICAL TASK RECORD — reference-only import capability: populate historical scheme vào
     reference layer (unit + registry) mà KHÔNG swap/activate runtime. Request 2026-09-04. -->

## Summary

Website giữ `VN_ADMIN_2025` làm active runtime scheme; hệ thống vẫn cần `VN_ADMIN_PRE_2025` trong reference layer (mapping tương lai, carrier legacy, historical resolution). Thêm chế độ `--reference-only` cho `secomm:vietnam-address:import`: read + validate dataset → snapshot units → registry status `HISTORICAL` — tất cả trong MỘT transaction, idempotent, không đụng runtime directory/config/membership.

## Mini Spec

### Goal

Populate historical scheme (`VN_ADMIN_PRE_2025`) vào reference layer mà không yêu cầu swap runtime — đạt target state: runtime = `VN_ADMIN_2025` only; reference layer chứa cả 2025 + PRE_2025; active scheme không đổi.

### Expected Behavior

1. `bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_PRE_2025 --reference-only`:
   read existing dataset (7-cột, `VnDatasetReader`) → toàn bộ validation hiện có (`VnDatasetValidator`, STOP_ON_ERROR) → trong MỘT transaction: `UnitSnapshotWriter::write` (upsert `secomm_vietnam_address_unit`, identity `scheme_code + code`, VNAP25-…) + registry update `secomm_vietnam_address_scheme` status `HISTORICAL` (khi scheme khác đang CURRENT) → commit.
2. `Model/Import/VnReferenceSchemeImporter` — orchestrator nhỏ tái dùng reader/validator/snapshot writer; KHÔNG có dependency vào hierarchy import/config writer/membership/guards/cache → cấu trúc không thể đụng runtime.
3. `SchemeRegistryUpdater::applyReference(scheme)`: upsert metadata từ catalog; status = HISTORICAL cho scheme không đang CURRENT; nếu registry row của chính scheme đang CURRENT → giữ CURRENT (chỉ refresh metadata); KHÔNG BAO GIỜ đụng status của scheme khác.
4. CLI: `--reference-only` mutually exclusive với `--swap`/`--rebuild` (reject rõ ràng); cho phép kết hợp `--dry-run` (validate only, không write). Help text hiển thị canonical codes `VN_ADMIN_2025 | VN_ADMIN_PRE_2025` (legacy `vn_current|vn_legacy` vẫn bị reject kèm hint, docs ưu tiên canonical).
5. `VnImportReport` thêm `referenceOnly` + `registryStatus` (in ra CLI + toArray).
6. `Setup/Patch/Data/ImportVnAdminPre2025ReferencePatch` — reference-only import tự chạy qua `setup:upgrade` (amended 2026-09-04 theo user: deploy không cần manual CLI): deps `[ImportVnAdmin2025SchemePatch]`, idempotent, validation fail → setup fail loudly (cùng contract bootstrap patch); constructor chỉ nhận `VnReferenceSchemeImporter` → cấu trúc không thể đụng runtime importer.

### Constraints / Rules

- KHÔNG modify/purge/re-key/reseed: `directory_country_region`, `directory_country_region_name`, `directory_region_city`, `directory_region_city_name`, `secomm_address_profile_location`; KHÔNG modify `secomm_vietnam_address/general/active_scheme`, `address/profiles/mapping`; KHÔNG trigger swap/rebuild/runtime purge/re-key/carrier guard.
- Reference import không demote CURRENT scheme; không có implicit status transition nào ngoài (missing|HISTORICAL → HISTORICAL) và (CURRENT → CURRENT keep).
- Idempotent: chạy lại không tạo duplicate (UNIQUE(scheme_code, code) upsert); metadata/display fields được update hợp lệ.
- Transactional validate → snapshot → registry → commit; fail ở bất kỳ bước = no partial unit snapshot, no partial registry update.
- Identity là `scheme_code + unit_code` — không dùng region_id/city_id cho historical mapping.
- Không clean cache (reference layer không nằm trên runtime render path — VnAddressUnitProvider đọc trực tiếp, không có cache layer).

### Out of Scope

VietMap/Google integration; mapping data `2025 ↔ PRE_2025` + `ExternalAddressMappingProviderInterface`; GHN/GHTK/Ahamove refactor; ShippingCore changes; carrier API profiles; runtime disambiguation; populate `secomm_vietnam_address_mapping`.

### Acceptance Criteria

- AC-001 (Case 1): runtime `VN_ADMIN_2025` + chạy reference-only PRE_2025 → runtime không đổi (region/city row counts + IDs giữ nguyên); registry `VN_ADMIN_2025=CURRENT, VN_ADMIN_PRE_2025=HISTORICAL`; unit table có units của cả 2 scheme.
- AC-002 (Case 2): chạy reference-only PRE_2025 lần 2 → không duplicate unit rows; runtime + CURRENT scheme không đổi.
- AC-003 (Case 3): dataset invalid → không có reference write, không registry mutation, không runtime mutation.
- AC-004 (Case 4): `--reference-only --swap` và `--reference-only --rebuild` bị reject rõ ràng (exit failure).
- AC-005 (Case 5): fresh environment (chưa có CURRENT/runtime VN data) → reference-only PRE_2025 ghi HISTORICAL, KHÔNG tự activate (active_scheme không đổi).
- AC-006: reference-only trên chính scheme CURRENT → status giữ CURRENT, snapshot refresh idempotent, runtime không đổi.

## Approach

Flag `--reference-only` trên command hiện có (thay vì command riêng): command này đã là điểm vào duy nhất của scheme import với scheme gate + report printing; tách command sẽ duplicate scheme validation + DI registration mà không thêm governance. Orchestrator mới `VnReferenceSchemeImporter` tách khỏi `VnAddressSchemeImporter` (853 dòng, runtime semantics) để dependency set tự chứng minh "không đụng runtime". Registry write gom về `SchemeRegistryUpdater` (registry abstraction hiện có).

## Verification

- [x] AC-001..006 — 2026-09-04: unit tests 139/421 xanh (+18 mới: `VnReferenceSchemeImporterTest` 7, `SchemeRegistryUpdaterTest` +4, `ImportVnAddressSchemeCommandTest` 8 — Case 4 mutual exclusion); AddressDropdown 66/147 xanh (regression).
- [x] DB verification dev (2026-09-04): registry `VN_ADMIN_2025=CURRENT / VN_ADMIN_PRE_2025=HISTORICAL`; unit table 2025 {L1:34, L2:3321} + PRE_2025 {L1:63, L2:699, L3:10595} = 11.357; `active_scheme` vẫn `VN_ADMIN_2025`; runtime 34 regions / max_region_id 1224 / 3.321 cities / max_city_id 6648 / membership 34 rows — KHÔNG ĐỔI qua 3 lần chạy (PRE_2025 ×2 idempotent + CURRENT ×1 kept-status). CLI: `--reference-only --swap|--rebuild` exit 1 + reject; `--scheme vn_legacy` reject + hint canonical. Evidence `.ai/runtime/evidence/TASK-F9XJ5G/`.
- [x] AC-007 (patch, amended 2026-09-04): `ImportVnAdminPre2025ReferencePatch` applied qua `setup:upgrade` thật trên dev (patch_list có entry); DB state sau upgrade identical với pre-upgrade (registry/units/runtime/config) — idempotent trên DB đã có reference data; unit tests +4 (143/426 xanh). Fresh-install path: patch chạy sau `ImportVnAdmin2025SchemePatch` theo deps.

**Status: dev complete + pre-review pending → TL review (Tier-2: CMP-VNADDR/CMP-ADDR).**

## Related records

- Parent FEAT-YA2C0W; Decision DEC-FEATYA2C0W-003 (reference layer + status label model), DEC-FEATYA2C0W-004 (guard D7 — không áp dụng cho reference-only vì không purge).
