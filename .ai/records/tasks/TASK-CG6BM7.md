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
decisions: [DEC-TASKCG6BM7-001, DEC-TASKCG6BM7-002, DEC-TASKCG6BM7-003]
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
verified_against_commit: 70416b7aa6889276818b72e311a9955cbf5ed578
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

## Corrective round 2026-09-16 (TL direct source review)

TL review round 1 và chỉ ra ba vấn đề, đã xử lý trong đúng task này (KHÔNG tạo task mới):

1. **BLOCKER — async refund lifecycle sai invariant** ("ZaloPay PROCESSING ≠ Magento refund
   completed"): flow cũ vẫn để core finalize `total_refunded`/`qty_refunded` khi provider chỉ mới
   PROCESSING ⇒ full refund có thể đẩy order CLOSED sớm. Fix theo DEC-TASKCG6BM7-003
   (plugin-orchestrated: provider hỏi ĐÚNG 1 LẦN trước core; PROCESSING → durable pending +
   STOP trước core accounting; cron finalize exact-once qua NATIVE core accounting khi SUCCESS).
   **Ghi nhận trung thực: audit round 1 đã bỏ lỡ tương tác giữa refund gateway flow với
   kế toán/order-state của Magento core** (`CreditmemoService::refund` set `STATE_REFUNDED` trước
   gateway + `RefundOperation` mutate mọi order refund totals) — xem findings.md.
2. **BLOCKER — retry chưa bounded đúng nghĩa**: bằng chứng F4 round 1 CHƯA ĐỦ — transport exception
   trước nay KHÔNG tiêu query budget (loop vô hạn tiềm ẩn cho transport failure). Đã fix:
   mọi attempt thật tiêu budget có evidence (`transport_error:`/`reconcile_error:`/
   `protocol_anomaly:`/`refund_failed:`), non-retryable → terminal. Xem findings.md.
3. **HIGH — race email trùng lặp**: `email_sent`-guard-only không chống được hai finalizer đồng
   thời. Nâng cấp lên atomic dispatch claim `email_dispatch` (DEC-TASKCG6BM7-002 bổ sung).

Scope giữ nguyên: KHÔNG merge, KHÔNG đụng ExtraFee/MoMo/LLMS/Mageplaza vendor/Bitbucket/SMTP/
icon-upload config; mọi thay đổi nằm trong `app/code/Secomm/ZaloPay` + `.ai`.

## Acceptance Criteria

- 34 test matrix round 1 (EMAIL 1–7, RATE 8–15, REFUND COMMAND 16–23, REFUND CRON 24–30,
  RESPONSE 31–34) + ma trận corrective (refund lifecycle 11, retry 13, plugin decision 11,
  email claim 6 test mới) — toàn bộ pass (full suite 260 tests / 937 assertions).
- Receipt đầy đủ, trung thực theo mẫu corrective round; nếu PASS → KHÔNG merge, chỉ push branch
  task lên GitHub, dừng chờ TL review.
- L3 validation: php -l, PHPCS module-wide, full ZaloPay unit suite, setup:di:compile, Secomm
  regression — PASS; integration runtime không khả dụng → ghi `INTEGRATION=ENVIRONMENT_BLOCKED`.
- Receipt §15 đầy đủ, trung thực; nếu PASS → KHÔNG merge, chỉ push branch task lên GitHub, dừng chờ TL.

---

# Corrective round 2 (2026-09-16) — TL direct source review lần 2

TL review lại source trực tiếp và chỉ ra HAI khiếm khuyết BLOCKER còn tồn tại trong chính code
corrective round 1; xử lý trong đúng task này (KHÔNG tạo task mới, KHÔNG merge):

1. **BLOCKER — Magento refund validation PHẢI pass trước khi hỏi provider** (F10): round 1 gọi
   `RefundCommand::execute()` trước `$proceed()` (nơi core chạy `validateForRefund` protected).
   Fix theo DEC-TASKCG6BM7-004 D1: preflight mirror 1:1 toàn bộ core validation
   (`Service/CreditmemoRefundPreflight`, anchor 2.4.8-p5 `CreditmemoService.php:189-219`,
   upgrade coupling + parity tests ghi rõ) + supplementary online-amount > 0; plugin gọi
   preflight trước mọi provider I/O và mọi persistence; pending Credit Memo KHÔNG persist khi
   preflight chưa pass.
2. **BLOCKER — UNKNOWN cạn ngân sách query phải TIẾP TỤC blocking** (F11): fix theo
   DEC-TASKCG6BM7-004 D2: durable semantic state `refund_state` thay cho việc suy "safe" từ
   `query_attempts == MAX`; quarantine at-cap; chỉ `confirmed_fail` mở khóa;
   `confirmed_success` về số dư refundable chuẩn; exhausted unknown giữ nguyên visible + block
   đến khi resolve chủ đích.

Scope bất biến: KHÔNG merge; KHÔNG đụng icon upload, ExtraFee/MoMo/LLMS/Bitbucket/SMTP;
thay đổi chỉ trong `app/code/Secomm/ZaloPay` + `.ai`.

Decisions: thêm DEC-TASKCG6BM7-004. Spec: Revision 3. Plan: appendix 2 (bước 16–18).
Evidence: findings F10–F11, proofs P11–P13, validation round 2.

## Acceptance criteria round 2

- Matrix mới: preflight parity 9 test + plugin provider-never 5 test + unknown-quarantine
  matrix (manager 16 / cron 13 tổng) — full suite **279 tests / 988 assertions OK**.
- Validation L3: php -l clean; PHPCS module-wide 0 errors; setup:di:compile 9/9 exit 0 chạy lại
  trên code cuối; CodeGraph call-chain P13.
- Receipt round 2 theo đúng mẫu TL đưa; nếu PASS → chỉ push `task/zalopay-postfix-audit`
  (GitHub origin), KHÔNG merge, STOP chờ TL review.

---

# Corrective round 3 (2026-09-16) — TL direct source review lần 3

TL review source trực tiếp lần 3 và chỉ ra HAI BLOCKER + BA HIGH còn tồn tại trong chính code
corrective round 2; xử lý trong đúng task này (KHÔNG tạo task mới, KHÔNG merge, KHÔNG đụng
icon upload):

1. **BLOCKER — F12: check-then-act race có thể DOUBLE REFUND.** `hasInFlight()==false →
   preflight → gọi ZaloPay → persist` không concurrency-safe. Fix theo DEC-TASKCG6BM7-005 D1:
   atomic durable claim `active_claim` + unique index NULL-trick
   (`ZALO_PAY_REFUND_ORDER_ACTIVE`, `ZALO_PAY_REFUND_M_REFUND_ID_ACTIVE`); `acquireClaim`
   INSERT autocommit commit TRƯỚC provider HTTP I/O; KHÔNG transaction/row lock xuyên qua
   request ZaloPay; claim mang `m_refund_id` ổn định (RefundCommand tách
   prepare/executePrepared); thua cuộc chết ở DB TRƯỚC HTTP.
2. **BLOCKER — F13: provider SUCCESS + local Magento failure = tiền ra không durable state.**
   Fix theo DEC-005 D3: state mới `provider_success_local_pending`; identity tồn tại TRƯỚC
   provider I/O; cron step 0 CHỈ finalize Magento accounting (KHÔNG query, KHÔNG /refund lần
   nữa); state machine 6 giá trị phân biệt tường minh — không gộp UNKNOWN.
   CRASH/RECOVERY: crash sau `/refund` ⇒ cron query ĐÚNG m_refund_id đã claim, không tạo
   refund mới.
3. **HIGH — F14: row lịch sử bị schema default `processing` chặn.** `hasInFlight` thêm
   `is_processed=0` + blocking-state filter; data patch `BackfillRefundState` backfill 3
   cohort theo bằng chứng; `is_processed=0` chưa resolve → unknown bảo thủ (block).
4. **HIGH — F15: confirmed FAIL để credit memo kẹt PROCESSING.** Cron FAIL → CM
   STATE_PROCESSING → STATE_OPEN (Magento-compatible per core validateForRefund); kế toán
   không đổi; KHÔNG bịa REFUNDED; refund sau thực hiện được.
5. **HIGH — F16: transport UNKNOWN lưu thành `processing`.** State `unknown` semantic riêng,
   cả hai đều block.

Scope bất biến: KHÔNG merge; KHÔNG đụng icon upload, ExtraFee/MoMo/LLMS/Bitbucket/SMTP;
không regress 11 mục DO-NOT-REGRESS; thay đổi chỉ trong `app/code/Secomm/ZaloPay` + `.ai`.

Decisions: thêm DEC-TASKCG6BM7-005 (001–004 giữ nguyên). Spec: Revision 4. Plan: appendix 3
(steps 19–26). Evidence: findings F12–F16, proofs P14–P16 (real-DB E1–E8, call-chain anchor,
crash-recovery), validation round 3.

## Acceptance criteria round 3

- Ma trận mới: atomic-claim manager 24 test / plugin 18 test / cron 18 test + backfill 2 test;
  full suite **296 tests / 1057 assertions OK**.
- Real-DB concurrency evidence: MariaDB 10.4 container throwaway riêng E1–E8 PASS (không đụng
  DB dev chung; container đã xoá).
- Validation L3: php -l clean; PHPCS 0 errors; setup:di:compile PASS (exit 0); CodeGraph
  rebuild + call-chain anchor P15 (claim :176 < provider :179).
- Receipt round 3 theo đúng mẫu TL đưa; nếu PASS → chỉ push `task/zalopay-postfix-audit`
  (GitHub origin), KHÔNG merge, STOP chờ TL review.

## Round 4 (2026-09-16)

- TL source review lần 4 (PRE_HEAD `60b0a4d4`): F17–F22.
- Fix: claim/bind 2 pha cho CM chưa save (F17); state-driven cron + stale-claim policy (F18); backfill v2 + claim ownership (F19/F20); unique constraint declarative (F21); duplicate detect 1062-driver-only (F22).
- Suite 306 tests / 1105 assertions OK; PHPCS 0 errors; real-DB F21/F19 DEFER TL (P18).
- Decisions: DEC-TASKCG6BM7-006.

## Round 5 (2026-09-17)

- TL source review lần 5 (PRE_HEAD `afe1d2ce`): F23–F25.
- Fix F23: LOCAL_READY (`initiating` — cron không bao giờ query, step 0b abandon có chủ đích) vs state mới `provider_request_started` + `provider_request_started_at` pin bằng UPDATE claim-guarded làm gate CUỐI trước provider HTTP; reconciliation grace 120s > HTTP timeout 10s (Laminas default, TransferFactory không override); cron trong grace no-op, hết grace identity-query cùng m_refund_id.
- Fix F24: cohort-3 WHERE của `BackfillRefundState` build bằng hai lời gọi quoteInto một-placeholder riêng.
- Fix F25 (no-defer): real setup:install + setup:upgrade + SHOW CREATE TABLE + legacy cohorts + migration claim proof (`acquireClaim` thật: unresolved REJECTED / resolved ALLOWED / multi-row một owner MIN) + F24 WHERE thật trên MariaDB 10.6 disposable + setup:di:compile PASS — xóa mọi DEFER của round 4.
- Suite 312 tests / 1126 assertions OK; PHPCS 0 errors; .ai: findings/proofs P19/validation round 5, SPEC Rev 6, PLAN Appendix 5, DEC-007.
- Không đụng: icon/logo, ExtraFee, MoMo, LLMS, Bitbucket, `auth.json` (pre-existing).
- Receipt round 5 theo mẫu TL; push chỉ `task/zalopay-postfix-audit`; STOP chờ TL review.

## Round 6 (2026-09-17)

- TL source review lần 6 (PRE_HEAD `ba930d77`): F26.
- Fix F26: cron không còn nhả claim `LOCAL_READY` ngay lập tức (giết request A hợp lệ trong pha bind cục bộ). Chính sách grace/staleness theo DEC-008: fresh `initiating` (tuổi `created_at` < `LOCAL_READY_GRACE_SECONDS = 300`) ⇒ cron no-op hoàn toàn; stale ⇒ `confirmed_fail` + nhả (provider I/O bất khả thi theo cấu trúc); missing `created_at` ⇒ consume budget bounded, không nhả. Anchor bền = `created_at` (set lúc INSERT claim, không đổi) ⇒ SCHEMA_CHANGED=NO.
- Grace tách bạch: LOCAL_READY 300s (pha cục bộ) ≠ reconciliation 120s (HTTP in-flight, timeout 10s).
- Suite 316 tests / 1145 assertions OK; PHPCS 0 errors; setup:di:compile PASS; real-DB race proof P20 21/21 PASS (cron thật qua DI trên MariaDB 10.6 disposable); CodeGraph re-index.
- .ai: findings F26, proofs P20, validation round 6, DEC-TASKCG6BM7-008, SPEC Rev 7, PLAN Appendix 6.
- Không đụng: icon/logo, ExtraFee, MoMo, LLMS, Bitbucket, `auth.json` (pre-existing).
- Receipt round 6 theo mẫu TL; push chỉ `task/zalopay-postfix-audit`; STOP chờ TL review.
