---
id: EVIDENCE-TASK-CG6BM7-FINDINGS
work_item: TASK-CG6BM7
spec: SPEC-TASK-CG6BM7-zalopay-postfix-audit
kind: audit-findings
baseline_commit: a48de3cac477cada0882974151db7765c554aa25
pre_existing_zalopay_commit: 181dc1d0a0aae8acf825ea08f73848c704c0ef76
created: 2026-09-16
---

# Audit findings — Secomm_ZaloPay post-fix audit (TASK-CG6BM7)

Phạm vi: `app/code/Secomm/ZaloPay` trên baseline `a48de3c` (đã chứa commit prior-implementation `181dc1d`).
Đối chiếu contract chính thức docs.zalopay.vn (refund ASYNC + `v2/query_refund`; MAC query = `app_id|m_refund_id|timestamp`; `v2/query_refund` response KHÔNG có `refund_id`).

## Đã fix (BLOCKER/HIGH — xác nhận trong audit này)

| # | Vấn đề | Mức | Fix + bằng chứng |
|---|--------|-----|------------------|
| F1 | `"abc"` → 0.0 âm thầm trong `Rate::toNumericAmount` (biến đổi tiền sai số lớn nhất) | BLOCKER | Strict parser; sai → `LocalizedException`. RATE 8–15 (`Test/Unit/Gateway/Helper/RateTest.php`) |
| F2 | Email xác nhận có thể gửi trước payment verified/commit (§3) | HIGH | Email chỉ sau commit, FINALIZED-duplicate backfill có guard `email_sent`; exception email không rollback. EMAIL 1–7 (`Test/Unit/Service/OrderFinalizerTest.php`) |
| F3 | RefundCommand ném `\Exception`/CommandException — `Payment::refund` chỉ bắt `LocalizedException` → raw provider/transport text lọt UI | HIGH | LocalizedException-only; message từ provider map trên response hiện tại; raw text chỉ log. REFUND CMD 16–23 (`Test/Unit/Gateway/Command/RefundCommandTest.php`) |
| F4 | Refund cron: vòng query vô hạn cho provider FAIL; chọn lại mọi `is_processed=0` mãi mãi | HIGH | Bounded budget `query_attempts` (96 ≈ 24h với cron */15 — khớp `etc/crontab.xml` `secomm_zalopay_refund_cronjob`), FAIL terminal + `last_error` evidence, per-item `try/catch (\Throwable)`. REFUND CRON 24–30 (`Test/Unit/Cron/RefundCronjobTest.php`) |
| F5 | `ResponseMessagesHandler` đọc `return_code` không có guard; code 3 (PROCESSING) bị gắn cờ fraud | HIGH | `readReturnCode` null-safe; 1→approve; 2/unknown→error+fraud; 3→message, KHÔNG fraud; missing/non-numeric → no mutation. RESPONSE 31–34 (`Test/Unit/Gateway/Response/ResponseMessagesHandlerTest.php`) |
| F6 | `TransactionRefundHandler` set transaction id từ `refund_id` không kiểm tra — nhưng `v2/query_refund` KHÔNG trả `refund_id` theo docs | MEDIUM→fix | Guard null/empty trước `setTransactionId`; close-parent theo invoice `canRefund()`. |

## Ghi nhận KHÔNG fix (MEDIUM/LOW — theo §10 chỉ fix BLOCKER/HIGH)

| # | Finding | Mức | Lý do không fix trong task này |
|---|---------|-----|-------------------------------|
| M1 | `RefundQueryCommand::execute()` còn TODO stub (không được gọi trong runtime path — `getRefundQuery()` là entry thực) | MEDIUM | Dead code, không ảnh hưởng runtime; để TL quyết xoá hay implement |
| M2 | Dead plugin `generateMac` trên virtualType `ZaloPayRefundDataBuilder` (RefundCommand build MAC riêng) | MEDIUM | Không chạy trong path hiện tại; xoá là thay đổi DI diện rộng → cần TL duyệt |
| M3 | `RefundProcessor::processRefundStatus` map sai lệch docs: `-1` map "System error." (docs: REFUND_PENDING); thiếu 0/−32/−101/−999; trùng lặp −24/−25/−26 | MEDIUM | Hiển thị admin-only; các path hệ thống (cron last_error, RefundCommand message) dùng map này cho evidence — không đỉnh đến hành vi thanh toán. Đã đề xuất trong spec §2.5 cho task sau |
| M4 | Refund instant-SUCCESS (return_code 1 ngay) không persist row → cron không có evidence trail | LOW | Creditmemo đã REFUNDED đồng bộ; row chỉ dùng cho async pending. Mở rộng schema thêm cột nữa là over-engineering |
| M5 | `m_refund_id` re-submit residual risk (double-submit refund cùng creditmemo trước khi cron chốt) | MEDIUM | Cần claim/lock ở Payment layer — DEC-TASKCG6BM7-001 đã đánh giá và NOT-AUTHORIZED scope này |
| M6 | PHPCS +5 warnings style mới (MethodAnnotationStructure x4, VariableTranslation x2 từ message động provider-map; bù −1 LineLength) | LOW | Style-only, 0 error; VariableTranslation là hệ quả cố ý của message động từ provider map (không phải i18n target) |

## Validator .ai

- `.ai` validator: **26 FAIL pre-existing trên baseline `a48de3c`** (liên quan records/audit khác, ngoài scope). Các record của TASK-CG6BM7 (TASK/SPEC/PLAN/DEC-001/DEC-002) không tạo FAIL mới.

---

# Corrective round 2026-09-16 (TL direct source review)

## Ghi nhận trung thực về giới hạn của audit round 1

- **F4 round 1 CHƯA ĐỦ.** Bằng chứng "bounded retry" của round 1 chỉ bao phủ provider FAIL/PROCESSING
  từ góc nhìn cron; **transport exception KHÔNG tiêu query budget** — row với provider timeout kéo
  dài được chọn lại mãi mãi (loop vô hạn tiềm ẩn), đối chiếu đúng nghĩa "retry bounded" của TL thì
  F4 khi đó chỉ chứng minh được một nửa. Đã fix: mọi genuine attempt tiêu budget (transport,
  protocol anomaly, finalize failure đều +1 attempt với safe evidence); non-retryable
  (FAIL/malformed payload/thiếu m_refund_id/missing creditmemo/state drift) → terminal saturate 96.
- **Audit round 1 đã BỎ LỠ tương tác refund-gateway với kế toán/order-state của Magento core.**
  Round 1 audit RefundCommand như một unit đóng (messaging, guard, finally-persist) mà không truy
  tiếp chuyện gì xảy ra SAU khi command trả về trong flow core: `CreditmemoService::refund` set
  `STATE_REFUNDED` TRƯỚC khi gọi gateway, rồi `RefundOperation` mutate TOÀN BỘ order refund totals
  ⇒ với return_code 3 (PROCESSING) flow cũ vẫn finalize kế toán Magento khi tiền chưa xác nhận, và
  full refund đẩy order CLOSED trong khi ZaloPay vẫn xử lý. Đây là BLOCKER thật của baseline
  (chính code của round 1 cũng vẫn chứa nó) — được TL bắt qua direct source review.

## Fix corrective (BLOCKER/HIGH mới)

| # | Vấn đề | Mức | Fix + bằng chứng |
|---|--------|-----|------------------|
| F7 | PROCESSING vẫn để core finalize totals/order-state (order CLOSED sớm) | BLOCKER | Kiến trúc plugin-orchestrated (DEC-TASKCG6BM7-003): RefundCommand provider-only, `CreditmemoRefundPlugin` hỏi provider TRƯỚC core; PROCESSING → `registerPending` + STOP trước `$proceed()`; cron `finalizeSuccess` exact-once qua native accounting trong 1 tx khoá row. Tests: `PendingRefundManagerTest` (11), `CreditmemoRefundPluginTest` (11), `RefundCommandTest` (11) |
| F8 | Transport exception không tiêu query budget (retry không bounded thật) | BLOCKER | `RefundQueryCommand` ném `RefundTransportException`; cron/plugin classify transport → `consumeQueryBudget('transport_error: …')`; non-retryable → `terminate('reconcile_error:'/'refund_failed:')`. Tests: `RefundCronjobTest` (13) |
| F9 | Race email trùng: email_sent-guard-only không chống 2 finalizer đồng thời | HIGH | Atomic claim `email_dispatch` (conditional UPDATE trong tx khoá row, token-guarded release, grace 900s). Tests: `PaymentAttemptResourceTest` (3) + EMAIL 8–10 trong `OrderFinalizerTest` (24 test) |

## Ghi nhận không đổi

- Các M1–M6 round 1 giữ nguyên trạng thái (out of scope theo §10, chờ TL).
- `RefundOutcomeMarker` là process-scoped (DI share theo scope): đủ cho invariant "no second
  provider refund" vì provider chỉ được hỏi trong request của plugin; cron KHÔNG BAO GIỜ gọi
  provider refund (chỉ query_refund). Ghi rõ để TL đánh giá: nếu sau này cron cần gọi refund lại,
  marker phải đổi sang durable.

---

# Corrective round 2 2026-09-16 (TL direct source review lần 2)

## Ghi nhận trung thực về hai khiếm khuyết còn sót trong chính code corrective round 1

- **F10 — Round 1 đặt provider I/O TRƯỚC Magento core validation.** `CreditmemoRefundPlugin::
  aroundRefund` gọi `refundCommand->execute()` TRƯỚC `$proceed()`, mà validation hợp lệ của
  refund Magento (`CreditmemoService::validateForRefund`, protected) nằm BÊN TRONG `$proceed()`
  ⇒ một refund Magento KHÔNG hợp lệ (over-refund; credit memo đã processed; order reference
  hỏng; amount <= 0) vẫn chạm ZaloPay trước khi bị chặn. Fix: `CreditmemoRefundPreflight` mirror
  1:1 toàn bộ `validateForRefund` (3 checks, cùng thứ tự, cùng message contract, cùng rounding
  qua PriceCurrencyInterface) + check bổ sung số tiền online > 0; plugin gọi preflight TRƯỚC mọi
  provider I/O và TRƯỚC mọi persistence — pending Credit Memo KHÔNG được persist khi preflight
  chưa pass. Nguồn mirror: vendor/magento/module-sales/Model/Service/CreditmemoService.php
  2.4.8-p5 :189-219 (refund :147-180) — upgrade coupling trong DEC-TASKCG6BM7-004.
- **F11 — Row UNKNOWN cạn ngân sách query rơi KHỎI blocking.** `hasInFlight` round 1 =
  `is_processed = 0 AND query_attempts < MAX`: transport/protocol exhaustion đẩy row khỏi guard
  trong khi outcome tại provider có thể là SUCCESS ⇒ admin refund lại được ⇒ DOUBLE REFUND.
  Fix: cột `refund_state` (processing | confirmed_success | confirmed_fail | unknown) là nguồn
  quyết định blocking; `hasInFlight` = state IN (processing, unknown); `consumeQueryBudget` tự
  quarantine → unknown khi cạn budget; `terminate` nhận state tường minh (mặc định unknown —
  outcome chưa xác nhận KHÔNG bao giờ được coi là an toàn); chỉ `confirmed_fail` mở khóa;
  `confirmed_success` chuyển quyền kiểm soát về số dư refundable chuẩn. `query_attempts == MAX`
  KHÔNG còn tự nó mang nghĩa "safe to refund".

## Fix corrective round 2

| # | Vấn đề | Mức | Fix + bằng chứng |
|---|--------|-----|------------------|
| F10 | Provider được hỏi trước khi Magento refund validation pass | BLOCKER | `Service/CreditmemoRefundPreflight.php` (mirror CreditmemoService.php:189-219) + plugin gọi tại `CreditmemoRefundPlugin.php:121-127` trước provider call :148. Tests: `CreditmemoRefundPreflightTest` (9) + plugin matrix "provider NEVER called" (over-refund / non-open CM / invalid order / zero amount → `RefundCommand::execute` never; valid → exactly once, preflight trước provider) |
| F11 | UNKNOWN cạn budget ngừng blocking → double refund | BLOCKER | `refund_state` (db_schema + whitelist + `RefundInterface` constants); `PendingRefundManager` hasInFlight theo state (:108-118), consumeQueryBudget quarantine (:199-206), terminate state tường minh (:228-238), finalizeSuccess → confirmed_success; cron FAIL → confirmed_fail. Tests: `PendingRefundManagerTest` (16), `RefundCronjobTest` (13 — FAIL ↦ confirmed_fail, state-drift ↦ unknown mặc định) |

## Không đổi (regression matrix giữ lại qua round 2)

- PROCESSING không mutate refunded totals, không đóng order; SUCCESS finalize qua native
  accounting; full SUCCESS → Magento tự xử lý CLOSED; provider FAIL không đụng kế toán;
  transport retry tiêu budget; email claim atomic; email fail không rollback payment; không có
  provider refund thứ hai trong local finalization — toàn bộ vẫn được pin bởi test matrix
  (full suite **279 tests / 988 assertions**, green).

---

# Corrective round 3 2026-09-16 (TL direct source review lần 3)

## Ghi nhận trung thực về giới hạn của fix round 2

- **F12 — Blocking guard round 2 vẫn là check-then-act.** `hasInFlight()==false → preflight →
  gọi ZaloPay → persist` KHÔNG concurrency-safe: hai request đồng thời cùng đọc
  `hasInFlight()==false` (chưa ai kịp insert) và CẢ HAI đều đi tới provider /refund. Guard
  SELECT-then-INSERT không bao giờ đủ; DEC-TASKCG6BM7-004 D2 chỉ đúng về semantic state, sai về
  atomicity của việc chiếm quyền. Fix: atomic claim DB-level (D1 DEC-005) — unique index
  NULL-trick trọng tài, thua cuộc chết TRƯỚC HTTP.
- **F13 — Provider SUCCESS + local fail rơi vào hư vô.** Round 2: `$proceed()` throw sau
  provider SUCCESS ⇒ exception propagates mà KHÔNG có durable state nào nói "tiền đã ra" —
  cron không có gì để finalize; hành vi thực tế khiến admin thử lại và cron/in-flight guard
  đều mở đường ⇒ double refund. Đây là blind spot của chính kiến trúc round 1+2: mọi state
  đều gắn với "provider chưa chắc SUCCESS".
- **F14 — Schema default `processing` nuốt dữ liệu lịch sử.** Cột `refund_state` default
  `processing` (DEC-004 D2) áp cho row CŨ khi ALTER: mọi refund đã hoàn tất từ trước
  (`is_processed=1`) bỗng nằm trong blocking set ⇒ order từng refund thành công không thể
  refund tiếp. Round 2 không có data patch — thiếu sót thật.
- **F15 — Confirmed FAIL để credit memo kẹt PROCESSING.** Provider từ chối tường minh (tiền
  KHÔNG ra) nhưng credit memo vẫn parked STATE_PROCESSING: hiển thị sai "đang xử lý", và
  (tùy version core) chặn refund sau đó. Round 2 chỉ mở khóa row, quên đối tượng Magento
  phía trên.
- **F16 — Transport UNKNOWN mượn nghĩa PROCESSING.** `markProcessing`-style persistence cho
  outcome không xác nhận làm nhiễu semantic: PROCESSING nghĩa là "provider đang xử lý, có
  query path"; UNKNOWN nghĩa là "không biết gì" — hai nghĩa phải là hai state.

## Fix corrective round 3

| # | Vấn đề | Mức | Fix + bằng chứng |
|---|--------|-----|------------------|
| F12 | check-then-act race ⇒ DOUBLE REFUND | BLOCKER | Atomic durable claim (DEC-005 D1): `active_claim` smallint NULL + 2 unique index `(order_id, active_claim)`/`(m_refund_id, active_claim)` (NULL-trick: row lịch sử không xung đột, tối đa 1 active/order + 1 active/m_refund_id); `acquireClaim` = 1 INSERT autocommit KHÔNG transaction (commit TRƯỚC provider I/O, không row lock xuyên HTTP); duplicate-key → thua cuộc chết TRƯỚC HTTP với "active or awaiting reconciliation"; `RefundCommand` tách `prepare()` (identity không I/O, :128) / `executePrepared()` (provider DUY NHẤT, :187). Chuỗi anchor plugin: guard :111 → preflight :142 → prepare :165 → **claim :176** → **provider :179**. DB evidence E1–E8 (MariaDB throwaway). Tests: `PendingRefundManagerTest` acquireClaim×4 (24 test), `CreditmemoRefundPluginTest` testClaimConflictPropagatesWithoutProviderCall |
| F13 | provider SUCCESS + local finalize fail = tiền ra, không durable state | BLOCKER | State mới `provider_success_local_pending` (PSLP): plugin bắt `\Throwable` quanh `$proceed()` SAU provider SUCCESS (:236-251) → `markProviderSuccessLocalPending` + message "succeeded at the provider but the local accounting is incomplete" (KHÔNG fail như refund thất bại); cron step 0 PSLP shortcut (:136-165) → `finalizeSuccess` CHỈ local, KHÔNG query_refund, KHÔNG /refund; finalize fail → consumeQueryBudget bounded. `RefundOutcomeMarker` mark TRƯỚC `$proceed()` nên finalize-lại không bao giờ hỏi provider lần hai. Tests: `testProviderSuccessWithLocalFailureLandsPendingState`, `testPslpRowFinalizesLocallyWithoutProviderQuery`, `testPslpFinalizeFailureConsumesBudget` |
| F14 | row lịch sử (is_processed=1) bị default `processing` chặn | HIGH | `hasInFlight` thêm filter `is_processed = 0` (:106-118, 3 filter); data patch `Setup/Patch/Data/BackfillRefundState.php` backfill theo evidence: `is_processed=1 AND last_error LIKE 'refund_failed:%'` → confirmed_fail; còn lại `is_processed=1` → confirmed_success; `is_processed=0` → unknown (bảo thủ, block). Tests: `BackfillRefundStateTest` (3 cohort + evidence order), `testHasInFlightThreeFiltersBlockingStates` |
| F15 | confirmed FAIL để CM kẹt PROCESSING mãi | HIGH | Cron FAIL branch → `releaseCreditmemoAfterFail` (:332-350): CM đang `STATE_PROCESSING`(4) → `setState(STATE_OPEN)` + save (STATE_OPEN = state core validateForRefund chấp nhận); kế toán KHÔNG đụng; KHÔNG bịa REFUNDED; release fail → critical swallow (row đã non-blocking). Tests: `testProviderFailReleasesCreditmemoToOpen`, `testProviderFailReleaseSaveFailureIsSwallowed`, `testProviderFailIsTerminalWithEvidence` (assert STATE_OPEN + save) |
| F16 | transport UNKNOWN lưu thành `processing` | HIGH | `markUnknown` semantic riêng (:214); plugin transport path (:180-206) land `unknown` với evidence `transport_error:`; blocking set v3 = initiating + processing + unknown + PSLP. Tests: `testMarkUnknownLandsSemanticUnknown`, `testUntrackableTransportStillMarksUnknownOnClaim`, quarantine tests giữ nguyên |

## Crash/recovery (bắt buộc TL, chứng minh riêng)

- Identity ổn định: claim INSERT mang `m_refund_id` mà `prepare()` đã build TRƯỚC I/O ⇒ crash
  bất kỳ đâu sau `/refund` để lại row chứa ĐÚNG identity provider đã thấy; cron query bằng
  m_refund_id đó (payload tái-sign từ row, `buildQuerySubject`), KHÔNG tạo refund mới.
- Kịch bản: crash giữa provider I/O và persist → plugin exception path không chạy → row ở
  `initiating` (claim đã commit) → cron step 2b/4 xử lý theo query outcome; nếu CM chưa tồn
  tại → step 1 terminate unknown (manual reconcile, không bao giờ refund lại).

## Không đổi (regression matrix giữ nguyên qua round 3)

- 11 mục DO-NOT-REGRESS của TL: Magento validation trước provider (preflight, round 2 giữ
  nguyên ở :142); PROCESSING ≠ completed refund (không mutate totals, không CLOSE); SUCCESS
  finalize exact-once native; full SUCCESS → Magento tự CLOSED; FAIL không đụng kế toán;
  unknown/exhausted block; transport retry bounded; email claim atomic; email fail không
  rollback payment. Full suite **296 tests / 1057 assertions OK**.
