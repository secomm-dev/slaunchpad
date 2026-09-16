---
id: DEC-TASKCG6BM7-005
title: 'ZaloPay refund: atomic durable claim trước provider I/O (active_claim + unique index NULL-trick), state machine v3 (PSLP + semantic UNKNOWN), backfill dữ liệu lịch sử, release creditmemo sau confirmed FAIL'
status: proposed
owners: [sa, tl]
decision_type: correctness
approval_date: 2026-09-16
created: 2026-09-16
last_verified: 2026-09-16
verified_against_commit: b3aa289d73d72de911225db0d35c7ee11b13f2b1
supersedes: []
superseded_by:
amends: [DEC-TASKCG6BM7-003, DEC-TASKCG6BM7-004]
work_items: [TASK-CG6BM7]
---

# Decision: Atomic refund claim + state machine v3 (BLOCKER round 3)

## Bối cảnh — hai BLOCKER + ba HIGH TL bắt được trong chính code corrective round 2

1. **F12 (BLOCKER) — check-then-act race có thể DOUBLE REFUND.** Chuỗi round 2
   `hasInFlight()==false → preflight → gọi ZaloPay → persist` KHÔNG concurrency-safe: hai
   request đồng thời cùng thấy `hasInFlight()==false`, CẢ HAI đều gọi provider /refund ⇒ tiền
   bị refund hai lần. Điều kiện bắt buộc của TL: với một order, đúng MỘT refund attempt nắm
   quyền thực hiện provider I/O tại một thời điểm; claim phải tồn tại TRƯỚC provider HTTP I/O;
   KHÔNG giữ DB transaction/row lock nào xuyên qua request ZaloPay; claim durable phải mang
   sẵn `m_refund_id` ổn định; requester thua cuộc PHẢI fail nguyên tử TRƯỚC provider call;
   DB-level uniqueness, KHÔNG phải `if (!hasInFlight()) {insert}`.
2. **F13 (BLOCKER) — provider SUCCESS + local Magento fail = tiền ra, không còn durable
   state.** Round 2: nếu `$proceed()` (core accounting) throw SAU khi ZaloPay SUCCESS, code
   chỉ rethrow — không còn state nào ghi "provider đã trả tiền, Magento chưa hoàn tất" ⇒ cron
   không có gì để finalize, và một attempt mới sẽ gọi /refund LẦI NỮA (double refund thật).
   Điều kiện bắt buộc: identity refund attempt ổn định phải tồn tại locally TRƯỚC khi provider
   I/O bắt đầu; provider SUCCESS + local finalize fail = durable state tách biệt
   (provider_success_local_pending); retry/cron CHỈ finalize Magento accounting và TUYỆT ĐỐI
   KHÔNG gọi /refund lần nữa; state machine phải phân biệt unknown / provider-success-local-
   incomplete / confirmed FAIL / finalized SUCCESS — không gộp chung UNKNOWN.
3. **F14 (HIGH) — dữ liệu lịch sử bị chặn bởi schema default.** `refund_state` default
   `processing` làm MỌI row cũ (đã `is_processed=1`, refund xong từ lâu) rơi vào blocking set
   ⇒ order có lịch sử refund thành công không thể refund tiếp. Yêu cầu: `hasInFlight` phải có
   `is_processed=0`; data patch backfill theo bằng chứng; row `is_processed=0` chưa resolve →
   UNKNOWN bảo thủ (chấp nhận block); bắt buộc bằng chứng migration.
4. **F15 (HIGH) — confirmed FAIL để credit memo kẹt PROCESSING mãi mãi.** Sau FAIL đã xác
   nhận (tiền KHÔNG bị refund), kế toán không đổi, credit memo pending phải không còn present
   as PROCESSING, refund sau đó (sửa xong) phải thực hiện được; KHÔNG bịa REFUNDED; dùng state
   Magento-tương-thích (STATE_OPEN theo core `validateForRefund`).
5. **F16 (HIGH) — transport UNKNOWN bị lưu thành `processing`.** Outcome không xác nhận phải
   là semantic UNKNOWN (cũng block), không được mượn nghĩa "provider đang xử lý" của
   PROCESSING.

## Quyết định

### D1 — Atomic claim: `active_claim` + unique index NULL-trick (F12)

- Cột mới `zalo_pay_refund.active_claim` smallint NULL (không default, không NOT NULL) —
  `1` khi row đang nắm claim, `NULL` khi đã terminal.
- Hai unique index: `(order_id, active_claim)` = `ZALO_PAY_REFUND_ORDER_ACTIVE` và
  `(m_refund_id, active_claim)` = `ZALO_PAY_REFUND_M_REFUND_ID_ACTIVE`. Tính chất MySQL then
  chốt: **unique index chứa NULL coi các row NULL là phân biệt** ⇒ hàng chục row lịch sử
  (active_claim NULL) cùng một order tồn tại vô tư; tối đa MỘT row `active_claim=1` cho mỗi
  order và mỗi `m_refund_id` — chính DB là người trọng tài, không phải check-then-act.
- `acquireClaim()` = MỘT INSERT dòng đơn ở autocommit (KHÔNG bọc transaction — commit phải
  xảy ra TRƯỚC provider I/O; row lock/transaction giữ xuyên HTTP là cấm theo điều kiện TL):
  bẫy duplicate-key (`SQLSTATE 23000` / `1062` / "Duplicate entry") → `LocalizedException`
  "active or awaiting reconciliation" — requester thua dừng TRƯỚC provider call, không bao giờ
  chạm HTTP; lỗi khác → abort safe ("could not be recorded locally"), KHÔNG gọi provider.
- Chuỗi plugin (anchor file:line, `CreditmemoRefundPlugin.php`): guard `:111-118` → pass-through
  `:121-124` → offline guard `:126-132` → preflight `:142` → pin invoice txn `:148-153` →
  `prepare()` identity KHÔNG I/O `:165` → **`acquireClaim` :176** → **`executePrepared` :179**
  (provider call DUY NHẤT). Claim commit trước provider I/O — invariant `CLAIM BEFORE I/O`.
- Claim chỉ được nhả khi terminal `confirmed_success`/`confirmed_fail`
  (`markConfirmedFail`/`markConfirmedSuccess`/`finalizeSuccess` bind `ACTIVE_CLAIM => null`);
  `unknown` và `provider_success_local_pending` GIỮ claim (quarantine giữ chỗ ở DB level —
  bằng chứng E7 phần Bằng chứng).

### D2 — RefundCommand tách prepare/executePrepared (identity trước I/O, F12+CRASH)

- `RefundCommand::execute()` giữ contract public cũ (= `prepare()` + `executePrepared()`),
  thêm `prepare(array): ?RefundRequest` (build `m_refund_id` ổn định + reconciliation payload,
  KHÔNG network I/O; `null` khi marker-skip) và
  `executePrepared(RefundRequest, array): RefundOutcome` (provider call DUY NHẤT cho identity
  đã claim). VO mới `Gateway/Command/RefundRequest` mang
  `(requestData, mRefundId, queryPayload, tracking)` — claim INSERT mang đúng `m_refund_id`
  provider sẽ thấy ⇒ crash sau `/refund` để lại row có CÙNG `m_refund_id`; cron/recovery query
  bằng ĐÚNG identity đó, KHÔNG BAO GIỜ tạo refund request mới.

### D3 — State machine v3: 6 state, PSLP tách khỏi UNKNOWN (F13/F16)

- `initiating` (claim vừa commit, chưa có outcome) → `processing` | `unknown` |
  `confirmed_fail`.
- `processing` → `confirmed_success` | `confirmed_fail` | `unknown` (quarantine khi cạn
  budget).
- `provider_success_local_pending` (MỚI, F13) → CHỈ `confirmed_success` (finalize local
  only). Cron step 0 xử lý trước mọi bước khác (RefundCronjob.php:136-165): gọi
  `finalizeSuccess` và TUYỆT ĐỐI không query_refund; finalize throw → `consumeQueryBudget`
  (bounded, evidence `reconcile_error:`), KHÔNG bao giờ re-refund.
- `unknown` (F16): mọi outcome không xác nhận (transport, protocol anomaly, reconcile fail,
  state drift, malformed payload, backfill row cũ `is_processed=0`) — semantic riêng, KHÔNG
  mượn `processing`.
- Blocking set = `initiating + processing + unknown + provider_success_local_pending`
  (`hasInFlight` 3 filter: `is_processed = 0` + 4-state IN + budget `< MAX` — filter
  `is_processed` là fix F14). Terminal `confirmed_success`/`confirmed_fail` nhả claim + mở
  khóa.

### D4 — Backfill/migration dữ liệu lịch sử (F14)

- `Setup/Patch/Data/BackfillRefundState.php` — 3 UPDATE theo THỨ TỰ BẰNG CHỨNG:
  1. `is_processed=1 AND last_error LIKE 'refund_failed:%'` → `confirmed_fail`;
  2. còn lại `is_processed=1` → `confirmed_success` (refund đã hoàn tất từ trước);
  3. `is_processed=0` → `unknown` (bảo thủ: chưa resolve ⇒ block đến khi reconcile chủ đích).
- Schema default `refund_state` = `processing` chỉ có ý nghĩa cho row MỚI do
  `acquireClaim` insert tường minh; mọi row existing được patch chạm. Bằng chứng migration =
  unit test pin đúng 3 cohort + đúng evidence filter (`BackfillRefundStateTest`).

### D5 — Release credit memo sau confirmed FAIL (F15)

- Cron FAIL branch (`RefundCronjob.php:292-309`) → `terminate(confirmed_fail)` +
  `releaseCreditmemoAfterFail` (:332-350): nếu CM đang `STATE_PROCESSING` (4, state parked
  của plugin) → `setState(Creditmemo::STATE_OPEN)` + save qua repository (STATE_OPEN = state
  core `validateForRefund` chấp nhận cho refund tiếp); state khác → không đụng. Release fail →
  critical log, KHÔNG propagate (row đã confirmed_fail, non-blocking; CM cần follow-up thủ
  công). Kế toán order/invoice KHÔNG BAO GIỜ bị đụng trong path này (tiền chưa ra).

## Hệ quả / giới hạn

- `RefundOutcomeMarker` vẫn process-scoped và ĐỦ: sau D1, quyền gọi provider do DB claim bảo
  hộ, không còn phụ thuộc marker cho cross-request safety; marker chỉ chống double-call
  trong cùng process (plugin request + finalizeSuccess cục bộ).
- `unknown` block vĩnh viễn đến khi thao tác vận hành tường minh — giữ đúng nghĩa bảo thủ của
  DEC-TASKCG6BM7-004 D2.
- MariaDB evidence thực hiện trên container throwaway riêng (`zt-mariadb-evidence`, đã xoá),
  KHÔNG đụng DB dev chung; full evidence E1–E8 xem proofs.md P14.

## Bằng chứng

- Schema: `etc/db_schema.xml` (`active_claim` + 2 unique index) + whitelist; `Api/Data/
  RefundInterface.php` (6 state constants + `ACTIVE_CLAIM` accessor); `Model/Data/RefundData.php`.
- `Service/PendingRefundManager.php`: `acquireClaim` :145 (INSERT autocommit + duplicate-key),
  `hasInFlight` :106 (3 filter), `markProcessing` :198, `markUnknown` :214,
  `markConfirmedFail` :232 (nhả claim), `markProviderSuccessLocalPending` :252,
  `markConfirmedSuccess` :273 (nhả claim), `terminate` :385 (confirmed_fail nhả claim).
- `Plugin/Model/Service/CreditmemoRefundPlugin.php`: chuỗi guard→preflight→prepare→claim→
  provider anchor :111/:142/:165/:176/:179; PSLP :241-251; markConfirmedSuccess :256.
- `Cron/RefundCronjob.php`: PSLP shortcut :136-165; REFUNDED bookkeeping :191-202; drift
  terminate :207-223; FAIL release :292-309 + `releaseCreditmemoAfterFail` :332-350.
- Tests: `PendingRefundManagerTest` (24/89), `CreditmemoRefundPluginTest` (18/75),
  `RefundCronjobTest` (18/52), `BackfillRefundStateTest` (2/10); full suite **296 tests /
  1057 assertions OK**.
- DB concurrency: MariaDB 10.4 throwaway container, E1–E8 (đôi song song, duplicate-key,
  NULL-trick lịch sử, quarantine giữ slot) — proofs.md P14.
