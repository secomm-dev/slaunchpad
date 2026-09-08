# BUG-SRF024 — Verification Evidence (SLP-147)

**Date**: 2026-09-04 · **Env**: local `slaunchpad.localhost` (Magento 2.4.8-p5, default mode, admin user `qc01calendar`)
**Method**: Playwright headless Chromium — A/B test: chạy cùng kịch bản TRƯỚC và SAU khi bật `Secomm_AdminCalendarFix`.
Target: trang `system_config/edit/section/mpdeliverytime` → group Delivery Time → **Date Off** (field-array của ticket SLP-147), mở calendar bằng icon (`.ui-datepicker-trigger`), page đã scroll (`scrollY > 0`).

## A/B Results — Date Off grid (primary target)

| Metric | repro (fix DISABLED) | verify (fix ENABLED) |
|---|---|---|
| `_findPos` live source | `getBoundingClientRect` only (override hỏng của Magento) | `rect + pageXOffset/pageYOffset` (fix) |
| scrollY | 956 | 956 |
| input doc top + height | 1313 + 33 | 1313 + 33 |
| `#ui-datepicker-div` doc top | 390 | **1346** |
| deltaTop (dp − input bottom) | **−956 (= −scrollY, chứng minh viewport-vs-document mismatch)** | **0** (anchored chính xác) |
| Kết quả | FAIL — tái hiện đúng SLP-147 | PASS |
| Console errors | 0 | 0 |

> `deltaTop = −scrollY` ở phase repro là proof trực tiếp: Magento's `_overwriteFindPos` trả viewport coords, gán vào `position:absolute` (document coords) → popup bị dịch lên đúng lượng scroll.

## Regression — `mage.dateRange` (order grid purchase-date filter)

- verify (fix enabled): scrollY=191, input doc top 425, dp doc top 55, dpH 370 → dp bottom = 425 = input top → datepicker mở **phía trên** input với clamp chuẩn của jQuery UI (`aboveOk`) → PASS.
- `$.mage.dateRange` registered = true (mixin re-register dateRange hoạt động).
- Console errors: 0.

## Screenshots

- `order-repro.png` — FAIL trước fix (calendar trôi về đầu trang, lệch −956px)
- `order-verify.png` — PASS sau fix (calendar neo ngay dưới input)
- `daterange-verify.png` — PASS regression (order grid date-range, mở phía trên đúng clamp)

## Test scripts

`calcheck.js` (Date Off + fallback), `daterange-check.js` (order grid). Navigate qua menu anchors (secret-keyed URLs) — direct URL không key sẽ 404 (behavior chuẩn của admin, không phải bug).

## Ghi chú môi trường

- `admin/urls/use_key` **không đổi** (giữ nguyên secret key).
- Admin user test `qc01calendar` (role Administrators) tạo cho QC tự động — **cleanup sau release** (TL quyết).
- `pub/static/adminhtml` + `var/view_preprocessed` đã clear và regenerate on-demand (default mode) — không cần static-content:deploy.
- Còn lại AC-001/002/004 pass bằng đo lường trên (AC-004: fix chỉ JS positioning, không đụng config data; hàng test chỉ thêm ở DOM, chưa Save); AC-005 console sạch. Manual QC trên demo env (case screenshot SLP-147) cho TL/QC.
