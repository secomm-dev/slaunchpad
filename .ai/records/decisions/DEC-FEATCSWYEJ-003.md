---
id: DEC-FEATCSWYEJ-003
legacy_ids: []
title: 'Payment Core expiry window = TTL provider session (VNPAY 15'), đo từ lần generate URL CUỐI (place order hoặc Continue Payment); mỗi lần Continue Payment refresh expires_at'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-25
created: 2026-08-25
last_verified: 2026-08-25
verified_against_commit: 140a83e8
supersedes: []
superseded_by:
work_items: [FEAT-CSWYEJ]
---

# Decision Record: Payment Core — expiry window semantics (D4 rev)

## Context

QC sandbox 2026-08-25 (user TL): với default 120', cửa sổ Continue Payment dài hơn nhiều so với **session thực tế của VNPAY (token TTL 15')**. Hệ quả sai semantics: nút Continue vẫn mời khách thanh toán bằng link đã chết; và khách click Continue ở phút 14 có thể bị cron cancel ở phút 15 khi đang nhập OTP — race giữa refresh và expiry.

User TL quyết định: **hết hạn phải theo TTL sandbox VNPAY (15')**, tính từ lần khách được đưa sang VNPAY gần nhất.

## Decision

| # | Câu hỏi | Quyết định | Alternatives |
|---|---|---|---|
| D4-rev | Expiry window tính từ đâu? | **Từ lần generate checkout URL cuối cùng** — place order lần đầu hoặc click Continue Payment lần sau. Mặc định 15' (khớp TTL token VNPAY sandbox/prod); override per-method vẫn khả dụng qua config cho provider khác có TTL khác. | (b) 120' tĩnh từ place order — mời khách thanh toán link chết; race cancel-giữa-lúc-thanh-toán |
| D8 | Continue Payment refresh expires_at? | **Có** — mỗi lần retry sinh provider session mới (token mới 15'), record window restart theo. Điều này đồng thời đóng race: cron không thể cancel đơn đang trong phiên thanh toán mới. | Không refresh — window cũ hết giữa phiên mới |

## Consequences

- `default_expiry_minutes` default đổi 120 → **15**; semantics = "TTL của provider session gần nhất".
- Cron expiry giờ thực chất xử lý "không còn phiên thanh toán hợp lệ nào" — đúng ý nghĩa nghiệp vụ.
- Đơn bị bỏ quên: sau 15' hết phiên cuối → cron verify querydr → cancel (nhanh hơn trước: thay vì giữ hàng 120', stock trả trong ~15-20').
- Force-close 7 ngày và toàn bộ race guard không đổi.
- Provider khác (Mollie) TTL khác: đặt `expiry_overrides` (vd `mollie:60`).

## Affected components

`Config::DEFAULT_EXPIRY_MINUTES` (120→15) · `etc/config.xml` default · `CanContinuePayment::refreshExpiry()` (mới) · `Controller/Payment/Retry.php` (gọi refresh sau khi adapter cấp URL thành công).

## Related records

- Refines: DEC-FEATCSWYEJ-001 (D4), DEC-FEATCSWYEJ-002
- Feature: FEAT-CSWYEJ
