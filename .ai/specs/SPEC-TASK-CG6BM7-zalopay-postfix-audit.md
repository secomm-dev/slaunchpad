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
