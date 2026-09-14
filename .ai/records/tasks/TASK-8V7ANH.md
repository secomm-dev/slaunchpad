---
id: TASK-8V7ANH
type: task
title: 'Translate "Track your order" trên My Account → Order View (SLP-216)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-216
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-09-14
updated: 2026-09-14
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv
  - app/design/frontend/Secomm/launchpad/i18n/en_US.csv
source_areas:
  - theme-i18n
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-14
supersedes: []
---

# [SLP][TASK-8V7ANH] Translate "Track your order" trên My Account → Order View (SLP-216)

<!-- External ticket: SLP-216. Scope: trang My Account → My Orders → Order View còn chuỗi "Track your order" (English) trên store vi_VN; cả trang đã dịch. Chỉ sửa theme i18n CSV — không đụng vendor/template/layout. -->
<!-- Mode C — embedded Mini-Spec + Approach trong record này. -->

## Summary

Demo (SLP-216 screenshot): order view `/sales/order/view/order_id/93` store vi_VN hiển thị link **"Track your order"** chưa dịch, mọi section khác đã tiếng Việt. Chuỗi đến từ `vendor/magento/module-shipping/view/frontend/layout/sales_order_view.xml:13` — block `tracking-info-link` (`Magento\Shipping\Block\Tracking\Link`, template `Magento_Shipping::tracking/link.phtml`) với argument `label` `translate="true"`. Label được `TranslateDecorator` wrap thành `Phrase` khi layout parse → theme-level dictionary (`Secomm/launchpad/i18n/*.csv`) phủ được mà không cần override. Key `Track your order` **thiếu** trong cả 2 theme CSV (các key tracking khác đã có: `Track Shipment`, `Order tracking`, `Tracking Number`…). Hyvä default theme override cùng layout với cùng label → không cần đụng.

## Mini Spec

### Goal
Link tracking trên My Account → Order View hiển thị đúng ngôn ngữ store: vi_VN → "Theo dõi đơn hàng", en_US → "Track your order".

### Expected Behavior
1. Store `default` (vi_VN): trang order view render link tracking với text + `title` attr = "Theo dõi đơn hàng".
2. Store `launchpad_en` (en_US): hiển thị identity "Track your order" (không đổi so với hiện tại).
3. Không thay đổi hành vi khác của trang (link vẫn mở tracking popup như trước).

### Constraints / Rules
- Chỉ thêm row vào theme CSV — KHÔNG sửa vendor, KHÔNG override layout/template.
- BR-001: string phải vào cả `vi_VN.csv` và `en_US.csv` (en_US mirror identity theo pattern hiện có).
- Giữ format CSV hiện có: `"key","value"`, UTF-8, LF, không thêm BOM/trailing space.

### Out of Scope
- Nội dung popup tracking (`tracking/tracking/popup` — "Tracking Information", carrier detail) — ticket khác nếu QC phát hiện còn English.
- Các payment/shipping method title ("Check / Money order", "Giao Hàng Nhanh (Hóa Tốc)") là config admin, không phải dictionary phrase.

### Acceptance Criteria
- AC-001: Framework-level — sau khi thêm row, Phrase "Track your order" resolve "Theo dõi đơn hàng" trên store vi_VN (store emulation CLI) và identity trên en_US.
- AC-002: Live — HTML trang order view (customer session vi_VN) chứa "Theo dõi đơn hàng", không còn "Track your order" (sau `cache:flush`; decode HTML entities khi grep).
- AC-003: en_US không regression — trang order view en_US vẫn "Track your order".

## Approach

1. Thêm row `"Track your order","Theo dõi đơn hàng"` vào `app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv` (cạnh cụm Magento_Shipping, dòng ~576) + mirror identity `"Track your order","Track your order"` vào `en_US.csv`.
2. Verify theo LL-0004/LL-0005/LL-0007: store emulation CLI (setAreaCode frontend → wire Phrase renderer → loadData per-store, 1 process/store) → `cache:flush` (as secomm) → fetch live order view bằng server-side customer session + curl, grep vi/en với decode entities.
3. Evidence: `.ai/evidence/TASK-8V7ANH/` (verify script + output + live HTML snapshot).

## Verification

- [x] AC-001 — PASS: framework 6/6 vi/en (dict + render thật) — `verify-dictionary.php` + `render-*.html`
- [x] AC-002 — PASS: live vi (occurrences=2, English=0) — `order-view-vi.html` + `check-live.py`
- [x] AC-003 — PASS: framework en + live en identity ×2 (`order-view-en.html`)

## Implementation Notes

2026-09-14 — bắt đầu (Mode C, phân tích đã xác nhận nguồn chuỗi + thiếu key dictionary).

2026-09-14 — DONE. Đã thêm row `"Track your order","Theo dõi đơn hàng"` vào `vi_VN.csv:577`
+ mirror identity vào `en_US.csv:577` (cạnh cụm Magento_Shipping popup). Verify toàn bộ
PASS (chi tiết `.ai/evidence/TASK-8V7ANH/RESULTS.md`):
- Framework: dictionary + render thật (block `Tracking\Link` + template `tracking/link.phtml`,
  label như layout argument `new Phrase`) per-store 3/3 × 2 store — pattern BUG-GJT6C1/LL-0007.
- Live: order view `/sales/order/view/order_id/5` (customer 3, server-side QC session) —
  vi: "Theo dõi đơn hàng" ×2 (text + title), English ×0; en: identity ×2 (store switch
  `?___store=launchpad_en` hoạt động trên order view — KHÔNG bị quirk LL-0011 như checkout).
- `cache:flush` as secomm trước khi fetch. Track fixture (order 5 / shipment 3, track_id=1)
  đã delete sau verify — DB as-found.

**Finding cho QC/TL:** Hyvä `Magento_Sales/templates/order/view.phtml:52-54` chỉ render
link tracking khi `$order->getTracksCollection()` non-empty (Luma render vô điều kiện).
Order demo 80 (screenshot SLP-216) có track nên link hiện; order KHÔNG có track sẽ không
thấy link — không phải lỗi của fix này.

Status: chờ TL review (Mode C).
