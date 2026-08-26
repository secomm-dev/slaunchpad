---
id: DEC-FEATCSWYEJ-002
legacy_ids: []
title: 'Payment Core D1 rev — VNPAY adapter nằm trong Secomm_PaymentCore (Model/Provider), Vnpayment_VNPAY giữ nguyên bản (pristine); querydr_url là config của Payment Core, để trống = auto-cancel OFF'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-25
created: 2026-08-25
last_verified: 2026-08-25
verified_against_commit: 140a83e8
supersedes: [DEC-FEATCSWYEJ-001]
superseded_by:
work_items: [FEAT-CSWYEJ]
---

# Decision Record: Payment Core — D1 revision (adapter placement)

## Context

Sau dev run đầu (adapter đặt trong `Vnpayment_VNPAY` per DEC-FEATCSWYEJ-001 D1), user (TL) chỉ đạo 2026-08-25: `Vnpayment_VNPAY` là extension third-party — **không sửa gì ở đó** (đúng quy tắc AGENTS §7.1 "Mageplaza-like third-party: extend, do not modify" áp cho vendor payment). Toàn bộ thay đổi trong extension đã được revert (`git checkout` + xóa file mới); ownership guard trong `Controller/Order/Info.php` cũng bị revert theo — lỗ hổng đó tồn tại từ trước, ngoài scope, sẽ logged là known risk thay vì sửa trong extension.

Câu hỏi thứ hai: querydr_url "phải nhập" — xác nhận: **đúng, phải nhập tay** (VNPAY không cấp endpoint trong code extension; docs integration cung cấp). Đã làm rõ semantics: link Continue Payment KHÔNG cần querydr (build lại từ 3 config sẵn có + increment_id); querydr CHỈ cho cron verify trước cancel. Không nhập → verify luôn UNKNOWN → cron không bao giờ cancel (auto-expiry off an toàn).

## Decision

| # | Câu hỏi | Quyết định | Alternatives |
|---|---|---|---|
| D1-rev | Adapter VNPAY đặt đâu? | **Trong `Secomm_PaymentCore/Model/Provider/`** (`VnpayAdapter` + `VnpayCheckoutUrl`). Extension giữ pristine. Adapter đọc config paths `payment/vnpay/*` của extension (string contract, không import class), tự triển khai VND conversion qua `Magento_Directory` (tương đương `Helper\Rate`). | (b) Trong Vnpayment_VNPAY (bản D1 gốc — bị user TL đổi: extension third-party không sửa) · (c) Module thứ 3 riêng — over-modularize |
| D7 | querydr_url thuộc ai? | **Config của Payment Core** (`secomm_paymentcore/vnpay/querydr_url`, admin group "VNPAY" trong section Secomm > Payment Core). Để trống → mọi verify UNKNOWN → cron không cancel (auto-expiry off đến khi nhập). | Đặt trong config extension (vi phạm pristine) |

## Consequences

- AC-013 ("core không reference provider") được diễn giải lại ở mức **code dependency**: core không import class nào của `Vnpayment_VNPAY`; việc biết config paths + wire protocol VNPAY nằm ở adapter `Model/Provider/` — ranh giới "core lifecycle" vs "provider protocol" vẫn rõ.
- `Helper\Rate` không dùng — replicate conversion VND bằng `Magento_Directory\Helper\Data::currencyConvert` (cùng semantics: order currency → VND, ×100).
- Lỗ hổng ownership ở `paymentvnpay/order/info` KHÔNG được sửa (thuộc extension) — logged known risk; Continue Payment controller của Payment Core có guard riêng đầy đủ nên bề mặt mới không kế thừa lỗ hổng.
- Mollie (Phase 2) hoặc provider mới: register adapter trong di.xml của PaymentCore (group `Model/Provider/`) hoặc từ module provider riêng khi có lý do.
- Signature/URL format phải khớp VNPAY spec — nếu extension upgrade đổi format, adapter (không phải extension) cần cập nhật.

## Affected components

`app/code/Secomm/PaymentCore/` (Model/Provider/VnpayAdapter + VnpayCheckoutUrl, di.xml, system.xml group vnpay, config.xml, module.xml +Magento_Directory); `Vnpayment_VNPAY` **zero changes**.

## Related records

- Supersedes: DEC-FEATCSWYEJ-001 (chỉ mục D1; D2–D6 giữ nguyên hiệu lực)
- Feature: FEAT-CSWYEJ
