---
id: BUG-ZTGGYZ
type: bug
title: 'U1 — VietNamAddress hierarchy: province→L2 edge mất trong unit snapshot (name-bridge UNMAPPED toàn bộ ward)'
project_code: SLP
mode: A
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec dưới đây (implementation-correcting-to-contract — không DEC mới, §23)
plan: ../../plans/BUG-ZTGGYZ-implementation-plan.md
risk: high                    # Address data integrity (Tier-2 overlay) + chặn GHN-C/GHTK real checkout path
status: in_progress           # dev-complete + rerun PASS — chờ TL review/closure (cùng gate TASK-FMBBSD GHN-C)
priority: high
decision_assessment: material
decisions: [DEC-FEATYA2C0W-001, DEC-FEATYA2C0W-004]
components:
  - CMP-ADDR
source_areas:
  - app/code/Secomm/VietNamAddress/
changes_project_state: true
created: 2026-09-14
updated: 2026-09-14
owner: [dev]
related_tickets: [TASK-FMBBSD]
---

# [SLP][BUG-ZTGGYZ] U1 — VietNamAddress hierarchy: province→L2 edge mất trong unit snapshot

**Discovery**: QC L3 GHN-C (TASK-FMBBSD, `.ai/evidence/TASK-FMBBSD/qc-l3-checkout.md`) — real Quote
path fail-closed `CANONICAL_UNRESOLVED` cho mọi address theo tên. Cùng gốc với defect #3 đã ghi ở
TASK-6TNKDH ("VietNamAddress-side, vẫn thuộc owning stream").

## Progress Log

- **2026-09-14 — fixed + repaired + GHN-C rerun PASS (AC-1..AC-6 đều green).** Contract xác định:
  Option A — `parent_code` là canonical portable parent relation (schema docblock "portable"; PRE L3
  dùng; getChildren + name resolver + GHN MappingMatcher expect); province edge bị mất do
  `UnitSnapshotWriter` import seed verbatim (seed để trống parent cho L2). Fix: writer synthesize
  `parent_code = region_code` (level vẫn theo RAW seed parent — 2025 ward không bị promote L3) +
  guard fail-loud unknown region; `HierarchyParentBackfill` + CLI
  `secomm:vietnam-address:hierarchy:repair` cho existing DB. DB repair thật: 2025 = 3,321 backfilled,
  PRE = 696; re-run no-op (idempotent); counts nguyên vẹn (34+3,321 / 63+696+10,035); non-root NULL = 0;
  Long Vĩnh 2025 parent VN-34 ✓. Name resolver live: `resolveWardByName(1224,'Long Vĩnh')` = EXACT
  `VNA25-F2118484F0`; Kỳ Lừa EXACT `VNA25-4EA3ADA7E1`. **GHN-C real Quote rerun PASS**: method
  `secomm_ghn` hiển thị **price 214,500 VND** (fee sandbox thật, 1 calculate_fee HTTP 200), title
  "GHN / GHN Delivery", flatrate không ảnh hưởng; snapshot không chứa GHN identity; Kỳ Lừa (merged,
  5 candidates) vẫn fail-closed hidden. **U2 = NOT REQUIRED**: name-only (cityId=null) đi trọn
  pipeline SUCCESS 214,500 — RateRequest destCity name là đủ, không mở ShippingCore task. Regression:
  Ghn+Ghtk+VietNamAddress+ShippingCore **802 tests / 0F / 0E**; GHTK không regress (name path hưởng
  lợi cùng bridge — positive cross-carrier impact). Evidence: `.ai/evidence/TASK-FMBBSD/qc-l3-checkout.md`
  (appendix U1) + chạy lại QC CASE 1. Follow-up ghi nhận riêng: `unitFile(PRE_2025)` stale file.

## Embedded Mini-Spec

### Goal

Khôi phục province→L2 hierarchy edge trong `secomm_vietnam_address_unit` để
`VnOperationalNameResolver → getChildren(...)` resolve được tên địa chỉ thật (2025: Province→Ward;
PRE_2025: Province→District→Ward), unblock GHN-C real checkout path và GHTK name path.

### Expected Behavior

1. **Contract (đã xác định từ code/data — Option A)**: `parent_code` là canonical portable parent
   relation (docblock UnitSnapshotWriter "portable"; PRE L3 đã dùng; `getChildren` + name resolver +
   GHN MappingMatcher đều expect). `region_code` là attribution địa lý, KHÔNG phải hierarchy edge.
2. Import: unit row có parent_code trống trong seed (2025 ward, PRE district) → snapshot ghi
   `parent_code = region_code` (= province unit code, vì region unit code == region_code); level giữ
   nguyên semantic (CSV parent trống → 2; có → 3). Guard fail-loud nếu region_code không tồn tại
   trong region rows cùng batch.
3. Existing DB: repair idempotent, deterministic — backfill `parent_code = region_code` cho
   `level = 2 AND parent_code IS NULL`, có validate region tồn tại; KHÔNG đổi scheme_code/unit_code/
   names/mappings; KHÔNG swap runtime scheme.
4. Sau fix: `getChildren(provinceCode)` trả L2 children; `getChildren(districtCode)` trả L3 wards;
   sai parent → không trả; name resolver parent-scoped, statuses EXACT/AMBIGUOUS/UNMAPPED giữ nguyên,
   không fuzzy.

### Constraints / Rules

- Không đổi canonical identity (scheme_code/unit_code/names/counts: 2025 = 34+3,321; PRE = 63+696+10,035).
- Không đụng cross-scheme mappings, GHN mapping datasets, AddressDropdown runtime tables.
- Không sửa Secomm_Ghn / ShippingCore / GHTK trong task này (read-only context).
- Không thêm fuzzy/LIKE/accent-stripping matching vào runtime.
- Mọi import path đều qua `UnitSnapshotWriter` (đã audit: VnAddressSchemeImporter + VnReferenceSchemeImporter
  virtualType + Data Patches bootstrap) — 1 điểm fix.

### Out of Scope

- U2 (RateRequest city node id) — chỉ re-evaluate SAU khi U1 fix + rerun Quote path (§17 brief TASK-FMBBSD).
- `VnSchemes::unitFile(PRE_2025)` trỏ file reference cũ thay vì SNAPSHOT_2024 — follow-up riêng (§20).
- GHN `CanonicalCsvProvider` workaround — giữ nguyên (redundant sau fix, gỡ ở task GHN riêng).
- Dataset remapping / re-generate unit codes.

### Acceptance Criteria

- AC-1: `UnitSnapshotWriter` ghi parent_code = region_code cho unit có seed parent trống; PRE ward giữ
  parent = district; guard fail khi region không tồn tại (tests).
- AC-2: Real rows: 2025 `VNA25-F2118484F0 (Long Vĩnh, VN-34 Vĩnh Long)` → parent `VN-34`; PRE
  `VNAP25-01965FA7E0` giữ parent `VNAP25-C5B8541622` (district) — tests khóa.
- AC-3: Repair command idempotent: chạy 2 lần → counts không đổi; non-root parent_code NULL = 0 cho
  cả 2 scheme trên DB hiện tại; audit integrity không đổi (34/3,321 + 63/696/10,035).
- AC-4: `VnOperationalNameResolver::resolveWardByName(regionId, 'Long Vĩnh')` → EXACT trên DB đã repair.
- AC-5: GHN-C real Quote rerun (TASK-FMBBSD QC CASE 1): Quote → collectShippingRates → GHN method
  hiển thị với fee sandbox thật; snapshot không chứa GHN identity.
- AC-6: Regression: VietNamAddress suite + Secomm_Ghn suite 0F/0E; GHTK không regress.
