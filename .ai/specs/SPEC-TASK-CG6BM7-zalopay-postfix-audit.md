Specification ID: SPEC-TASK-CG6BM7

# SPEC-TASK-CG6BM7 — ZaloPay post-fix audit & correction

- **Work item**: [TASK-CG6BM7](../records/tasks/TASK-CG6BM7.md) (Mode A, high risk — payment/order lifecycle)
- **Baseline**: `a48de3cac477cada0882974151db7765c554aa25` (`dev/development/thanhle`), branch `task/zalopay-postfix-audit`
- **Scope**: `app/code/Secomm/ZaloPay` only
- **Ngày**: 2026-09-16

## 1. Goal

Khép lại các lỗ hổng đúng-sai còn lại của module ZaloPay sau commit `181dc1d` (không rewrite),
được audit độc lập trên baseline `a48de3ca`, đối chiếu contract chính thức ZaloPay (docs.zalopay.vn,
truy 2026-09-16), với nguyên tắc: **money/code paths never trust unvalidated provider input; never
coerce malformed money to zero; terminal states are explicit; email is post-commit and idempotent.**

## 2. Expected Behavior (hành vi chuẩn sau fix)

### 2.1 Rate (Gateway/Helper/Rate.php)

- `getVndAmount()` / `getVndAmountByCurrency()` CHẤP NHẬN: int/float, chuỗi số chuẩn `1685000`, `1685000.00`, `"1685000"`,
  `"1685000.0000"`, và định dạng nhóm 3 chữ số `"1,685,000.00"` (kể cả `-1,234.56`).
- TỪ CHỐI (throw `LocalizedException`, không bao giờ convert về 0): `"abc"`, `"1,2,3"`, `"10foo"`,
  `true/false`, `null`, array, object, chuỗi rỗng.
- Giữ nguyên logic conversion: VND → round; non-VND → `currencyConvert` rồi round; lỗi conversion
  vẫn throw `LocalizedException` như cũ.

### 2.2 Email (Service/OrderFinalizer.php)

- Email xác nhận đơn **chỉ** chạy sau khi DB transaction commit (không có path nào gửi email trước
  commit; không gửi khi capture fail — capture throw → rollback → rethrow trước block email).
- Gửi fresh: sau commit, `OrderSender::send()` — sync thành công persist `email_sent = 1` (native);
  sync fail → log critical, order vẫn FINALIZED (payment KHÔNG rollback).
- Duplicate (FINALIZED recovery — Return revisit / duplicate IPN / recovery): KHÔNG gửi lại khi
  `email_sent == 1`; gửi bù đúng 1 lần khi `email_sent != 1` (khắc phục mail-loss window
  crash-between-commit-and-email; driver = mọi caller `finalizeOrRecover`, documented trong
  DEC-TASKCG6BM7-002).
- IPN/Return/Recovery duplicate → không bao giờ mail trùng (guard `email_sent`).

### 2.3 Refund async lifecycle (Cron/RefundCronjob.php + zalo_pay_refund)

- Schema `zalo_pay_refund` thêm 2 cột (whitelist cập nhật): `query_attempts` smallint unsigned
  NOT NULL DEFAULT 0 (budget truy vấn), `last_error` text NULL (bằng chứng lỗi cuối — safe text only).
- Cron mỗi item độc lập (try/catch (\Throwable) per item — một item hỏng không chặn batch).
- Bounded: selection filter `is_processed = NOT_PROCESSED AND query_attempts < 96` (~24h ở cron
  15 phút) → row hết budget bị bỏ, KHÔNG query nữa (explicit exhaustion + log critical khi đạt cap).
- Provider FAIL (return_code 2) → **terminal**: saturate `query_attempts = 96` + `last_error` =
  message an toàn từ map (RefundProcessor, theo sub_return_code nếu numeric), row giữ
  NOT_PROCESSED → tự rơi khỏi selection (filter attempts), KHÔNG query lại, log critical một lần.
  `last_error` là **terminal-only marker** — chỉ FAIL ghi last_error.
- PROCESSING (3) / unknown / missing return_code / transport exception / payload malformed →
  TĂNG `query_attempts` +1 (evidence qua cron log; `last_error` KHÔNG bị ghi — terminal-only);
  tới cap → ngừng (explicit exhaustion, log critical).
- SUCCESS (1) → creditmemo STATE_REFUNDED (lưu qua repository), row `is_processed = PROCESSED`,
  `last_error = NULL`, log info.
- Cleanup cron giữ nguyên hành vi xoá PROCESSED monthly — bằng chứng terminal-FAIL (NOT_PROCESSED +
  last_error) không bao giờ bị xoá.

### 2.4 RefundCommand messaging (Gateway/Command/RefundCommand.php)

- Chỉ throw `LocalizedException` (hoặc CommandException — cũng là LocalizedException) — KHÔNG còn
  generic `new Exception` (Payment::refund chỉ bắt LocalizedException; raw propagation chặn
  creditmemo save với raw message).
- Message UI luôn từ RefundProcessor map dựa trên sub_return_code của response HIỆN TẠI (không dùng
  sub_return_code cũ của response refund khi query fail); khi không xác định được → message generic
  an toàn "Zalopay: Refund failed. Please try again later." — KHÔNG BAO GIỜ lộ raw transport/internal
  exception text ra UI.
- Safe array reads (`?? null` + strict check) cho return_code/sub_return_code.
- Giữ nguyên finally-block PROCESSING persistence (load-bearing: entity_id cho FK refund row qua
  `$creditMemo->save()` trực tiếp).
- Bỏ comment `//TODO change condition` (điều kiện hiện tại đúng theo contract; lý do ghi trong spec):
  finally vẫn chỉ persist khi `statusCode === REFUND_PROCESSING` — instant SUCCESS không tạo row
  (evidence gap MEDIUM, ghi nhận trong evidence), PROCESSING tạo row NOT_PROCESSED cho cron.

### 2.5 ResponseMessagesHandler (Gateway/Response/ResponseMessagesHandler.php)

- return_code 1 → `approve_messages` (không fraud flag).
- return_code 2/unknown → `error_messages` + `setIsFraudDetected(true)` (giữ nguyên semantics cũ).
- return_code 3 (PROCESSING) → message được ghi (key `error_messages`) nhưng **KHÔNG** set fraud
  flag — PROCESSING là trạng thái bình thường của provider, không phải fraud/error.
- Missing return_code → không fatal (safe read).

### 2.6 TransactionRefundHandler

- `refund_id` thiếu → không undefined-key; handler skip transaction bookkeeping an toàn.

## 3. Constraints

- Scope: chỉ `app/code/Secomm/ZaloPay`. Không sửa ExtraFee/MoMo/LLMS/Mageplaza source, không đụng
  SMTP/staging/system config, không merge, không force push, không amend `181dc1d`/`a48de3ca`.
- `zalo_pay_refund` chỉ thêm cột tối thiểu (2 cột, có precedent `secomm_zalopay_payment_attempt`.
  recovery_attempts/recovery_exhausted); whitelist phải cập nhật.
- Không thêm claim/lock architecture cho refund cron (query là idempotent provider GET; hai worker
  cùng xử lý 1 row là benign — documented, DEC-TASKCG6BM7-001).
- Không đổi `m_refund_id` generation (mỗi lần submit refund là một refund request mới; cron chỉ
  QUERY bằng m_refund_id đã lưu — idempotent; re-submit do admin tạo creditmemo mới sẽ được provider
  dedup qua bookkeeping zp_trans_id + amount; residual risk documented).
- No PII/provider raw data vào logs; message UI qua `__()` + map.

## 4. Out of Scope

- `RefundQueryCommand::execute()` TODO-stub + plugin `generateMac` chết trên virtualType
  `ZaloPayRefundDataBuilder` + handler/validator unused của RefundQueryCommand: dead-wiring MEDIUM —
  ghi nhận trong evidence, không sửa (avoid unnecessary diff).
- RefundProcessor REFUND_MESSAGES map inaccuracies (-1 = REFUND_PENDING thực ra là "System error.",
  thiếu 0/-32/-101-ORDER_NOT_FOUND/-999): MEDIUM — ghi nhận, không sửa text map trong task này.
- m_refund_id fresh-per-attempt khi admin re-submit: documented residual risk.
- RefundCommand instant-SUCCESS không persist refund row (evidence gap MEDIUM).
- Email retry-scheduled driver: khuyến nghị `sales_email/general/async_sending` cho TL (config-level,
  ngoài scope code).

## 4bis. Credit memo / order state (§8 audit — document-only)

- Luồng: `CreditmemoService::refund` → `Creditmemo::register()` (order totals/state đổi NGAY khi
  register — order refunded amounts phản ánh trước khi gateway xác nhận) → `Payment::refund()` →
  RefundCommand. Full refund khi `canRefund()` false → order → CLOSED (ExtraFee `CanCreditmemoPlugin`
  liên quan chuỗi COMPLETE→CLOSED — ExtraFee dependency, KHÔNG sửa).
- Fail after processing: RefundCommand throw (giờ là LocalizedException) → CreditmemoService rollback
  creditmemo save; nếu refund đã PROCESSING trước đó thì creditmemo ở STATE_PROCESSING + refund row
  NOT_PROCESSED → cron tiếp tục query tới terminal (mới: bounded + terminal FAIL explicit).
- Multiple/partial refunds: mỗi creditmemo một refund row riêng (FK credit_memo_id), cron xử lý
  độc lập per row — hoạt động đúng với partial/full/multiple sau fix.
- Duplicate success: cron 2 lần thấy return_code=1: lần đầu set PROCESSED → không còn trong selection;
  race 2 worker cùng thấy 1: cả hai cùng set creditmemo REFUNDED + row PROCESSED (idempotent same-end-state), creditmemo được save 2 lần với cùng state — benign.
- PROCESSING→processing→REFUNDED: creditmemo PROCESSING (plugin state) giữ trong lúc provider xử lý,
  cron flip sang REFUNDED khi terminal.

## 5. Decisions

- DEC-TASKCG6BM7-001 — bounded retry schema + terminal-FAIL design (2 cột, không config, không claim
  architecture; cleanup-an-band-by-design).
- DEC-TASKCG6BM7-002 — email idempotency + retry semantics (post-commit only, email_sent guard,
  driver = finalizeOrRecover callers; khuyến nghị async_sending cho TL).

## 6. Test Matrix (34 — §13)

| # | Nhóm | Test | File |
|---|------|------|------|
| 1 | EMAIL | fresh finalize → gửi đúng 1 lần sau commit | OrderFinalizerTest |
| 2 | EMAIL | FINALIZED duplicate + email_sent=1 → không gửi lại | OrderFinalizerTest |
| 3 | EMAIL | capture fail → không email, rollback + rethrow | OrderFinalizerTest |
| 4 | EMAIL | contract mismatch → không email | OrderFinalizerTest |
| 5 | EMAIL | email exception → order giữ FINALIZED (không rollback) | OrderFinalizerTest |
| 6 | EMAIL | IPN/Return/Recovery duplicates → không mail trùng | OrderFinalizerTest |
| 7 | EMAIL | FINALIZED duplicate + email_sent!=1 → gửi bù 1 lần | Order backfill |
| 8 | RATE | `1685000` (int) OK | RateTest |
| 9 | RATE | `1685000.00` (float) OK | RateTest |
| 10 | RATE | `"1685000"` OK | RateTest |
| 11 | RATE | `"1685000.0000"` OK | RateTest |
| 12 | RATE | `"1,685,000.00"` OK | RateTest |
| 13 | RATE | `"abc"` → throw | RateTest |
| 14 | RATE | `"1,2,3"` → throw | RateTest |
| 15 | RATE | `"10foo"`, array, object, bool, null → throw | RateTest |
| 16 | REFUND CMD | return_code=1 → success, không throw | RefundCommandTest |
| 17 | REFUND CMD | 3 → query=1 → success | RefundCommandTest |
|  mocks 18 | REFUND CMD | 3 → query=3 → row NOT_PROCESSED + creditmemo PROCESSING, không throw | RefundCommandTest |
| 19 | REFUND CMD | return_code=2 → LocalizedException, message từ map | RefundCommandTest |
| 20 | REFUND CMD | query transport fail khi PROCESSING → safe message (không raw), finally persist row | RefundCommandTest |
| 21 | REFUND CMD | response thiếu return_code → safe failure | RefundCommandTest |
| 22 | REFUND CMD | exception type = LocalizedException (Payment::refund bắt được) | RefundCommandTest |
| 23 | REFUND CMD | UI message không chứa raw exception/provider text | RefundCommandTest |
| 24 | REFUND CRON | per-item isolation (item 1 throw, item 2 vẫn xử lý) | RefundCronjobTest (unit) |
| 25 | REFUND CRON | SUCCESS → creditmemo REFUNDED + row PROCESSED + last_error NULL | RefundCronjobTest |
| 26 | REFUND CRON | FAIL → last_error set, row giữ NOT_PROCESSED, không requery | RefundCronjobTest |
| 27 | REFUND CRON | PROCESSING → attempts++ và giữ NOT_PROCESSED | RefundCronjobTest |
| 28 | REFUND CRON | cap đạt → skip (collection filter + guard) | RefundCronjobTest |
| 29 | REFUND CRON | collection filter loại last_error + cap rows | RefundCronjobTest |
| 30 | REFUND CRON | malformed additional_information → item skip, batch sống | RefundCronjobTest |
| 31 | RESPONSE | code 1 → approve_messages, không fraud | ResponseMessagesHandlerTest |
| 32 | RESPONSE | code 2 → error_messages + fraud | ResponseMessagesHandlerTest |
| 33 | RESPONSE | code 3 → message, KHÔNG fraud flag | ResponseMessagesHandlerTest |
| 34 | RESPONSE | unknown code → error + fraud; missing return_code → không fatal | ResponseMessagesHandlerTest |

## 7. Validation (L3)

php -l toàn bộ file đổi; PHPCS module-wide; full ZaloPay unit suite; `setup:di:compile`;
Secomm regression suite; CodeGraph structural proofs + git grep proofs (email no-pre-commit;
RefundCronjob ownership; ResponseMessagesHandler consumers). Integration runtime không khả dụng →
`INTEGRATION=ENVIRONMENT_BLOCKED`, không tuyên bố PASS cho integration.

---

# Revision 2 — 2026-09-16 (corrective round; cùng work item, cùng baseline corrective head `70416b7a`)

## Lý do

TL direct source review round 1 xác nhận 3 khiếm khuyết mà SPEC revision 1 đã không bao phủ:

1. **BLOCKER — async refund lifecycle vi phạm invariant "ZaloPay PROCESSING ≠ Magento refund
   completed"**: revision 1 chỉ đặc tả retry/cron trên row `zalo_pay_refund`, không đặc tả ràng
   buộc với kế toán Magento core (tổng refund, order state). Với return_code 3, flow cũ vẫn để core
   finalize `total_refunded`/`qty_refunded` ⇒ full refund có thể đẩy order CLOSED khi tiền chưa
   xác nhận. Audit round 1 (evidence F4) cũng chưa đủ: transport exception không tiêu query budget.
2. **BLOCKER — retry chưa bounded đúng nghĩa**: mọi genuine attempt phải có state progression đo
   được; non-retryable (malformed payload/thiếu identity/thiếu creditmemo) phải terminal có evidence.
3. **HIGH — race trùng mail**: `email_sent`-guard-only không chống được hai finalizer đồng thời.

## Yêu cầu bổ sung (tóm tắt normative)

- **FR-R2.1**: Provider refund được hỏi ĐÚNG MỘT LẦN cho một yêu cầu refund, TRƯỚC khi bất kỳ
  order/creditmemo total nào bị mutate; SUCCESS → kế toán Magento do NATIVE core flow nắm
  (exactly once); PROCESSING → không total thay đổi, không CLOSED; FAIL/transport → totals bất biến.
- **FR-R2.2**: Refund đang pending (PROCESSING/outcome unknown) chặn yêu cầu refund thứ hai cho
  cùng order; reconcile theo `m_refund_id`, KHÔNG BAO GIỜ re-request (chống double refund).
- **FR-R2.3**: Cron finalize chỉ khi provider SUCCESS, trong MỘT transaction có row lock
  (`FOR UPDATE`) + re-check `is_processed`; recovery branch khi creditmemo đã REFUNDED;
  KHÔNG tự set order CLOSED (core tự xác định theo accounting).
- **FR-R2.4**: Retry classification bắt buộc: transport → tiêu budget + `transport_error:`;
  provider FAIL → terminal + `refund_failed:` (message từ provider map an toàn); malformed/
  missing identity/missing creditmemo/state drift → terminal + `reconcile_error:`; mã lạ →
  `protocol_anomaly:`. `last_error` không chứa secret/raw provider internals.
- **FR-R2.5**: Email xác nhận order: atomic dispatch claim (`email_dispatch`) bên trong tx
  finalize (row FOR UPDATE serialize); claim loss ⇒ KHÔNG gửi; send fail ⇒ release claim,
  KHÔNG rollback payment/order; "sent" bền vững = `email_sent=1` (Magento); grace reclaim 900s.
- **NFR-R2.6**: Bằng chứng call-chain phải đọc source core (vendor) vì CodeGraph không index
  vendor; unit mocks phải khai báo giới hạn một cách trung thực; integration không khả dụng ⇒
  `INTEGRATION=ENVIRONMENT_BLOCKED`.

## Ma trận test bổ sung (bổ sung vào §13)

- REFUND LIFECYCLE (11): PendingRefundManagerTest.
- PLUGIN DECISION (11): CreditmemoRefundPluginTest.
- RETRY/RECONCILIATION mở rộng (13): RefundCronjobTest (rewrite).
- PROVIDER-ONLY COMMAND (11): RefundCommandTest (rewrite).
- EMAIL CONCURRENCY (3 + resource 3): OrderFinalizerTest EMAIL 8–10 + PaymentAttemptResourceTest.

---

# Revision 3 — Corrective round 2 (2026-09-16)

## Invariants bổ sung (không thay thế Revision 2)

- **INV-R3.1 (Magento validation trước provider):** `Magento refund validation PASS → provider
  refund mới được yêu cầu`. Plugin gọi preflight mirror (`CreditmemoRefundPreflight`,
  anchor `CreditmemoService.php:189-219` 2.4.8-p5) trước MỌI provider I/O và MỌI persistence;
  pending Credit Memo KHÔNG persist khi preflight chưa pass. Over-refund / creditmemo đã
  processed / invalid order / online amount <= 0 ⇒ provider NEVER called. Mirror là option 3
  (core method protected): parity tests pin từng check; upgrade Magento phải re-diff mirror.
- **INV-R3.2 (blocking là semantic state):** cột `zalo_pay_refund.refund_state` quyết định
  blocking — `processing` + `unknown` BLOCK; `confirmed_fail` mở khóa (provider từ chối tường
  minh); `confirmed_success` về số dư refundable chuẩn. `query_attempts == MAX` KHÔNG tự mang
  nghĩa "safe to refund"; budget exhaustion tự quarantine → `unknown`; row unknown giữ nguyên
  visible + block đến khi được resolve chủ đích (thao tác vận hành tường minh).
- **NFR-R3.1:** mọi thay đổi round 2 nội bộ `app/code/Secomm/ZaloPay`; không regress các
  invariant Revision 2 (PROCESSING không mutate totals/không đóng order; SUCCESS native
  accounting; FAIL không đụng kế toán; email claim atomic; retry bounded).

## Ma trận test bổ sung (bổ sung vào §13)

- PREFLIGHT PARITY (9): `CreditmemoRefundPreflightTest`.
- PROVIDER-NEVER PLUGIN MATRIX (5 mới; tổng plugin 16): `CreditmemoRefundPluginTest`.
- UNKNOWN-QUARANTINE (manager 16 / cron 13 tổng): `PendingRefundManagerTest`,
  `RefundCronjobTest` — processing blocks; transport/protocol/finalize-exhausted blocks
  (unknown); confirmed FAIL không block; processed SUCCESS không block.

---

# Revision 4 — Corrective round 3 (2026-09-16, TL direct source review lần 3)

## Invariants bổ sung (không thay thế Revision 2–3)

- **INV-R4.1 (atomic claim, F12):** với một order, đúng MỘT refund attempt nắm quyền thực
  hiện provider refund I/O tại một thời điểm; claim durable PHẢI commit TRƯỚC provider HTTP
  I/O; KHÔNG DB transaction/row lock nào được giữ xuyên qua request ZaloPay; claim mang sẵn
  `m_refund_id` ổn định; requester thua cuộc fail nguyên tử ở DB (unique index
  `(order_id, active_claim)` + `(m_refund_id, active_claim)`, NULL-trick cho row lịch sử)
  TRƯỚC khi chạm provider; KHÔNG check-then-act.
- **INV-R4.2 (provider SUCCESS + local failure, F13):** identity refund attempt ổn định tồn
  tại locally TRƯỚC provider I/O bắt đầu; provider SUCCESS + local finalize failure = durable
  state `provider_success_local_pending`; retry/cron CHỈ finalize Magento accounting và
  KHÔNG BAO GIỜ gọi /refund lần nữa; state machine phân biệt tường minh `unknown` /
  `provider_success_local_pending` / `confirmed_fail` / `confirmed_success` — không gộp.
- **INV-R4.3 (crash/recovery):** crash sau `/refund` ⇒ cron/recovery query bằng ĐÚNG
  `m_refund_id` đã claim, KHÔNG BAO GIỜ tạo refund request mới.
- **INV-R4.4 (historical rows, F14):** blocking guard thêm `is_processed = 0`; data patch
  backfill theo bằng chứng (`is_processed=1 AND last_error LIKE 'refund_failed:%'` →
  confirmed_fail; còn lại `is_processed=1` → confirmed_success; `is_processed=0` → unknown
  bảo thủ); migration evidence bắt buộc.
- **INV-R4.5 (confirmed FAIL, F15):** sau confirmed FAIL — tiền KHÔNG refund, kế toán
  không đổi, credit memo pending không còn present as PROCESSING (về `STATE_OPEN` per core
  validateForRefund), refund sau đó thực hiện được; KHÔNG bịa REFUNDED.
- **INV-R4.6 (semantic UNKNOWN, F16):** outcome transport không xác nhận được lưu state
  `unknown` (cũng block), KHÔNG mượn `processing`.
- **NFR-R4.1:** không regress 11 mục DO-NOT-REGRESS (validation trước provider; PROCESSING
  ≠ completed; PROCESSING không mutate totals/không CLOSE; SUCCESS finalize exactly once;
  full SUCCESS → CLOSED qua Magento; FAIL không đụng kế toán; unknown/exhausted block;
  transport retry bounded; email atomic; email fail không rollback payment). Robustness:
  invalid order không fatal trước preflight (Magento-compatible exception, provider call 0,
  persistence 0).

## Ma trận test bổ sung (bổ sung vào §13)

- ATOMIC CLAIM (manager 24 tổng; plugin 18 tổng): `PendingRefundManagerTest`,
  `CreditmemoRefundPluginTest` — two-concurrent → one durable claim → one provider call;
  same m_refund_id no two active attempts; claim trước provider (order-assertion).
- PSLP (F13): plugin `testProviderSuccessWithLocalFailureLandsPendingState`; cron
  `testPslpRowFinalizesLocallyWithoutProviderQuery` + `testPslpFinalizeFailureConsumesBudget`.
- CRON v3 (18 tổng): `RefundCronjobTest` — REFUNDED bookkeeping; drift terminate; FAIL
  release STATE_OPEN + swallow.
- BACKFILL (F14): `BackfillRefundStateTest` (2) — 3 cohort evidence-order.
- REAL-DB CONCURRENCY (E1–E8): MariaDB 10.4 throwaway container — proofs.md P14.

## Revision 5 — Round 4 (F17–F22)

- Claim/bind 2 pha (DEC-006): claim INSERT `credit_memo_id NULL` → save CM (entity_id thật) → bind UPDATE guard `active_claim = 1` → mới provider. `credit_memo_id` nullable (FK giữ), REQUIRED trước provider I/O.
- Stale-claim policy: INITIATING không bound CM = `abandoned_before_provider_io` (confirmed_fail, nhả claim); cron state-driven (OPEN = state bind hợp lệ; chỉ CANCELED = drift); CM park PROCESSING sau bind.
- Backfill v2: 4 cohort evidence-ordered (fail giữ nguyên; success disjoint; ambiguity → UNKNOWN; unresolved → UNKNOWN) + claim ownership MIN(entity_id)/order (`active_claim=1` chặn claim mới qua unique).
- Unique declarative chuẩn 2.4.8-p5 (`<constraint xsi:type="unique">`); duplicate detect chỉ nhận driver 1062 (FK/23000 khác không phải conflict).

## Revision 6 — Round 5 (F23–F25)

- State machine v5 (DEC-007): `initiating` siết thành **LOCAL_READY** (provider chắc chắn chưa contact — cron KHÔNG bao giờ query, step 0b terminate `abandoned_before_provider_io` → confirmed_fail cho mọi initiating); state mới **`provider_request_started`** + cột **`provider_request_started_at`** (datetime nullable, UTC) pin bằng MỘT UPDATE claim-guarded (`entity_id = ? AND active_claim = 1`) — gate CUỐI trước `executePrepared`; mark fail ⇒ terminate + "could not be started", không bao giờ HTTP.
- Reconciliation grace: `RECONCILIATION_GRACE_SECONDS = 120` — quan hệ cứng HTTP timeout (Laminas default 10s, TransferFactory không override) < grace. Cron step 0c: thiếu timestamp ⇒ `consumeQueryBudget(reconcile)` (không query, không nhả); trong grace ⇒ no-op toàn phần; hết grace ⇒ identity-query CÙNG m_refund_id. Blocking sets 5 state (thêm `provider_request_started`).
- F24: cohort-3 WHERE của `BackfillRefundState` build bằng HAI lời gọi `quoteInto` một-placeholder riêng (Zend quoteInto không bind tuần tự array) — cohort semantics F20 giữ nguyên.
- F25 (no-defer): real `setup:install` + `setup:upgrade` + `SHOW CREATE TABLE zalo_pay_refund` (credit_memo_id NULL YES, FK CASCADE, UNIQUE (order_id, active_claim), UNIQUE (m_refund_id, active_claim), cột mới) + legacy cohorts A/B/C/D + multi-row/one-owner + claim proof `acquireClaim` thật (unresolved REJECTED / resolved ALLOWED) + F24 WHERE thật trên MariaDB 10.6 disposable + `setup:di:compile` PASS — không còn mục DEFER nào.
- Suite **312 tests / 1126 assertions OK**; PHPCS 0 errors.

## Revision 7 — Round 6 (F26)

- LOCAL_READY grace/staleness (DEC-008): step 0b KHÔNG còn terminate mọi initiating nhìn thấy là terminate. **Fresh** `initiating` (tuổi từ `created_at` < `LOCAL_READY_GRACE_SECONDS = 300`) ⇒ cron no-op hoàn toàn (không query, không terminate, không release) — bảo vệ request A đang trong pha bind cục bộ (`acquireClaim` → save CM → bind → mark provider-start). **Stale** (tuổi ≥ 300s) ⇒ owner crash trước provider-start ⇒ provider I/O bất khả thi theo cấu trúc ⇒ `confirmed_fail` + nhả claim. `created_at` thiếu ⇒ `consumeQueryBudget(reconcile)` bounded, không query, không nhả. Anchor bền = `created_at` (set lúc INSERT claim, không bao giờ đổi; row không thể quay lại `initiating`) ⇒ **không đổi schema**; so UTC-thẳng-UTC như 0c.
- Grace tách bạch: `LOCAL_READY_GRACE_SECONDS = 300` (pha cục bộ không network, hoàn tất < 1s ⇒ headroom >300×) ≠ `RECONCILIATION_GRACE_SECONDS = 120` (HTTP in-flight, trần Laminas timeout 10s). Không gộp giá trị/không gộp nghĩa.
- Tests: cron −2 round-5 initiating +6 F26 (fresh×bound/unbound no-op; stale×bound/unbound released không query; missing-ts budget-only; race composition cron-untouched ⇒ mark thành công). F23 provider-start tests giữ nguyên. Real-DB P20: cron thật qua DI trên MariaDB 10.6 — 21/21 PASS (race + crash recovery + bound FK-thật + side-proof atomic claim 1062).
- Suite **316 tests / 1145 assertions OK**; PHPCS 0 errors; `setup:di:compile` PASS exit 0; SCHEMA_CHANGED=NO ⇒ không bắt buộc setup:upgrade lại (bằng chứng round-5 còn hiệu lực).

# Revision 8 — Round 7 DEADLINE MODE (2026-09-17, PRE_HEAD `c8c59231`)

P0: CM native `STATE_OPEN` duy nhất trước thành công (F27) + xóa state 4/plugin static-interception chết (F34); marker one-shot keyed `credit_memo_id` + `prepare()===null` fail-closed (F28); phân loại response typed — chỉ `return_code=2` = CONFIRMED_FAIL, missing/non-numeric/unexpected ⇒ `RefundProtocolException` → UNKNOWN giữ claim + m_refund_id (F30); `invoice_id` persist pre-provider + confirmed-success khôi phục association ⇒ refund ONLINE (F31); PSLP không bao giờ thành UNKNOWN — `consumeQueryBudget` chỉ atomic increment, selection cron `(attempts<96 OR PSLP)` (F32). P1: CAS conditional UPDATE mọi post-claim transition, CAS-fail ⇒ reload + log, terminal swallow / blocking throw (F29); terminal-explicit cron selection + guard `processRefund` (F33). Giữ (correct+green): FK NO ACTION (F35), retention 90d (F36). Blocker real-gate đã fix: `Rate::getVndAmount` nhận OrderAdapter (TypeError crash sync refund). Invariants trước đó giữ nguyên. Gate: unit 364/1299, PHPCS 0 errors, di:compile PASS, setup:upgrade + FK NO ACTION verified, 5/5 REAL smokes PASS (P21). Chi tiết: DEC-009…015.
