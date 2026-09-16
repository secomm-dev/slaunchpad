---
id: TASK-CA25FG
type: task
title: Add VNPAY Min/Max Order Total Config And Payment Logo
project_code: SLP
parent:
external_refs: {}
legacy_ids: []
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-15
updated: 2026-09-15
ticket_ref:
affects_version: Magento 2.4.8-p5
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Secomm/VNPAY
source_areas:
  - payment
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-15
supersedes: []
---

# [SLP][TASK-CA25FG] Add VNPAY Min/Max Order Total Config And Payment Logo

<!-- Mode B — feature nhỏ theo pattern chuẩn Magento. Payment-adjacent → TL review bắt buộc trước merge (§8.3/§11). Spec VALID 2026-09-15 — user acting as SA/TL in chat ("ok làm đi" sau khi duyệt solution). -->

## Summary

Thêm config **Minimum/Maximum Order Total** cho phương thức `vnpay` (phù hợp chính sách hạn mức giao dịch VNPAY) và **logo VNPAY** tại checkout, với yêu cầu không gây side effect cho các phương thức thanh toán khác.

## Mini Spec

### Goal

- Đơn ngoài khoảng [min, max] cấu hình → method VNPAY **không hiển thị** ở checkout.
- POST thẳng `/paymentvnpay/order/info` để lách → bị chặn server-side (re-validate).
- Checkout hiển thị logo VNPAY cạnh tên phương thức.

### Expected Behavior

- Method VNPAY tự ẩn khi `baseGrandTotal` ngoài [min, max] — qua check core có sẵn.
- Guard server-side trả về rỗng + log info khi bị lách.
- Logo hiển thị scoped chỉ cho VNPAY.

### Constraints / Rules

- Dùng cơ chế chuẩn Magento `TotalMinMax` (verified: `MethodList.php:96-100` áp `CHECK_ORDER_TOTAL_MIN_MAX` cho mọi method) — KHÔNG viết logic check hiển thị riêng.
- Config so trên **base currency** (`baseGrandTotal`) — chuẩn core; store VN base=VND khớp chính sách VNPAY.
- Logo bằng CSS scoped selector, không đụng template payment chung (dùng chung với Mollie/COD).
- Message source English + `vi_VN.csv` (BR-001) — không phát sinh message mới (guard trả rỗng theo pattern hiện có của Info).

### Out of Scope

- Logo ở admin/order view, trang khác ngoài checkout.
- Chuyển đổi min/max sang VND khi base currency ≠ VND (multi-currency) — ghi nhận limitation.

### Acceptance Criteria

- AC-001: Admin có 2 field `Minimum/Maximum Order Total` — min default `5000` (VNPAY yêu cầu trên 5.000đ, có comment nhắc policy); max **trống mặc định** (tùy hạn mức thẻ, merchant config nếu muốn). Min/Max **chỉ dùng để show/hide** method VNPAY.
- AC-002: Đơn dưới min hoặc trên max → VNPAY **không xuất hiện** ở checkout; các method khác bình thường.
- AC-003: POST thẳng Info với total ngoài khoảng → trả rỗng + log `attempt rejected` (không tạo attempt/URL).
- AC-004: Logo VNPAY hiển thị cạnh title method VNPAY ở checkout; không ảnh hưởng method khác.
- AC-005: Trảfield trống → không giới hạn tương ứng (semantics của core TotalMinMax).

## Implementation

| File | Change |
|------|--------|
| `etc/adminhtml/system.xml` | +2 field `min_order_total` (sortOrder 60) / `max_order_total` (61), `validate-number`, `canRestore`, có `comment` giải thích (min: phải trên 5.000đ theo chính sách VNPAY; max: để trống = không giới hạn) |
| `etc/config.xml` | + default min `5000` (VNPAY policy: trên 5.000đ); max **mặc định trống** — không giới hạn (tùy hạn mức thẻ, merchant config thêm nếu muốn) |
| `Controller/Order/Info.php` | + guard re-validate sau `collectTotals()`, trước save: ngoài khoảng → log info + trả rỗng; + dep `Logger` |
| `view/frontend/web/images/logo-vnpay.png` | **new** — logo VNPAY chính thức (owner cung cấp) |
| `Model/Ui/VnpayConfigProvider.php` | **new** — expose logo URL vào `window.checkoutConfig.payment.vnpay.logo` |
| `etc/frontend/di.xml` | **new** — đăng ký VnpayConfigProvider vào `CompositeConfigProvider` ở **frontend scope** (y hệt ZaloPay `etc/frontend/di.xml`; đăng ký ở global `etc/di.xml` không được merge vào runtime frontend) |
| `view/frontend/web/template/payment/vnpay.html` | + `<img class="vnpay-logo">` trong label method (inline sizing) |
| `view/frontend/web/js/view/payment/method-renderer/vnpay-method.js` | + `getLogo()` đọc từ checkoutConfig |
| `view/frontend/layout/checkout_index_index.xml` | giữ nguyên (đã bỏ hướng dẫn head CSS) |

Side-effect analysis: mọi thay đổi chỉ đọc config của method `vnpay` / scoped selector logo — Mollie, COD, OSC templates, core không bị đụng.

## Test Plan (QC)

| # | Scenario | Kỳ vọng |
|---|----------|---------|
| T1 | Đơn trong khoảng | VNPAY hiển thị, pay flow bình thường |
| T2 | Đơn < min (VD 5.000đ) | VNPAY biến mất khỏi checkout |
| T3 | Config max + đơn > max | VNPAY biến mất khỏi checkout (max trống = luôn hiện) |
| T4 | POST thẳng Info khi ngoài khoảng | Response rỗng + log `attempt rejected` |
| T5 | Logo | Hiển thị ở method VNPAY; Mollie/COD không đổi |
| T6 | Regression Mollie/COD | Không đổi behavior |

## Verification

- Fixed confirmed: ⏳ chờ QC (compile + flush; placeholder SVG cần thay logo chính thức)
- Regression checked: ⏳ T6