# Implementation Plan: TASK-ZS2B41 (rev. 2026-10-01) — Gộp dimension config GHN về 3 paths dùng chung + fix per-dimension CREATE

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-ZS2B41 (rev. 2026-10-01, user correction #2 — pre-review; bản 6-path chưa release/chưa commit) |
| Mode | A (Tier-2 shipping) |
| Specification | Mini-Spec embedded trong [records/tasks/TASK-ZS2B41.md](../records/tasks/TASK-ZS2B41.md) — MINI, VALID (5 sections, rev. 2026-10-01) |
| Decision | DEC-TASKZS2B41-001 (amended in place rev. 2026-10-01 — 3 SHARED paths, defaults 200×3 Create contract; sandbox 150 supersede làm default) |
| Root cause (bug kèm) | `GhnPhysicalParcelInterpreter::assertWithinLimits()` so MỌI chiều với `getMaxLengthCm()` — width/height không bao giờ dùng own limit |
| Contract | 3 paths `carriers/secomm_ghn/max_{length,width,height}_cm` shared RATE (display filter) + CREATE (hard gate); default 200×3 (user decision 2026-10-01); constants = authoritative fallback (`readPositiveIntCm`) |
| Reuses | `Config::readPositiveIntCm()` (giữ nguyên); `QuoteParcelEstimate` 3 optional ctor params (chỉ đổi defaults qua `GhnPackageLimits::MAX_DIMENSION_CM`); `GhnPhysicalLimit` interface `CarrierPhysicalLimitInterface` (không đổi) |
| Out of scope | weight 20/50kg config; data migration path cũ; store param cho `CarrierPhysicalLimitInterface`; commit/push |

## Files affected

Production: `Model/GhnShipmentConstraints.php` (xoá `RATE_MAX_SIDE_CM`, docblock provenance), `Model/Config.php` (6→3 constants + getters `getMax{L,W,H}Cm`), `Model/Shipment/GhnPhysicalLimit.php`, `Model/Rate/QuoteParcelEstimator.php`, `Model/Rate/GhnPackageLimits.php`, `Model/Shipment/GhnPhysicalParcelInterpreter.php` (BUG FIX per-dimension + message "%2 limit"), comment sweep (`GhnCreateReasonLabel`, `GhnCreateParcelValidator`, `GhnShipmentSaveValidationObserver`, `GhnRateCalculator`, `QuoteParcelEstimate`, `GhnRateRequestMapper`, `EstimatedPackage`), `etc/config.xml` (3 defaults 200), `etc/adminhtml/system.xml` (6→3 fields), `i18n/en_US.csv` + `vi_VN.csv` (3 labels + wording).

Tests: 9 files / 12 mock sites (`getRateMax*/getCreateMax*` → `getMax*`); +3 regression width/height own-limit (`GhnPhysicalParcelInterpreterTest`); +1 merchant-lowered-150 (`GhnRateCalculatorTest`).

Records: DEC-TASKZS2B41-001 (amended), TASK-ZS2B41.md (rev.), DECISIONS.md line, CHANGELOG 0.17.0, `ShippingCore/docs/USER_GUIDE.md` (~255/262/500).

## Steps

1. Records trước (A1–A3 đã làm cùng phiên; A4 = file này).
2. Constants + Config seam (B1–B2) → consumers (B3–B6) → interpreter bug fix + wording (B7–B9) → config surface (B10–B12 i18n).
3. Tests (C): đổi 12 mock sites, +4 test mới.
4. Records closure (D): CHANGELOG 0.17.0, USER_GUIDE, TASK Verification checkboxes.

## Verification

1. Baseline trước khi sửa: Ghn suite + validator (`--check-records --check-specs`) — validator baseline 56 FAIL (2026-10-01, chỉ so trước/sau).
2. Sau: Ghn suite all green (+4); full Secomm suite bắt cross-module.
3. `bin/magento setup:di:compile` + `cache:flush`.
4. `bin/magento config:show carriers/secomm_ghn/max_length_cm` → 200; path cũ rỗng; admin 3 field.
5. Smoke RATE: length 210 → GHN ẩn (`GHN_PACKAGE_LENGTH_LIMIT_EXCEEDED`, limit 200); width 190 + `max_width_cm=150` → ẩn WIDTH; 190cm default → quote.
6. Smoke CREATE: package 100×190×100 + `max_width_cm=150` → save chặn pre-commit "…width is 190 cm — above the 150 cm width limit."; width 140 → OK.
7. Guard grep `getRateMax|getCreateMax|XML_PATH_RATE_MAX|XML_PATH_CREATE_MAX|RATE_MAX_SIDE_CM|rate_max_|create_max_` → 0 hits (ngoài CHANGELOG/records prose).
