---
id: TASK-CG6BM7
type: task
title: 'ZaloPay post-fix audit & correction — email idempotency, refund async lifecycle, rate validation, response-state semantics'
project_code: SLP
parent: FEAT-ZLP1PF
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-CG6BM7-zalopay-postfix-audit.md
risk: high
status: in_progress
created: 2026-09-16
updated: 2026-09-16
legacy_ids: []
decisions: [DEC-TASKCG6BM7-001, DEC-TASKCG6BM7-002]
decision_assessment: architecture-material
components:
  - Secomm_ZaloPay
source_areas:
  - app/code/Secomm/ZaloPay/Gateway/Helper/Rate.php
  - app/code/Secomm/ZaloPay/Service/OrderFinalizer.php
  - app/code/Secomm/ZaloPay/Gateway/Command/RefundCommand.php
  - app/code/Secomm/ZaloPay/Gateway/Command/RefundQueryCommand.php
  - app/code/Secomm/ZaloPay/Gateway/Response/ResponseMessagesHandler.php
  - app/code/Secomm/ZaloPay/Gateway/Response/TransactionRefundHandler
  - app/code/Secomm/ZaloPay/Cron/RefundCronjob.php
  - app/code/Secomm/ZaloPay/etc/db_schema.xml
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: a48de3cac477cada0882974151db7765c554aa25
last_verified: 2026-09-16
supersedes: []
---

# [SLP][TASK-CG6BM7] ZaloPay post-fix audit & correction

## Bối cảnh (Context)

Commit `181dc1d` ("[Zalo] Fix refund flow, rate TypeError, response state, and order confirmation email")
đã có trong `dev/development/thanhle` là implementation trước đó, KHÔNG phải bằng chứng tuân thủ `.ai`.
Task này tái dựng toàn bộ công việc ZaloPay dưới khung `.ai` (Mode A, high risk), audit độc lập trên
baseline `a48de3ca` (workspace `../slaunchpad-workspaces/zalopay-postfix-audit`, branch
`task/zalopay-postfix-audit`), chỉ fix các vấn đề ZaloPay đã xác nhận, bổ sung test/bằng chứng, chuẩn bị
cho TL review. KHÔNG merge, KHÔNG đụng commit ExtraFee `a48de3ca` (nội dung unrelated), KHÔNG sửa
SMTP/staging config.

## Audit đã xác nhận (đối chiếu contract chính thức ZaloPay 2026-09-16)

Nguồn chính thức: docs.zalopay.vn `docs/specs/order-refund/`, `docs/specs/order-query-refund/`,
`docs/developer-tools/knowledge-base/status-codes/`:
- `return_code` toàn cục: **1 = SUCCESS, 2 = FAIL, 3 = PROCESSING**.
- Refund API là **async**: gọi `v2/refund` rồi BẮT BUỘC query `v2/query_refund` bằng `m_refund_id`
  (định dạng `yymmdd_appid_<identifier>` — merchant TXID dùng để query trạng thái).
- Response `v2/refund` có `refund_id` (int64, "merchant needs to store this field for cross-check");
  response `v2/query_refund` **KHÔNG có** `refund_id` theo schema chính thức.
- sub_return_code refund/query-refund: `-1 REFUND_PENDING`, `-13`, `-14`, `-32`, `-101`, `-429`, `-500`,
  `-999` v.v.

## Phạm vi đã sửa (đã confirm)

1. **Rate** (`Gateway/Helper/Rate.php`): input tiền tệ malformed ("abc", "1,2,3", "10foo", array,
   object) trước nay bị ép `(float)` im lặng về 0 — giờ bị từ chối bằng exception an toàn; chấp nhận
   `1685000`, `1685000.00`, `"1685000"`, `"1685000.0000"`, `"1,685,000.00"`; giữ nguyên conversion.
2. **Email** (`Service/OrderFinalizer.php`): email chỉ gửi sau commit thành công; path FINALIZED
   duplicate nay gửi bù khi `email_sent != 1` (idempotent qua `email_sent` mà OrderSender sync-success
   persist =1); thanh toán KHÔNG rollback khi email fail (giữ nguyên); quyết định retry-scheduled
   documented trong DEC-TASKCG6BM7-002.
3. **Refund async (cron)** (`Cron/RefundCronjob.php` + schema `zalo_pay_refund`): per-item error
   isolation; bounded retry (`query_attempts` < 96); provider FAIL → terminal evidence (`last_error`,
   không loop, không false-success); PROCESSING đúng nghĩa (tăng budget, không coi là lỗi); không còn
   undefined-key; cleanup cron không mất bằng chứng (terminal-FAIL rows là NOT_PROCESSED, cleanup chỉ
   xoá PROCESSED).
4. **RefundCommand messaging** (`Gateway/Command/SubCommand/... `Gateway/Command/RefundCommand.php`):
   chỉ ném `LocalizedException` (Payment::refund bắt đúng loại này); message UI lấy từ map provider
   an toàn (RefundProcessor) — KHÔNG bao giờ lộ raw provider/internal exception text; safe array reads.
5. **ResponseMessagesHandler** (`Gateway/Response/ResponseMessagesHandler.php`): return_code 3
   (PROCESSING) không còn set `is_fraud_detected` (data bug: refund đang xử lý bị gắn cờ fraud);
   1 → approve, 2/unknown → error + fraud flag (giữ semantics).
6. **TransactionRefundHandler**: guard `refund_id` thiếu (không undefined-key).

## Out of scope

ExtraFee / MoMo / LLMS / Mageplaza SMTP / staging config / shared workspace; merge; git history rewrite;
sửa ExtraFee `CanCreditmemoPlugin`; đổi `async_sending` store config (chỉ ghi nhận khuyến nghị cho TL).

## Acceptance Criteria

- 34 test matrix (EMAIL 1–7, RATE 8–15, REFUND COMMAND 16–22, REFUND CRON 23–30, RESPONSE 31–34) pass.
- L3 validation: php -l, PHPCS module-wide, full ZaloPay unit suite, setup:di:compile, Secomm
  regression — PASS; integration runtime không khả dụng → ghi `INTEGRATION=ENVIRONMENT_BLOCKED`.
- Receipt §15 đầy đủ, trung thực; nếu PASS → KHÔNG merge, chỉ push branch task lên GitHub, dừng chờ TL.
