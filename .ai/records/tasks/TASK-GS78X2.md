---
id: TASK-GS78X2
type: task
title: 'Replace VN_ADMIN_PRE_2025 canonical dataset bằng snapshot cuối 2024 — Data Patch RefreshVnAdminPre2025Snapshot2024 (rebuild unit + mapping reference layer)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: C
specification_level: MINI
spec_status: VALID            # user-directed implementation directive 2026-09-08 (patch flow + cleanup rules + validation + AC trong request); Mini-Spec embedded đủ 5 sections
specification_ref: null       # Mini-Spec embedded (Mode C)
risk: medium                  # destructive cleanup trên reference layer (TRUNCATE-equivalent + scheme-scoped delete) nhưng: scope chặt, transaction-wrapped, reference-layer only
status: in_progress
priority: high
decisions: [DEC-FEATYA2C0W-003]
decision_assessment: non-material
decision_approval_summary:
  total: 1
  pending_approval: []
  approved: [DEC-FEATYA2C0W-003]
  rejected: []
  superseded: []
  last_synced: '2026-09-08'
verified_against_commit: null
components:
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/VietNamAddress/
changes_project_state: true       # reference-layer data replaced (unit PRE_2025 + mapping rebuilt)
changes_architecture: false
changes_integration: false
changes_known_limitations: true
last_verified: '2026-09-08'
supersedes: []
work_items: [TASK-GS78X2, FEAT-YA2C0W]
related_tickets: [TASK-F9XJ5G, TASK-J9AVGK, TASK-NDSZ7V, TASK-Q4B98P, SPIKE-9Z231Q]
---

# [SLP][FEAT-YA2C0W][TASK-GS78X2] Replace VN_ADMIN_PRE_2025 canonical dataset bằng snapshot cuối 2024 — Data Patch RefreshVnAdminPre2025Snapshot2024 (rebuild unit + mapping reference layer)

## Mini Spec

### Goal

`VN_ADMIN_PRE_2025` (reference layer) phải phản ánh đúng cấu trúc hành chính tồn tại cuối 2024:
63 province + 696 district + 10,035 ward. Thay dataset hiện tại (63/699/10,595) bằng 2 file
snapshot mới trong `Secomm_VietNamAddress/Files/` qua MỘT Data Patch mới (không sửa patch cũ),
chạy tự động qua `setup:upgrade`. `scheme_code` giữ `VN_ADMIN_PRE_2025` — snapshot là VERSION
của dataset, không phải scheme mới.

### Expected Behavior

1. Data patch `RefreshVnAdminPre2025Snapshot2024` orchestrate đúng thứ tự:
   cleanup mapping (toàn bảng) → delete units `scheme_code='VN_ADMIN_PRE_2025'` (scheme-scoped)
   → import units từ `VN_ADMIN_PRE_2025_SNAPSHOT_2024_import.csv` → import mapping từ
   `VN_ADMIN_PRE_2025_TO_2025_SNAPSHOT_2024_mapping.csv` → validate sau import → fail loud
   (exception, không silently continue).
2. Reuse import stack hiện có: `VnDatasetReader` (file override qua DI `$files` — extension
   point sẵn có), `VnDatasetValidator` (bổ sung DI `$expectedOverrides` cùng pattern — counts/
   collision của snapshot khác catalog mặc định 63/699/10,595 + 19/38), `VnReferenceSchemeImporter`
   (virtual type với reader/validator snapshot), `VnMappingImporter` (validate ALL + upsert),
   `UnitSnapshotWriter`, `SchemeRegistryUpdater`. Patch chỉ orchestration + post-import validation.
3. Transaction: toàn bộ DML (cleanup + import + validate) trong MỘT transaction của patch
   (nested với transaction của `VnReferenceSchemeImporter` — Magento savepoint/decrement
   semantics); failure `setup:upgrade` → rollback, DB về trạng thái trước patch, không
   half-imported.
4. Validation bắt buộc: unit L1=63, L2(district)=696, L3(ward)=10,035; không duplicate code
   (dataset validator + UNIQUE(scheme,code) + DB recheck); không orphan parent_code (dataset
   validator 2-pass + DB recheck); mapping: source phải tồn tại PRE_2025 unit, target phải tồn
   tại 2025 unit, không duplicate edge (VnMappingValidator import-time + `validate(null)`
   DB-mode sau import). Thất bại → `VnImportValidationException` (extends `LocalizedException`,
   convention module).
5. VN_ADMIN_2025 (unit + directory), `directory_country_region`, `directory_region_city`,
   storefront/customer data, config active_scheme, membership: KHÔNG bị đụng (reference-only
   importer đảm bảo structural).

### Constraints / Rules

- KHÔNG sửa/xóa 3 patch cũ (`ImportVnAdmin2025SchemePatch`, `ImportVnAdminPre2025ReferencePatch`,
  `ImportVnAdminPre2025To2025MappingPatch`) hay bất kỳ entry `patch_list` nào; patch mới declare
  `getDependencies() = [ImportVnAdminPre2025To2025MappingPatch]` (chạy sau legacy seed trên
  fresh install).
- KHÔNG hard-code absolute path — resolve qua `ComponentRegistrarInterface` (pattern patch
  mapping hiện có) / reader (ComponentRegistrar nội bộ).
- Không regenerate `VNAP25-*` code runtime — import đúng `code` trong CSV (CSV = canonical
  source; `UnitSnapshotWriter` upsert immutable code).
- Cleanup mapping: directive cho phép `TRUNCATE`; implement bằng DELETE trong transaction
  (TRUNCATE = DDL implicit commit phá atomicity — conflict với mục tiêu failure-safety của
  chính directive; report deviation).
- Không đổi scheme_code / không đụng AddressDropdown / storefront / directory / VietMap / GHN
  / provider mapping / admin UI / generic migration framework.
- DI virtual types trong `etc/di.xml` — không subclass trừ khi cần logic mới.

### Out of Scope

Directory runtime tables + customer/storefront forms · VN_ADMIN_2025 data · GHN/provider
mapping (SPIKE-9Z231Q line) · AddressDropdown refactor · data fixes ngoài 2 file snapshot ·
patch list manipulation · retry/fallback logic.

### Acceptance Criteria

- **AC-1**: Sau `setup:upgrade`: unit `VN_ADMIN_PRE_2025` = L1 63, L2 696, L3 10,035; mapping
  = 10,418 edges từ file snapshot (rebuild toàn bộ).
- **AC-2**: `VN_ADMIN_2025` unit (34/3,321) + `directory_country_region` (34) +
  `directory_region_city` (3,321) unchanged; active_scheme config unchanged.
- **AC-3**: Không orphan hierarchy (DB recheck 0); không invalid mapping reference
  (`VnMappingImporter::validate(null)` errors = []); không duplicate unit code/edge.
- **AC-4**: Patch có idempotency (re-run sau khi xóa patch entry local = upsert tương đương,
  counts ổn định); validation fail-path: expected-counts sai → throw, DB unchanged (test
  transaction rollback).
- **AC-5**: Compile + unit suite Secomm không thêm failure mới; validator project 0 finding
  mới; evidence ghi nhận.

## Approach

Audit cho thấy extension point đúng: `VnDatasetReader` có DI `$files` override (dành sẵn cho
"alternate datasets"); `VnDatasetValidator` chưa có — bổ sung `$expectedOverrides` cùng pattern
(additive, backward compatible). `VnReferenceSchemeImporter` được virtualize (reader + validator
snapshot) qua di.xml; patch inject virtual importer + `VnMappingImporter` + `ResourceConnection`
+ `ComponentRegistrarInterface`. Thứ tự: record → validator override param → di.xml → patch →
compile → test (baseline → setup:upgrade → verify AC → idempotency → fail-path) → governance.

## Verification

- [ ] **AC-1 — BLOCKED trên data finding (F)**: snapshot CSV vi phạm uniqueness contract
  `(region, parent_code, name_vi)` — 6 cặp đơn vị cấp huyện trùng tên trong cùng tỉnh (cấu
  trúc thật cuối 2024: TP/huyện Cao Lãnh, Hồng Ngự, Kỳ Anh, Long Mỹ, Cai Lậy, Duyên Hải) mà
  file cũ chỉ giữ 1 trong mỗi cặp. Patch fail-loud đúng thiết kế; apply-path chờ TL/SA chọn:
  (A) regenerate file với disambiguation suffix (pattern ward-collision hiện có), hoặc
  (B) phê duyệt nới contract cho snapshot. Counts L1/L2/L3 + collision override ĐÃ verify
  khớp (63/696/10,035 + 18/36) trong output validation.
- [x] **AC-2 — verified trên failure-rollback**: sau `setup:upgrade` fail, DB nguyên vẹn
  baseline (unit 2025 = 34/3,321 · directory region = 34 · city = 3,321 · mapping = 10,064 ·
  unit PRE = 63/699/10,595); patch_list KHÔNG ghi entry (aborted cleanly). Sẽ re-verify sau apply.
- [x] **AC-3 (fail-path phần) — verified**: validation errors hiển thị tường minh
  (VnImportValidationException với error list); DB-mode checks đã implement trong patch.
- [x] **AC-4 (fail-safety phần) — PROVEN trên failure thật**: transaction rollback hoàn toàn
  (không half-imported; patch_list không ghi). Idempotency re-run: chờ sau apply.
- [x] **AC-5 — verified**: compile pass; VietNamAddress unit suite 148 tests OK (0 failure);
  full suite 15 errors = 8 PromotionMaxDiscount + 7 Tracking (pre-existing debt, 0 liên quan);
  validator project chạy riêng cuối task.

**Finding F (delivered 2026-09-08, chờ TL/SA)** — chi tiết + evidence:
`.ai/evidence/TASK-GS78X2/test-result.md`. Patch code hoàn chỉnh + fail-loud/rollback proven;
không tự sửa CSV, không tự nới contract (Tier-2 data contract).
