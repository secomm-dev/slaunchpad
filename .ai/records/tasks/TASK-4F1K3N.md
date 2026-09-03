---
id: TASK-4F1K3N
type: task
title: 'Import v2 multi-level (dual-format) + VietNamAddress re-key + vn_current profile'
project_code: SLP
parent: {type: feature, id: FEAT-2PZQKJ}
mode: B
specification_level: MINI
spec_status: DRAFT
specification_ref: Embedded Mini-Spec
risk: medium
status: proposed
created: 2026-08-25
updated: 2026-08-25
decisions: [DEC-FEAT2PZQKJ-001]
components:
  - CMP-ADDR
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/AddressDropdown/Model/Import/
  - app/code/Secomm/VietNamAddress/
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-2PZQKJ][TASK-4F1K3N] Import v2 multi-level (dual-format) + VietNamAddress re-key + vn_current profile

<!-- CANONICAL TASK RECORD — Phase 2. Data pipeline + country adapter. -->

> **Scope pull-forward 2026-08-27 (TASK-ADT94K / DEC-FEATYA2C0W-002)**: phần lõi "code-identified hierarchy upsert" đã được implement tại `Secomm\AddressDropdown\Model\Import\Hierarchy\HierarchyImportService` (`Api\HierarchyAddressImportInterface`) như một service riêng — KHÔNG mở rộng entity 9 cột. Re-key VietNamAddress + dataset import cũng đã làm ở TASK-ADT94K (matcher `CurrentDatasetRekeyMatcher`, codes `VNC-/VNL-` do dataset cung cấp — không sinh slug). Phần còn lại của task này (dual-format bên trong ImportExport entity, membership sinh từ import argument) **parked** — chỉ mở lại khi có nhu cầu import qua admin ImportExport UI.

## Summary

Import entity `address_dropdown` hỗ trợ format mới multi-level (cột `parent_code`/`parent_city_code` thay 2 cột sub_city) song song format cũ 9 cột (map thành depth-1/depth-2); data patch VietNamAddress re-import 2-level dataset gán `code`.

> **Scope transfer 2026-08-25 (FEAT-YA2C0W / TASK-S0M7YC)**: phần "khai báo profile `vn_current` + membership seeding + config mapping" đã chuyển sang FEAT-YA2C0W (TASK-R83FXW) — task này giữ thuần **generic import v2 machinery + re-key**. Lý do: profile registration là trách nhiệm country adapter, gom cùng relations/resolver trong một FEAT để mạch lạc boundary.

## Mini Spec

### Goal
Dữ liệu hierarchy import được ở arbitrary depth với `code` ổn định; VN dataset hiện tại có identity chuẩn cho membership + carrier references.

### Expected Behavior
1. Import v2 nhận format mới: cột cha tham chiếu qua `code` (region_code + parent city code) — không phụ thuộc tên; format cũ 9 cột vẫn hợp lệ (sub_city → city depth 2).
2. Dedupe/UPDATE key chuyển sang `(region_id, parent_city_id, code)` thay name-hack `CONVERT(? USING binary)`.
3. Import sinh membership rows theo profile config (import argument) — subtree-claim ở node gốc.
4. Data patch mới trong VietNamAddress: re-key `VN_Address_2Level.csv` với `code` (slug), idempotent, KHÔNG tự chạy lại CSV cũ không code (patch cũ đã applied — dùng behavior ADD/UPDATE).
5. ~~`Secomm/VietNamAddress/etc/address_profiles.xml`: profile `vn_current`...~~ → **MOVED to TASK-R83FXW (FEAT-YA2C0W)** — xem scope transfer note ở Summary. Task này chỉ bảo đảm import v2 sinh được data mà profile đó consume (codes + membership-compatible rows).
6. Sample file mới + CHANGELOG cả 2 module.

### Constraints / Rules
- Import giữ STOP_ON_ERROR strategy; row lỗi báo rõ dòng/cột.
- Không xoá data cũ khi re-key (UPDATE city_id giữ nguyên — FK GhnAddressMapper không đổi).
- BR-001: chuỗi mới song ngữ.

### Out of Scope
- `vn_legacy` profile + dataset 3 cấp cũ (feature riêng khi cần); membership admin UI.

### Acceptance Criteria
- AC-001: Import format cũ + mới trên cùng entity; format mới đạt depth 3 fixture.
- AC-002: Sau re-key: mọi VN city có `code` unique; `city_id` không đổi (so sánh trước/sau).
- AC-003: `vn_current` render đúng trên renderer mới (nối TASK-3T3NSV); country khác không bị ảnh hưởng.
- AC-004: Import 3.3k rows < thời gian baseline hiện tại (không slower per-row).

## Approach

Plan: [FEAT-2PZQKJ-implementation-plan](../../plans/FEAT-2PZQKJ-implementation-plan.md) — Phase 2, Step 5.

## Implementation Notes

Chưa triển khai (proposed).

## Verification

- [ ] AC-001..004 — evidence: `.ai/runtime/evidence/TASK-4F1K3N/`

## Related records

- Parent feature: FEAT-2PZQKJ
- Decision: DEC-FEAT2PZQKJ-001 (accepted)
