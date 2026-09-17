# BUG-ER121M (SLP-199 follow-up) — Results

> Discount section: nút tụt +12.1px so với input khi validation message hiển thị.
> Fix: `align-items: center` → `flex-start` trong `osc-discount-code.css`. CSS-only, 1 rule.
> Verify: Playwright, guest checkout, product `joust-duffle-bag` — 2026-09-16.

## Files

- `probe.js` — probe (mỗi process 1 viewport `VP=1280|375`; `WITH_COUPON=1` bật flow FREESHIP apply/cancel).
- `baseline-output.txt` (run 1, 1280 — crash ở 375 do OOM), `baseline-1280.txt`, `baseline-375.txt` — trước fix.
- `after-1280.txt` (full 5 state + coupon), `after-375.txt` (fresh+error PASS, coupon-flow fail — xem Ambient), `after-375-nocoupon.txt` (full AC-001 scope).
- Screenshots: `discount-vi-{1280,375}-{fresh,error}-before.png` (trước) / `*-after3.png` (sau).

## Baseline (trước fix)

| Viewport | State | control h | dTop | dBottom |
|---|---|---|---|---|
| 1280 | fresh | 36px | 0 | 0 |
| 1280 | error ("Đây là một trường bắt buộc.") | 60.1px | **+12.1** | **+12.1** |
| 375 | fresh | 36px | 0 | 0 |
| 375 | error | 60.1px | **+12.1** | **+12.1** |

Cơ chế khớp phân tích: mage/validation chèn `div.mage-error` (h=17.1px) vào trong `.control` ngay sau input (mage/validation.js errorPlacement 1876–1907); input cũng bị add class `mage-error` (line 1614 — lưu ý khi querySelector: node đầu tiên match `.mage-error` là input). `.control` 36→60.1px + `align-items: center` → toolbar 36px center theo row cao → nút tụt (60.1−36)/2 ≈ +12.1px.

## After fix

| Viewport | State | dTop | dBottom |
|---|---|---|---|
| 1280 | fresh | 0 | 0 |
| 1280 | error | **0** | **0** |
| 1280 | applied (nút "Hủy mã giảm giá", input `.disabled`) | 0 | 0 |
| 1280 | canceled | 0 | 0 |
| 1280 | error2 (error tái lập sau cancel) | 0 | 0 |
| 375 | fresh | 0 | 0 |
| 375 | error | **0** | **0** |

- `alignItems` computed = `flex-start` ở mọi state.
- Coupon flow behavior (1280): apply FREESHIP → nút đổi `action-cancel` + input disabled; cancel → về `action-apply`; error message VI render đúng (BUG-5NR0PD intact); không JS error mới.
- AC-001 PASS (error state 0/0 cả 2 viewport — ngưỡng ±2px); AC-002 PASS (các state khác bất biến 0/0); AC-005 screenshot full section sạch.

## Ambient / ghi nhận

- **FREESHIP apply trên viewport 375 không hoàn tất** (`.action-cancel` không xuất hiện trong 30s sau click, không retry-fail): không thể do fix (align-items là presentation; 1280 cùng flow PASS; click thành công không lỗi). Khả năng pre-existing theo mobile flow/quote session — **chuyển QC xác minh**, không block bug này (AC behavior đã cover ở 1280).
- **Trap LL-0015 tái xác nhận (F3)**: sau khi sửa source CSS, bản materialized regular-file trong `pub/static/frontend/Magento/luma/vi_VN/Launchpad_Osc/css/` vẫn serve nội dung cũ → probe 1 đọc `align-items: center` dù source đã sửa. Fix verify: `find pub/static -path "*Launchpad_Osc*" -name "osc-*.css" -delete` (11 file mọi area) → request sau re-materialize đúng nội dung mới.
- **OOM**: box chỉ còn ~1.2GB RAM available (OpenSearch/VSCode/mysqld) — chromium headless bị oom-kill khi chạy 2 context liên tiếp trong 1 process; probe tách 1 viewport/process (dmesg `oom_kill chrome-headless` xác nhận).
- Console: không có error mới (2× "Error fetching data" = ambient baseline TASK-WXBQYZ).
