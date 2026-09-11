# BUG-HE2NGV (SLP-128) — Batch 4 Verification Results

**Date**: 2026-09-11 · **Mode**: C · **Scope**: newsletter subscription name + OSC order comment/survey labels (order view)

## Changes

`app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv` + `en_US.csv` (mirror):

| Key | vi value (mới) | Lý do |
|---|---|---|
| `You are subscribed to "General Subscription".` | `Bạn đã đăng ký "Nhận bản tin khuyến mãi".` | value cũ giữ tên subscription English (batch 1 dịch nguyên khối) |
| `General Subscription` | `Nhận bản tin khuyến mãi` | đồng bộ tên đã duyệt với message (user chốt theo annotation client; thay "Bản tin chung" của AC-006) |
| `Order Comment` (key MỚI) | `Ghi chú Đơn hàng` | CSV Mageplaza Osc (`app/code/Mageplaza/Osc/i18n/vi_VN.csv:68`) dịch sai "Đặt hàng Bình luận"; theme dict chưa có key nên module CSV thắng |
| `Order Survey` (key MỚI) | `Khảo sát đơn hàng` | proactive cùng trang — Osc CSV "Đặt hàng Khảo sát" |

Nguồn render: Hyva `Magento_Customer/templates/account/dashboard/info.phtml:72` (full-string hardcode), Mageplaza Osc `order/view/comment.phtml:27` + `order/view/survey.phtml:27` (order view = Hyvä scope → theme dict thắng mọi module dict).

## Verification

### Framework-level — 8/8 PASS (exit 0)
Script boot Magento thật (area frontend, theme `Secomm/launchpad`): 4 phrase vi resolve đúng value mới; 4 phrase en_US giữ English (identity skip theo design — LL-0005).

### Live storefront — 3/3 PASS (sau `cache:flush` as secomm, session fresh)
1. **Newsletter manage page** (login customer test): toggle label **"Nhận bản tin khuyến mãi"** ✓
2. **Dashboard** (customer subscribed): **`Bạn đã đăng ký "Nhận bản tin khuyến mãi".`** ✓ — 0 English leftover
3. **Guest order view** (đơn 000000014, test data `osc_order_comment` + `osc_survey_*`): **"Ghi chú Đơn hàng"** + **"Khảo sát đơn hàng"** ✓ — "Đặt hàng Bình luận"/"Đặt hàng Khảo sát"/English = 0

Raw: `.ai/runtime/evidence/BUG-HE2NGV/batch4/` (gitignored — verify-i18n-batch4.php + live-*.html + cookies).

## Regression / Flags cho TL

- en_US không đổi hành vi; mirror key-set vi/en lệch duy nhất 47 key flatpickr (**pre-existing BUG-GJT6C1** — thiếu en mirror, vi phạm BR-001; flag riêng, chưa xử lý trong batch 4)
- Dup key `Comments` trong vi_VN.csv ("Ghi chú" vs "Bình luận", last-wins) — pre-existing, flag TL
- Scope git: chỉ 2 file i18n + docs (record/estimation/LESSONS-LL-0018/evidence)
- Test data local (cleanup sau QC): customer `qc-slp128b4@test.local` + subscriber; order 14 guest gắn osc fields
- QC curl gotchas → **LL-0018** (form_key rotate sau loginPost; OAR 2.4.8 `oar_billing_lastname` + key `oar_zip` bắt buộc; Guest\View redirect khi logged-in)
- Wording VI chờ TL duyệt; chờ TL review (Mode C — Code Gate)
