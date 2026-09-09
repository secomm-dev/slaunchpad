---
id: BUG-1N8XC8
type: bug
title: TableRate shipping method "{{delivery_days}}" renders untranslated day(s) suffix on storefront
project_code: SLP
parent:
external_refs:
  tickets: SLP-112
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-09
updated: 2026-09-09
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2) + Mageplaza TableRateShipping
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Launchpad/MageplazaTranslate
  - app/code/Mageplaza/TableRateShipping (read-only root cause)
source_areas:
  - checkout
  - shipping
  - i18n
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-09
supersedes: []
---

# [SLP][BUG-1N8XC8] TableRate shipping method "{{delivery_days}}" renders untranslated day(s) suffix on storefront

<!-- External ticket: SLP-112. Scope: translation-only — thêm 2 phrase i18n cho phần "{{delivery_days}}" của TableRate method title hiển thị ở OSC Order Summary ("Giao hàng - Tiêu chuẩn 3 day(s)"). Không đụng logic collectRates / pricing / vendor Mageplaza. -->

## Summary

Trên OSC Order Summary (store vi_VN), dòng shipping hiển thị "Giao hàng - Tiêu chuẩn **3 day(s)**" — phần số ngày do placeholder `{{delivery_days}}` sinh ra chưa được dịch sang tiếng Việt. Title TableRate method lưu trong DB chứa `{{delivery_days}}`; carrier thay placeholder bằng `__('%1 day(s)', …)` / `__('%1 - %2 day(s)', …)`, nhưng 2 phrase này không có bản dịch vi_VN ở bất kỳ dictionary nào (module chỉ có `en_US.csv` identity; theme `Secomm/launchpad` cũng chưa có).

## Mini Spec

### Goal

Phần `{{delivery_days}}` trong method title của Mageplaza TableRateShipping hiển thị tiếng Việt trên store vi_VN ("3 ngày" / "2 - 5 ngày") và giữ nguyên English trên store en_US.

### Expected Behavior

- Store `default` (vi_VN): "Giao hàng - Tiêu chuẩn 3 **ngày**"; case range: "2 - 5 ngày".
- Store `launchpad_en` (en_US): giữ nguyên "3 day(s)" / "2 - 5 day(s)" (identity).
- Cả 2 case output của `getDeliveryDays()`: single (`%1 day(s)`) và range (`%1 - %2 day(s)`).

### Constraints / Rules

- **Không sửa** `app/code/Mageplaza/TableRateShipping` in-place (§7.1) — phrase thêm vào `Launchpad_MageplazaTranslate/i18n/{vi_VN,en_US}.csv` (nhà chung i18n Mageplaza của project, pattern BUG-GJT6C1/BUG-5NR0PD).
- BR-001: thêm phrase ở **cả** `vi_VN.csv` và `en_US.csv` (en_US dạng identity).
- Giữ nguyên source phrase `%1 day(s)` / `%1 - %2 day(s)` — chỉ đổi target; placeholder `%1`/`%2` phải nằm đúng vị trí.
- Tiếng Việt không chia số nhiều → bỏ "(s)" trong bản dịch ("ngày" đúng cho mọi số).

### Out of Scope

- Không đổi logic `collectRates()` / `getDeliveryDays()` / cách thay `{{delivery_days}}` — translation-only.
- Không dịch các phrase khác trong `TableRateShipping/i18n/en_US.csv` (chỉ 2 phrase lộ trên storefront checkout).
- Không đổi title/carrier title lưu trong DB admin ("Giao hàng", "Tiêu chuẩn" là text admin nhập, không qua `__()`).

### Acceptance Criteria

- AC-001: Store `default` (vi_VN) — dictionary resolve `__('%1 day(s)', 3)` → "3 ngày" và `__('%1 - %2 day(s)', 2, 5)` → "2 - 5 ngày" (verify CLI per-store, pattern BUG-GJT6C1).
- AC-002: Store `launchpad_en` (en_US) — 2 phrase giữ identity English.
- AC-003: Storefront OSC Order Summary store vi_VN hiển thị "… ngày" thay vì "… day(s)" (manual QC trên demo env — cần cart có rate TableRate có delivery days).
- AC-004: Regression — các phrase i18n khác đã dịch (DeliveryTime, ExtraFee, coupon) không bị ảnh hưởng; en_US storefront không đổi.

## Root Cause

`Mageplaza_TableRateShipping` không ship `i18n/vi_VN.csv`; `i18n/en_US.csv` của module chỉ có identity mapping. 2 phrase sinh ra từ [`getDeliveryDays()`](app/code/Mageplaza/TableRateShipping/Model/Carrier/TableRate.php) — `__('%1 day(s)', $maxDelivery)` (dòng 426) và `__('%1 - %2 day(s)', $minDelivery, $maxDelivery)` (dòng 429) — được `str_replace` vào method title tại collectRates (dòng 213-214) trước khi render ở OSC Order Summary. Không có dictionary vi_VN nào chứa 2 phrase → `__()` trả nguyên English.

Trigger: admin đặt method title dạng "Tiêu chuẩn {{delivery_days}}"; phần title text là DB label (không qua `__()`), chỉ phần placeholder là translatable.

## Approach

1. ✅ Trace code: `TableRate::collectRates` → `str_replace('{{delivery_days}}')` → `getDeliveryDays()` → 2 phrase `__()` (root cause, trên).
2. Thêm 2 phrase vào `app/code/Launchpad/MageplazaTranslate/i18n/vi_VN.csv` ("ngày") + mirror identity vào `en_US.csv` (BR-001, pattern BUG-GJT6C1).
3. Deploy: `setup:static-content:deploy vi_VN en_US` + `cache:flush` (CLI as `secomm` — LL-0003).
4. Verify CLI per-store (AC-001/002): dictionary-level + Phrase render, script trong `.ai/runtime/evidence/BUG-1N8XC8/`, evidence tại `.ai/evidence/BUG-1N8XC8/`.
5. Pre-review → TL review → manual QC OSC storefront (AC-003) → estimation log.

## Test Plan & Evidence

- CLI verify per-store (1 process/store — LL-000X): vi_VN expect "ngày", en_US expect identity "day(s)".
- Regression: phrase sample khác trong cùng dictionary (vd "Delivery Date" → "Ngày giao hàng") resolve đúng ở cả 2 store.
- Manual QC storefront (TL/QC): OSC Order Summary trên demo env, đúng case screenshot SLP-112.

## Implementation (done 2026-09-09)

- `app/code/Launchpad/MageplazaTranslate/i18n/vi_VN.csv` +2 phrase: `"%1 day(s)","%1 ngày"`, `"%1 - %2 day(s)","%1 - %2 ngày"`.
- `app/code/Launchpad/MageplazaTranslate/i18n/en_US.csv` +2 identity rows (BR-001).
- Không đụng vendor `Mageplaza_TableRateShipping`, không đụng logic, không đụng DB.

## Verification Evidence (2026-09-09 — chi tiết: `.ai/evidence/BUG-1N8XC8/RESULTS.md`)

Dictionary-level verify per-store (script `.ai/runtime/evidence/BUG-1N8XC8/verify-day-phrases.php`, pattern BUG-GJT6C1, không setDesignTheme → chứng minh theme-independent theo LL-0011):

| Store | Check | Got | |
|---|---|---|---|
| `default` (vi) | `__('%1 day(s)', 3)` | `3 ngày` | PASS |
| `default` (vi) | `__('%1 - %2 day(s)', 2, 5)` | `2 - 5 ngày` | PASS |
| `launchpad_en` (en) | single + range | identity English | PASS |
| cả 2 | regress `Delivery Date` | đúng mỗi locale | PASS |

**6/6 PASS** (exit 0). Deploy: `cache:flush` (bước bắt buộc — dictionary PHP được cache) + SCD `vi_VN en_US` (finding: `js-translation.json` chỉ chứa phrase scan-JS, không chứa dictionary CSV module → SCD không ảnh hưởng phrase PHP-side; chi tiết `.ai/evidence/BUG-1N8XC8/RESULTS.md`).

## Follow-ups / Cleanup

- **Manual QC trên demo env (TL/QC — AC-003)**: OSC Order Summary hiển thị "… ngày" — test trên `slaunchpad-demo.secomm.vn` (nơi chụp screenshot SLP-112; local TableRate `active=0`, cần cart + rate có delivery days). Cover cả case single lẫn range nếu demo có method range.
- Wording "%1 ngày" / "%1 - %2 ngày" đã chạy theo đề xuất được duyệt ("thực thi") — nếu TL muốn đổi (vd thêm "(s)") chỉ sửa target column 2 dòng CSV.
