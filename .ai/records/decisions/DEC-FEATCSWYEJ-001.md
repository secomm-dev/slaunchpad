---
id: DEC-FEATCSWYEJ-001
legacy_ids: []
title: 'Payment Core — adapter trong module provider (D1); expires_at table riêng (D2); race guard 3 lớp lock+state+querydr (D3); default expiry 120 phút + textarea override (D4); snapshot semantics khi disable (D5); TxnRef giữ = increment id (D6)'
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

# Decision Record: Payment Core — architecture decisions (D1–D6)

## Context

FEAT-CSWYEJ (Mode A) xây `Secomm_PaymentCore` quản lý tập trung pending-payment lifecycle + Continue Payment, VNPAY là adapter đầu tiên. Spec FULL [SPEC-FEAT-CSWYEJ §9](../../specs/SPEC-FEAT-CSWYEJ-payment-core.md) nêu 6 quyết định kiến trúc chặn `spec_status: VALID`. Risk categories: payment logic (Vnpayment_VNPAY), order state transitions, DB schema, third-party API contract mới (VNPAY querydr).

## Decision (accepted 2026-08-25 — user acting as TL/SA, `/approve` D1–D6 theo khuyến nghị, gồm D3b force-close 7 ngày)

| # | Câu hỏi | Khuyến nghị | Alternatives bị loại/khả thi |
|---|---|---|---|
| D1 | VNPAY adapter đặt ở đâu? | **Trong `Vnpayment_VNPAY`** (`PaymentCore/VnpayAdapter`) — core không reference provider; provider tự đăng ký qua di.xml của mình | (b) Trong Secomm_PaymentCore — vi phạm AC-013 (core phụ thuộc config VNPAY); (c) Module riêng — over-modularize cho 1 class |
| D2 | Lưu expires_at ở đâu? | **Table riêng `secomm_paymentcore_payment`** — không đụng sales tables, index (status, expires_at) cho cron, room cho audit fields | (b) Extension attribute trên sales_order_payment — đụng core table; (c) additional_information JSON — không index/query được cho cron |
| D3 | Race guard trước cancel: mức nào? | **3 lớp: lock + state check + querydr bắt buộc**; UNKNOWN → skip + retry; force-close record sau N ngày (config) chống dồn | (b) Chỉ lock + state check — không bắt được "paid ở provider, IPN chưa về"; (c) querydr optional theo config — ops cấu hình sai là mất đơn |
| D3b | Force-close ngưỡng N ngày? | **7 ngày** (config `secomm_paymentcore/cron/force_close_days`) | 3/14/30 ngày — TL chốt giá trị |
| D4 | Default expiry + override per-method? | **Default 120 phút** + override qua **textarea `method_code:minutes`** (validate server-side) | (b) 24h — giữ inventory quá lâu cho VN fashion; (c) dynamic-rows UI — phức tạp Phase 1 |
| D5 | Unassign/disable core khi đang có record active? | **Snapshot semantics**: record đã tạo tiếp tục được quản lý đến khi resolve; disable chỉ dừng tạo record mới + cron vẫn đọc record tồn tại | (b) Disable = cron no-op hoàn toàn — đơn active kẹt vĩnh viễn |
| D6 | Continue Payment với VNPAY: TxnRef? | **Giữ TxnRef = increment id** (URL mới, CreateDate mới) — IPN match theo TxnRef=incrementId (Ipn.php:76-78). Sandbox verified 2026-08-25: token `paymentv2/...PaymentMethod.html?token=…` là session 15' do VNPAY sinh/quản lý — nhiều session cùng TxnRef OK, không cần tái sử dụng token cũ; QC còn verify regenerate sau khi token cũ hết hạn | (b) TxnRef suffix `-R1` — vỡ IPN match, phải sửa IPN (Tier 2, scope lớn hơn nhiều) |

## Consequences

- D1+D2 định hình module layout + db_schema (spec §4.2, §4.5).
- D3 quyết định correctness chống cancel nhầm đơn đã thu tiền — AC-010/AC-011 verify.
- D4 giữ đơn giản config admin, validate server-side thay vì dynamic UI.
- D5 tránh order kẹt vĩnh viễn khi ops tắt core.
- D6 giữ IPN hiện tại nguyên vẹn (không phải sửa IPN controller — giảm rủi ro Tier 2).

## Affected components

`app/code/Secomm/PaymentCore/` (module mới); `app/code/Vnpayment/VNPAY/` (adapter + PaymentUrlBuilder refactor + config querydr); db_schema table `secomm_paymentcore_payment`.

## Related records

- Feature: FEAT-CSWYEJ (spec §9 Open Decisions D1–D6)
- DECISIONS.md index: DEC-FEATCSWYEJ-001 (pending)