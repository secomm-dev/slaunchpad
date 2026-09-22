# Implementation Plan: BUG-ZTGGYZ — U1 VietNamAddress hierarchy province→L2 edge

## Metadata

| Field | Value |
|-------|-------|
| Task | BUG-ZTGGYZ (blocker U1 của TASK-FMBBSD GHN-C QC L3) |
| Mode | A (address data integrity — Tier-2 overlay) |
| Specification | Mini-Spec embedded trong [records/bugs/BUG-ZTGGYZ.md](../records/bugs/BUG-ZTGGYZ.md) — MINI, VALID (implementation-correcting-to-contract; không DEC mới) |
| Root cause | `UnitSnapshotWriter::write()` import `parent_code` verbatim từ seed (seed để trống province edge cho L2) → mọi L2 unit parent NULL → `getChildren(parent)` không trả children → name-bridge UNMAPPED toàn bộ |
| Contract | **Option A** — `parent_code` là canonical portable parent relation; province edge được SYNTHESIZE từ `region_code` lúc import (region unit code == region_code) |
| Reuses | `UnitSnapshotWriter` (điểm fix duy nhất — mọi import path đều qua đây), pattern test mock-adapter của VietNamAddress |
| Out of scope | U2 (chỉ re-evaluate sau rerun), `unitFile(PRE_2025)` stale file (follow-up riêng §20), GHN CanonicalCsvProvider workaround, dataset remap |

## Files affected

| File | Change | Lý do |
|------|--------|-------|
| `Model/Import/UnitSnapshotWriter.php` | modify | Synthesize `parent_code = region_code` cho unit có seed parent trống; level vẫn derive từ CSV parent (3:2); guard fail-loud region không tồn tại |
| `Model/Import/HierarchyParentBackfill.php` | new | Repair service cho existing DB: backfill `parent_code = region_code` WHERE level=2 AND parent_code IS NULL (validate region L1 tồn tại; idempotent; trả counts) |
| `Console/Command/RepairAddressHierarchyCommand.php` | new | `secomm:vietnam-address:hierarchy:repair [--scheme=]` gọi backfill, in counts, exit fail nếu còn null |
| `etc/di.xml` | modify | Đăng ký command |
| `Test/Unit/Model/Import/UnitSnapshotWriterTest.php` | new | AC-1/AC-2: synthesis matrix (2025 ward → parent=region L2; PRE district → parent=region L2; PRE ward giữ parent district L3) + real rows Long Vĩnh 2025/PRE + guard fail |
| `Test/Unit/Model/Import/HierarchyParentBackfillTest.php` | new | AC-3: chỉ đụng level=2 NULL; validate region; counts; idempotent |
| `Test/Unit/Model/VnAddressUnitProviderTest.php` | modify | Khóa contract `getChildren` where `parent_code = ?` (§12) |

## Verification

VietNamAddress + Secomm_Ghn scoped suites 0F/0E · `setup:di:compile` · chạy repair trên DB hiện tại →
audit null counts = 0 + counts dataset không đổi · `resolveWardByName(1224, 'Long Vĩnh')` EXACT ·
**GHN-C real Quote rerun** (QC CASE 1 — method `secomm_ghn` + fee sandbox thật + snapshot sạch) ·
GHTK smoke name-path (không sửa code). U2 quyết định theo §17 brief.
